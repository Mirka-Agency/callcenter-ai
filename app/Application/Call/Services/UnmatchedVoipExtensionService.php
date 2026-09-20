<?php

namespace App\Application\Call\Services;

use App\Application\Intelligence\Services\CallAnalysisQueueService;
use App\Domain\Voip\Enums\CallDirection;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\EmployeeIntegrationMeta;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipCallLog;
use App\Services\EmployeeIntegrationMetaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnmatchedVoipExtensionService
{
    public function __construct(
        private CallEmployeeResolver $resolver,
        private CallIngestionService $ingestion,
    ) {}

    /**
     * @return list<array{
     *     extension: string,
     *     connection_id: int,
     *     connection_name: string,
     *     employee_id: int,
     *     employee_name: string,
     *     employee_is_active: bool
     * }>
     */
    public function listAssigned(Organization $organization): array
    {
        $connectionIds = OrganizationVoipConnection::query()
            ->where('organization_id', $organization->id)
            ->pluck('id');

        if ($connectionIds->isEmpty()) {
            return [];
        }

        $rows = EmployeeIntegrationMeta::query()
            ->where('integratable_type', OrganizationVoipConnection::class)
            ->whereIn('integratable_id', $connectionIds)
            ->where('key', 'extension')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->with(['employee', 'integratable'])
            ->get();

        return $rows
            ->map(function (EmployeeIntegrationMeta $meta): ?array {
                $extension = $this->normalizeExtension((string) $meta->value);

                if ($extension === '') {
                    return null;
                }

                $employee = $meta->employee;

                return [
                    'extension' => $extension,
                    'connection_id' => (int) $meta->integratable_id,
                    'connection_name' => $meta->integratable?->name ?? '—',
                    'employee_id' => (int) $meta->organization_user_id,
                    'employee_name' => $employee?->full_name ?: '—',
                    'employee_is_active' => (bool) ($employee?->is_active ?? false),
                ];
            })
            ->filter()
            ->sortBy(fn (array $row) => [$row['connection_name'], $row['extension']])
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     extension: string,
     *     connection_id: int,
     *     connection_name: string,
     *     call_count: int,
     *     last_call_at: ?Carbon,
     *     last_source_number: ?string,
     *     last_destination_number: ?string,
     *     last_direction: ?string,
     *     last_customer_number: ?string
     * }>
     */
    public function listUnmatched(Organization $organization, ?int $days = null): array
    {
        $extensionMap = $this->resolver->extensionEmployeeMapForOrganization((int) $organization->id);

        $query = VoipCallLog::query()
            ->where('organization_id', $organization->id)
            ->with('connection')
            ->select([
                'id',
                'organization_id',
                'organization_voip_connection_id',
                'direction',
                'source_number',
                'destination_number',
                'started_at',
                'raw_payload',
            ]);

        if ($days !== null) {
            $query->where('started_at', '>=', now()->subDays($days));
        }

        /** @var array<string, array{extension: string, connection_id: int, connection_name: string, call_count: int, last_call_at: ?Carbon, last_source_number: ?string, last_destination_number: ?string, last_direction: ?string, last_customer_number: ?string}> $aggregated */
        $aggregated = [];

        foreach ($query->lazy(500) as $log) {
            if ($this->resolver->resolveFromCallLogUsingMap($log, $extensionMap) !== null) {
                continue;
            }

            $extension = $this->primaryExtension($log);

            if ($extension === null) {
                continue;
            }

            $key = $extension.'|'.$log->organization_voip_connection_id;

            if (! isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'extension' => $extension,
                    'connection_id' => (int) $log->organization_voip_connection_id,
                    'connection_name' => $log->connection?->name ?? '—',
                    'call_count' => 0,
                    'last_call_at' => null,
                    'last_source_number' => null,
                    'last_destination_number' => null,
                    'last_direction' => null,
                    'last_customer_number' => null,
                ];
            }

            $aggregated[$key]['call_count']++;

            $startedAt = $log->started_at;

            if ($startedAt !== null && (
                $aggregated[$key]['last_call_at'] === null
                || $startedAt->gt($aggregated[$key]['last_call_at'])
            )) {
                $aggregated[$key]['last_call_at'] = $startedAt;
                $aggregated[$key]['last_source_number'] = $log->source_number;
                $aggregated[$key]['last_destination_number'] = $log->destination_number;
                $aggregated[$key]['last_direction'] = $log->direction?->value;
                $aggregated[$key]['last_customer_number'] = $this->customerNumberFromLog($log, $extension);
            }
        }

        return collect($aggregated)
            ->sortByDesc(fn (array $row) => $row['last_call_at']?->timestamp ?? 0)
            ->values()
            ->all();
    }

    public function createExtension(
        Organization $organization,
        string $extension,
        int $connectionId,
        int $organizationUserId,
    ): int {
        $extension = $this->normalizeExtension($extension);
        $connection = $this->connectionForOrganization($organization, $connectionId);
        $employee = $this->activeEmployeeForOrganization($organization, $organizationUserId);

        $this->assertEmployeeHasNoExtensionOnConnection($employee, $connection, errorKey: 'newEmployeeId');

        return $this->assignExtensionToEmployee(
            organization: $organization,
            extension: $extension,
            connectionId: $connectionId,
            organizationUserId: $organizationUserId,
        );
    }

    public function reassignExtension(
        Organization $organization,
        string $extension,
        int $connectionId,
        int $organizationUserId,
    ): int {
        $extension = $this->normalizeExtension($extension);
        $connection = $this->connectionForOrganization($organization, $connectionId);
        $employee = $this->activeEmployeeForOrganization($organization, $organizationUserId);

        $current = $this->extensionMeta($organization, $extension, $connectionId);

        if ($current && (int) $current->organization_user_id === (int) $employee->id) {
            return 0;
        }

        $this->assertEmployeeHasNoExtensionOnConnection($employee, $connection, $extension, 'employee');

        return DB::transaction(function () use ($organization, $extension, $connectionId, $employee, $current): int {
            $current?->delete();

            return $this->assignExtensionToEmployee(
                organization: $organization,
                extension: $extension,
                connectionId: $connectionId,
                organizationUserId: (int) $employee->id,
            );
        });
    }

    public function removeExtension(
        Organization $organization,
        string $extension,
        int $connectionId,
    ): void {
        $extension = $this->normalizeExtension($extension);

        $this->connectionForOrganization($organization, $connectionId);

        $this->extensionMeta($organization, $extension, $connectionId)?->delete();
    }

    public function assignExtensionToEmployee(
        Organization $organization,
        string $extension,
        int $connectionId,
        int $organizationUserId,
        ?int $days = null,
    ): int {
        $extension = $this->normalizeExtension($extension);

        $connection = $this->connectionForOrganization($organization, $connectionId);
        $employee = $this->activeEmployeeForOrganization($organization, $organizationUserId);

        EmployeeIntegrationMetaService::assignVoipExtension($employee, $connection, $extension);

        return $this->backfillCalls(
            organization: $organization,
            extension: $extension,
            connectionId: $connectionId,
            days: $days,
            organizationUserId: $organizationUserId,
        );
    }

    /**
     * Attach the employee to every matching VoIP call (and related analyses).
     * When $days is null, all historical calls for that extension are updated.
     * Calls with a recording and no analysis are then placed in the processing queue.
     */
    public function backfillCalls(
        Organization $organization,
        string $extension,
        int $connectionId,
        ?int $days = null,
        ?int $organizationUserId = null,
    ): int {
        $extension = $this->normalizeExtension($extension);

        if ($extension === '') {
            return 0;
        }

        $query = VoipCallLog::query()
            ->where('organization_id', $organization->id)
            ->where('organization_voip_connection_id', $connectionId);

        if ($days !== null) {
            $query->where(function ($builder) use ($days): void {
                $builder->where('started_at', '>=', now()->subDays($days))
                    ->orWhereNull('started_at');
            });
        }

        $logs = $query->get();
        $count = 0;
        $matched = collect();

        foreach ($logs as $log) {
            if (! in_array($extension, $this->resolver->extensionCandidates($log), true)) {
                continue;
            }

            $callId = $this->ingestion->ingestFromVoipLog($log);
            $employeeId = $organizationUserId
                ?? $this->resolver->resolveFromCallLog($log);

            if ($employeeId !== null) {
                Call::query()
                    ->where('organization_id', $organization->id)
                    ->where(function ($builder) use ($log, $callId): void {
                        $builder->where('voip_call_log_id', $log->id)
                            ->orWhere('id', $callId);
                    })
                    ->where(function ($builder) use ($employeeId): void {
                        $builder->whereNull('organization_user_id')
                            ->orWhere('organization_user_id', '!=', $employeeId);
                    })
                    ->update(['organization_user_id' => $employeeId]);

                ConversationAnalysis::query()
                    ->where('organization_id', $organization->id)
                    ->where(function ($query) use ($log, $callId): void {
                        $query->where('voip_call_log_id', $log->id)
                            ->orWhere('call_id', $callId);
                    })
                    ->update(['organization_user_id' => $employeeId]);
            }

            $count++;
            $matched->push($log);
        }

        $this->enqueueUnanalyzedLogs($organization, $matched);

        return $count;
    }

    /**
     * @param  Collection<int, VoipCallLog>  $logs
     */
    private function enqueueUnanalyzedLogs(Organization $organization, $logs): void
    {
        $queue = app(CallAnalysisQueueService::class);

        foreach ($logs as $log) {
            $callId = $this->ingestion->ingestFromVoipLog($log);
            $call = Call::query()->find($callId);

            if ($call) {
                $queue->dispatchForCall($call);
            }
        }
    }

    public function primaryExtension(VoipCallLog $log): ?string
    {
        $candidates = $this->resolver->extensionCandidates($log);

        return $candidates[0] ?? null;
    }

    public static function normalizeExtension(string $extension): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return trim(str_replace($arabic, $english, str_replace($persian, $english, $extension)));
    }

    private function connectionForOrganization(Organization $organization, int $connectionId): OrganizationVoipConnection
    {
        return OrganizationVoipConnection::query()
            ->where('organization_id', $organization->id)
            ->whereKey($connectionId)
            ->firstOrFail();
    }

    private function activeEmployeeForOrganization(Organization $organization, int $organizationUserId): OrganizationUser
    {
        return OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->whereKey($organizationUserId)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function extensionMeta(
        Organization $organization,
        string $extension,
        int $connectionId,
    ): ?EmployeeIntegrationMeta {
        return EmployeeIntegrationMeta::query()
            ->where('integratable_type', OrganizationVoipConnection::class)
            ->where('integratable_id', $connectionId)
            ->where('key', 'extension')
            ->where('value', $extension)
            ->whereHas('employee', fn ($query) => $query->where('organization_id', $organization->id))
            ->first();
    }

    private function assertEmployeeHasNoExtensionOnConnection(
        OrganizationUser $employee,
        OrganizationVoipConnection $connection,
        ?string $exceptExtension = null,
        string $errorKey = 'newEmployeeId',
    ): void {
        $existing = EmployeeIntegrationMeta::query()
            ->where('organization_user_id', $employee->id)
            ->where('integratable_type', OrganizationVoipConnection::class)
            ->where('integratable_id', $connection->id)
            ->where('key', 'extension')
            ->first();

        if (! $existing) {
            return;
        }

        $existingExtension = $this->normalizeExtension((string) $existing->value);

        if ($exceptExtension !== null && $existingExtension === $exceptExtension) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => __('ui.voip.extensions_employee_has_other', ['extension' => $existingExtension]),
        ]);
    }

    private function customerNumberFromLog(VoipCallLog $log, string $extension): ?string
    {
        $source = $log->source_number;
        $destination = $log->destination_number;
        $preferred = $log->direction === CallDirection::Outbound
            ? [$destination, $source]
            : [$source, $destination];

        foreach ($preferred as $number) {
            if ($number !== null && $number !== '' && $number !== $extension) {
                return $number;
            }
        }

        return $source ?: $destination;
    }
}
