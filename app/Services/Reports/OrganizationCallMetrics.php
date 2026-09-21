<?php

namespace App\Services\Reports;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Models\Call;
use App\Models\VoipCallLog;
use Carbon\Carbon;

class OrganizationCallMetrics
{
    public function __construct(
        private CallEmployeeResolver $resolver,
    ) {}

    public function countToday(int $organizationId): int
    {
        return $this->countBetween(
            $organizationId,
            now()->startOfDay(),
            now()->endOfDay(),
        );
    }

    public function countThisMonth(int $organizationId): int
    {
        return $this->countBetween(
            $organizationId,
            now()->startOfMonth()->startOfDay(),
            now()->endOfDay(),
        );
    }

    public function countBetween(int $organizationId, Carbon $from, Carbon $to): int
    {
        $from = $from->copy();
        $to = $to->copy();

        $extensionMap = $this->resolver->extensionEmployeeMapForOrganization($organizationId);
        $definedEmployeeIds = array_values(array_unique(array_map('intval', array_values($extensionMap))));

        $callQuery = Call::query()
            ->where('organization_id', $organizationId)
            ->occurredBetween($from, $to)
            ->whereNotNull('organization_user_id');

        if ($definedEmployeeIds !== []) {
            $callQuery->whereIn('organization_user_id', $definedEmployeeIds);
        }

        $callCount = $callQuery->count();

        $linkedVoipLogIds = Call::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('voip_call_log_id')
            ->pluck('voip_call_log_id');

        $orphanLogs = VoipCallLog::query()
            ->where('organization_id', $organizationId)
            ->occurredBetween($from, $to)
            ->when(
                $linkedVoipLogIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $linkedVoipLogIds),
            )
            ->get();

        $orphanCount = 0;

        foreach ($orphanLogs as $log) {
            $employeeId = $this->resolver->resolveFromCallLogUsingMap($log, $extensionMap);

            if ($employeeId === null) {
                continue;
            }

            $orphanCount++;
        }

        return $callCount + $orphanCount;
    }
}
