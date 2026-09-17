<?php

namespace App\Services\Performance\Calculators;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\DTOs\ReportFilter;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Support\JalaliDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PerformanceTrendCalculator
{
    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return list<array{period: string, label: string, avg_score: float, count: int}>
     */
    public function qualityTrend(ReportFilter $filter, Collection $analyses): array
    {
        return $this->bucketAnalyses($filter, $analyses, function (Collection $items) {
            $scored = $items->filter(fn (ConversationAnalysis $analysis) => $analysis->isEvaluable());

            return [
                'avg_score' => $scored->isNotEmpty() ? round((float) $scored->avg('score'), 1) : 0.0,
                'count' => $items->count(),
            ];
        });
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return list<array{period: string, label: string, avg_score: float, count: int}>
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
            $date = $call->started_at ?? $call->created_at;
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
            ->filter(fn (ConversationAnalysis $a) => $a->analyzed_at !== null)
            ->groupBy(fn (ConversationAnalysis $a) => $this->periodKey($a->analyzed_at, $granularity))
            ->sortKeys()
            ->map(function (Collection $items, string $period) use ($granularity) {
                return [
                    'period' => $period,
                    'label' => $this->periodLabel($period, $granularity),
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
            ->filter(fn (ConversationAnalysis $a) => $a->analyzed_at !== null)
            ->groupBy(fn (ConversationAnalysis $a) => $this->periodKey($a->analyzed_at, $granularity))
            ->sortKeys()
            ->map(function (Collection $items, string $period) use ($granularity, $aggregator) {
                return array_merge([
                    'period' => $period,
                    'label' => $this->periodLabel($period, $granularity),
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

        return $analyses
            ->filter(fn (ConversationAnalysis $analysis) => $analysis->analyzed_at !== null
                && $this->periodKey($analysis->analyzed_at, $granularity) === $period)
            ->values();
    }

    /**
     * @param  list<array{period: string, label: string, avg_score?: float, count?: int}>  $trend
     * @return array{period: string, label: string, avg_score?: float, count?: int}|null
     */
    public function previousTrendRow(array $trend, string $period): ?array
    {
        $ordered = collect($trend)->sortBy('period')->values();
        $index = $ordered->search(fn (array $row) => ($row['period'] ?? null) === $period);

        if ($index === false || $index < 1) {
            return null;
        }

        return $ordered[$index - 1];
    }

    private function periodKey(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'week' => $date->format('Y-W'),
            default => $date->format('Y-m-d'),
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
