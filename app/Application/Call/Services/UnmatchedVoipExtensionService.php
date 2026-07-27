<?php

namespace App\Application\Call\Services;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
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
        int $days = 14,
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

        return $this->backfillCalls($organization, $extension, $connectionId, $days);
    }

    public function backfillCalls(
        Organization $organization,
        string $extension,
        int $connectionId,
        int $days = 14,
    ): int {
        $logs = VoipCallLog::query()
            ->where('organization_id', $organization->id)
            ->where('organization_voip_connection_id', $connectionId)
            ->where('started_at', '>=', now()->subDays($days))
            ->get();

        $count = 0;

        foreach ($logs as $log) {
            if (! in_array($extension, $this->resolver->extensionCandidates($log), true)) {
                continue;
            }

            $this->ingestion->ingestFromVoipLog($log);
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
