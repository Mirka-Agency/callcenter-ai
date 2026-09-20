<?php

namespace App\Services\Reports;

use App\Models\Call;
use App\Models\VoipCallLog;
use Carbon\Carbon;

class OrganizationCallMetrics
{
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

        $linkedVoipLogIds = Call::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('voip_call_log_id')
            ->pluck('voip_call_log_id');

        $callCount = Call::query()
            ->where('organization_id', $organizationId)
            ->occurredBetween($from, $to)
            ->count();

        $orphanVoipCount = VoipCallLog::query()
            ->where('organization_id', $organizationId)
            ->occurredBetween($from, $to)
            ->when(
                $linkedVoipLogIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $linkedVoipLogIds),
            )
            ->count();

        return $callCount + $orphanVoipCount;
    }
}
