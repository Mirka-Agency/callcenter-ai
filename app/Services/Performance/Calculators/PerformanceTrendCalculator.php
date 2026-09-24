<?php

namespace App\Services\Performance\Calculators;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\DTOs\ReportFilter;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Support\CompanyWorkCalendar;
use App\Support\JalaliDate;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PerformanceTrendCalculator
{
    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return list<array{
     *     period: string,
     *     label: string,
     *     tooltip_label: string,
     *     tooltip_body: ?string,
     *     avg_score: float|null,
     *     count: int
     * }>
     */
    public function qualityTrend(ReportFilter $filter, Collection $analyses): array
    {
        $trend = $this->bucketAnalyses(
            $filter,
            $analyses,
            function (Collection $items) {
                $scored = $items->filter(fn (ConversationAnalysis $analysis) => $analysis->isEvaluable());

                return [
                    'avg_score' => $scored->isNotEmpty() ? round((float) $scored->avg('score'), 1) : null,
                    'count' => $items->count(),
                    'tooltip_body' => $scored->isNotEmpty() ? null : 'تحلیلی انجام نشد',
                ];
            },
        );

        return $this->omitFridays($filter, $trend);
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return list<array{period: string, label: string, tooltip_label: string, avg_score: float, count: int}>
     */
    public function leadTrend(ReportFilter $filter, Collection $analyses): array
    {
        return $this->bucketAnalyses($filter, $analyses, function (Collection $items) {
            $scores = $items->map(fn (ConversationAnalysis $a) => $a->lead_quality_json['score'] ?? null)->filter();

            return [
                'avg_score' => $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : 0.0,
                'count' => $items->count(),
            ];
        });
    }

    /**
     * @param  Collection<int, Call>  $calls
     * @return list<array{period: string, label: string, count: int}>
     */
    public function callVolumeTrend(ReportFilter $filter, Collection $calls): array
    {
        $granularity = $filter->granularity();
        $buckets = [];

        foreach ($calls as $call) {
            $date = $call->occurredAt();
            if (! $date) {
                continue;
            }

            $key = $this->periodKey($date, $granularity);
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        ksort($buckets);

        return collect($buckets)->map(fn (int $count, string $period) => [
            'period' => $period,
            'label' => $this->periodLabel($period, $granularity),
            'tooltip_label' => $this->periodTooltipLabel($period, $granularity),
            'count' => $count,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return list<array{period: string, label: string, positive: int, neutral: int, negative: int, mixed: int}>
     */
    public function sentimentTrend(ReportFilter $filter, Collection $analyses): array
    {
        $granularity = $filter->granularity();

        return $analyses
            ->filter(fn (ConversationAnalysis $a) => $a->occurredAt() !== null)
            ->groupBy(fn (ConversationAnalysis $a) => $this->periodKey($a->occurredAt(), $granularity))
            ->sortKeys()
            ->map(function (Collection $items, string $period) use ($granularity) {
                return [
                    'period' => $period,
                    'label' => $this->periodLabel($period, $granularity),
                    'tooltip_label' => $this->periodTooltipLabel($period, $granularity),
                    'positive' => $items->where('sentiment', AnalysisSentiment::Positive)->count(),
                    'neutral' => $items->where('sentiment', AnalysisSentiment::Neutral)->count(),
                    'negative' => $items->where('sentiment', AnalysisSentiment::Negative)->count(),
                    'mixed' => $items->where('sentiment', AnalysisSentiment::Mixed)->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return list<array{label: string, count: int}>
     */
    public function qualityDistribution(Collection $analyses): array
    {
        $buckets = [
            'عالی — ۸۰ به بالا' => 0,
            'خوب — ۶۰ تا ۷۹' => 0,
            'نیاز به بهبود — ۴۰ تا ۵۹' => 0,
            'ضعیف — زیر ۴۰' => 0,
        ];

        foreach ($analyses as $analysis) {
            $score = (int) ($analysis->score ?? 0);
            match (true) {
                $score >= 80 => $buckets['عالی — ۸۰ به بالا']++,
                $score >= 60 => $buckets['خوب — ۶۰ تا ۷۹']++,
                $score >= 40 => $buckets['نیاز به بهبود — ۴۰ تا ۵۹']++,
                default => $buckets['ضعیف — زیر ۴۰']++,
            };
        }

        return collect($buckets)->map(fn (int $count, string $label) => [
            'label' => $label,
            'count' => $count,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @param  callable(Collection<int, ConversationAnalysis>): array<string, mixed>  $aggregator
     * @return list<array<string, mixed>>
     */
    private function bucketAnalyses(ReportFilter $filter, Collection $analyses, callable $aggregator): array
    {
        $granularity = $filter->granularity();

        return $analyses
            ->filter(fn (ConversationAnalysis $a) => $a->occurredAt() !== null)
            ->groupBy(fn (ConversationAnalysis $a) => $this->periodKey($a->occurredAt(), $granularity))
            ->sortKeys()
            ->map(function (Collection $items, string $period) use ($granularity, $aggregator) {
                return array_merge([
                    'period' => $period,
                    'label' => $this->periodLabel($period, $granularity),
                    'tooltip_label' => $this->periodTooltipLabel($period, $granularity),
                ], $aggregator($items));
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return Collection<int, ConversationAnalysis>
     */
    public function analysesForPeriod(ReportFilter $filter, Collection $analyses, string $period): Collection
    {
        $granularity = $filter->granularity();

        if ($granularity === 'day' && $this->isFridayPeriod($period)) {
            return collect();
        }

        return $analyses
            ->filter(fn (ConversationAnalysis $analysis) => $analysis->occurredAt() !== null
                && $this->periodKey($analysis->occurredAt(), $granularity) === $period)
            ->values();
    }

    /**
     * Only Friday is a company holiday. Thursday is a working day and stays on the chart.
     *
     * @param  list<array<string, mixed>>  $trend
     * @return list<array<string, mixed>>
     */
    private function omitFridays(ReportFilter $filter, array $trend): array
    {
        if ($filter->granularity() !== 'day') {
            return $trend;
        }

        return collect($trend)
            ->reject(fn (array $row) => $this->isFridayPeriod((string) ($row['period'] ?? '')))
            ->values()
            ->all();
    }

    private function isFridayPeriod(string $period): bool
    {
        return CompanyWorkCalendar::isFriday($period);
    }

    /**
     * @param  list<array{period: string, label: string, avg_score?: float|null, count?: int}>  $trend
     * @return array{period: string, label: string, avg_score?: float|null, count?: int}|null
     */
    public function previousTrendRow(array $trend, string $period): ?array
    {
        $ordered = collect($trend)->sortBy('period')->values();
        $index = $ordered->search(fn (array $row) => ($row['period'] ?? null) === $period);

        if ($index === false || $index < 1) {
            return null;
        }

        for ($i = $index - 1; $i >= 0; $i--) {
            $row = $ordered[$i];

            if (($row['avg_score'] ?? null) === null) {
                continue;
            }

            return $row;
        }

        return null;
    }

    private function periodKey(CarbonInterface $date, string $granularity): string
    {
        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::instance($date);

        return match ($granularity) {
            'week' => $carbon->format('Y-W'),
            default => CompanyWorkCalendar::dayKey($carbon),
        };
    }

    private function periodLabel(string $key, string $granularity): string
    {
        if ($granularity === 'week') {
            return 'هفته '.$key;
        }

        return JalaliDate::monthDay($key);
    }

    private function periodTooltipLabel(string $key, string $granularity): string
    {
        if ($granularity === 'week') {
            return $this->periodLabel($key, $granularity);
        }

        return JalaliDate::monthDayWithWeekday($key);
    }
}
