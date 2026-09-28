<?php

namespace App\Services\Reports;

use App\Domain\Call\Enums\ConversationSource;
use App\DTOs\ReportFilter;
use App\Models\Call;
use App\Models\VoipCallLog;
use App\Support\CompanyWorkCalendar;
use App\Support\JalaliDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CallMetricsAnalytics
{
    public function totalCalls(ReportFilter $filter): int
    {
        $voip = app(DefinedExtensionCallConstraint::class)->applyToVoipLogs(
            $filter->applyToVoipQuery(VoipCallLog::query()),
            $filter->organizationId,
        )->count();

        $manualQuery = app(DefinedExtensionCallConstraint::class)->applyToQueueCalls(
            Call::query()
                ->where('organization_id', $filter->organizationId)
                ->where('source', ConversationSource::ManualUpload->value)
                ->whereBetween('created_at', [$filter->from, $filter->to]),
            $filter->organizationId,
        );

        return $voip + $manualQuery->count();
    }

    /** @return list<array{period: string, label: string, count: int}> */
    public function callActivityTrend(ReportFilter $filter): array
    {
        $granularity = $filter->granularity();
        $buckets = $granularity === 'day'
            ? $this->dailyVoipCounts($filter)
            : $this->weeklyVoipCounts($filter);

        ksort($buckets);

        $closedDays = app(ChartHolidayCalendar::class)->forRange($filter->organizationId, $filter->from, $filter->to);

        return collect($buckets)
            ->reject(fn (int $count, string $period) => $granularity === 'day' && $closedDays->hides($period))
            ->map(fn (int $count, string $period) => [
                'period' => $period,
                'label' => $this->periodLabel($period, $granularity),
                'count' => $count,
            ])->values()->all();
    }

    public function averageCallDurationSeconds(ReportFilter $filter): int
    {
        $average = $filter->applyToVoipQuery(VoipCallLog::query())
            ->whereNotNull('duration')
            ->where('duration', '>', 0)
            ->avg('duration');

        return $average === null ? 0 : (int) round((float) $average);
    }

    /** @return array<string, int> */
    private function dailyVoipCounts(ReportFilter $filter): array
    {
        $moment = 'COALESCE(voip_call_logs.started_at, voip_call_logs.created_at)';
        $day = CompanyWorkCalendar::sqlDayKey($moment, DB::connection()->getDriverName());
        $query = $filter->applyToVoipQuery(VoipCallLog::query())
            ->whereRaw($moment.' IS NOT NULL');
        $base = $query->toBase();
        $base->columns = [];

        $buckets = [];

        foreach ($base->selectRaw($day.' as day_key')->selectRaw('COUNT(*) as aggregate')->groupByRaw($day)->get() as $row) {
            $key = substr((string) $row->day_key, 0, 10);

            if ($key === '') {
                continue;
            }

            $buckets[$key] = (int) $row->aggregate;
        }

        return $buckets;
    }

    /** @return array<string, int> */
    private function weeklyVoipCounts(ReportFilter $filter): array
    {
        $buckets = [];

        foreach ($filter->applyToVoipQuery(VoipCallLog::query())->get(['started_at', 'created_at']) as $call) {
            $occurredAt = $call->started_at ?? $call->created_at;

            if ($occurredAt === null) {
                continue;
            }

            $key = $this->periodKey($occurredAt, 'week');
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        return $buckets;
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
