<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilter;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\VoipCallLog;
use App\Support\CompanyWorkCalendar;
use App\Support\JalaliDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CallMetricsAnalytics
{
    public function totalCalls(ReportFilter $filter): int
    {
        $voip = $filter->applyToVoipQuery(VoipCallLog::query())->count();

        $manualQuery = Call::query()
            ->where('organization_id', $filter->organizationId)
            ->whereBetween('created_at', [$filter->from, $filter->to]);

        return $voip + $manualQuery->count();
    }

    /** @return list<array{period: string, label: string, count: int}> */
    public function callActivityTrend(ReportFilter $filter): array
    {
        $granularity = $filter->granularity();
        $voipCalls = $filter->applyToVoipQuery(VoipCallLog::query())
            ->get(['started_at', 'created_at']);

        $buckets = [];

        foreach ($voipCalls as $call) {
            $occurredAt = $call->started_at ?? $call->created_at;

            if ($occurredAt === null) {
                continue;
            }

            $key = $this->periodKey($occurredAt, $granularity);
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        ksort($buckets);

        return collect($buckets)
            ->reject(fn (int $count, string $period) => $granularity === 'day' && CompanyWorkCalendar::isHoliday($period))
            ->map(fn (int $count, string $period) => [
                'period' => $period,
                'label' => $this->periodLabel($period, $granularity),
                'count' => $count,
            ])->values()->all();
    }

    public function averageCallDurationSeconds(ReportFilter $filter): int
    {
        $durations = $filter->applyToVoipQuery(VoipCallLog::query())
            ->whereNotNull('duration')
            ->where('duration', '>', 0)
            ->pluck('duration');

        if ($durations->isEmpty()) {
            return 0;
        }

        return (int) round($durations->avg());
    }

    public function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }

        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $secs);
    }

    private function periodKey(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'week' => $date->format('Y-W'),
            default => CompanyWorkCalendar::dayKey($date),
        };
    }

    private function periodLabel(string $key, string $granularity): string
    {
        if ($granularity === 'week') {
            return 'هفته '.$key;
        }

        return JalaliDate::monthDay($key);
    }
}
