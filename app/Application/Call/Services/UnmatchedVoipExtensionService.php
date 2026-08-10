<?php

namespace App\Application\Call\Services;

use App\Models\Call;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\ConversationAnalysis;
use App\Models\VoipCallLog;
use App\Services\EmployeeIntegrationMetaService;
use Illuminate\Support\Carbon;

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
     *     call_count: int,
     *     last_call_at: ?Carbon
     * }>
     */
    public function listUnmatched(Organization $organization, int $days = 14): array
    {
        $logs = VoipCallLog::query()
            ->where('organization_id', $organization->id)
            ->where('started_at', '>=', now()->subDays($days))
            ->with('connection')
            ->orderByDesc('started_at')
            ->get();

        /** @var array<string, array{extension: string, connection_id: int, connection_name: string, call_count: int, last_call_at: ?Carbon}> $aggregated */
        $aggregated = [];

        foreach ($logs as $log) {
            if ($this->resolver->resolveFromCallLog($log) !== null) {
                continue;
            }

            $extension = $this->primaryExtension($log);

            if ($extension === null) {
                continue;
            }

            if ($this->resolver->resolveByExtension(
                (int) $log->organization_id,
                (int) $log->organization_voip_connection_id,
                $extension,
            ) !== null) {
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
                ];
            }

            $aggregated[$key]['call_count']++;

            $startedAt = $log->started_at;

            if ($startedAt !== null && (
                $aggregated[$key]['last_call_at'] === null
                || $startedAt->gt($aggregated[$key]['last_call_at'])
            )) {
                $aggregated[$key]['last_call_at'] = $startedAt;
            }
        }

        return collect($aggregated)
            ->sortByDesc(fn (array $row) => $row['last_call_at']?->timestamp ?? 0)
            ->values()
            ->all();
    }

    public function assignExtensionToEmployee(
        Organization $organization,
        string $extension,
        int $connectionId,
        int $organizationUserId,
        ?int $days = null,
    ): int {
        $connection = OrganizationVoipConnection::query()
            ->where('organization_id', $organization->id)
            ->whereKey($connectionId)
            ->firstOrFail();

        $employee = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->whereKey($organizationUserId)
            ->where('is_active', true)
            ->firstOrFail();

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
     */
    public function backfillCalls(
        Organization $organization,
        string $extension,
        int $connectionId,
        ?int $days = null,
        ?int $organizationUserId = null,
    ): int {
        $extension = trim($extension);

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
        }

        return $count;
    }

    public function primaryExtension(VoipCallLog $log): ?string
    {
        $candidates = $this->resolver->extensionCandidates($log);

        return $candidates[0] ?? null;
    }
}
