<?php

namespace App\Services\Performance\Calculators;

use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Services\Reports\CallMetricsAnalytics;
use App\Support\JalaliDate;
use Illuminate\Support\Collection;

class EmployeeMetricsCalculator
{
    public function __construct(
        private SentimentScoreCalculator $sentimentCalculator,
        private CallMetricsAnalytics $callMetrics,
    ) {}

    /**
     * @param  Collection<int, Call>  $calls
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return array<string, mixed>
     */
    public function compute(Collection $calls, Collection $analyses): array
    {
        $analyzedCallIds = $analyses->pluck('call_id')->filter()->all();

        $answered = $calls->filter(
            fn (Call $call) => in_array($call->status, ['completed', 'answered'], true)
                || in_array($call->id, $analyzedCallIds, true),
        )->count();

        $missed = $calls->where('status', 'missed')->count();
        $durationSource = $calls->filter(
            fn (Call $call) => in_array($call->id, $analyzedCallIds, true)
                || (in_array($call->status, ['completed', 'answered'], true) && ($call->duration_seconds ?? 0) > 0),
        );
        $durations = $durationSource->pluck('duration_seconds')->filter(fn ($d) => $d > 0);
        if ($durations->isEmpty()) {
            $durations = $calls->pluck('duration_seconds')->filter(fn ($d) => $d > 0);
        }
        $avgDuration = $durations->isNotEmpty() ? (int) round($durations->avg()) : 0;

        $leadScores = $analyses
            ->map(fn (ConversationAnalysis $a) => $a->lead_quality_json['score'] ?? null)
            ->filter();

        $effectiveness = $analyses
            ->map(fn (ConversationAnalysis $a) => $this->effectivenessScore($a))
            ->filter();

        $firstActivity = $calls->min(fn (Call $c) => $c->started_at ?? $c->created_at)
            ?? $analyses->min('analyzed_at');
        $lastActivity = $calls->max(fn (Call $c) => $c->started_at ?? $c->created_at)
            ?? $analyses->max('analyzed_at');

        return [
            'total_calls' => $calls->count(),
            'answered_calls' => $answered,
            'missed_calls' => $missed,
            'average_duration_seconds' => $avgDuration,
            'average_duration_label' => $this->callMetrics->formatDuration($avgDuration),
            'total_analyzed' => $analyses->count(),
            'first_activity_at' => JalaliDate::date($firstActivity),
            'last_activity_at' => JalaliDate::date($lastActivity),
            'average_quality_score' => round((float) $analyses->avg('score'), 1),
            'average_lead_score' => $leadScores->isNotEmpty() ? round((float) $leadScores->avg(), 1) : 0.0,
            'average_sentiment' => $this->sentimentCalculator->average($analyses),
            'effectiveness_score' => $effectiveness->isNotEmpty() ? round((float) $effectiveness->avg(), 1) : 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return array<string, mixed>
     */
    public function deltas(array $current, array $previous): array
    {
        $qualityDelta = $this->delta($current['average_quality_score'], $previous['average_quality_score']);
        $leadDelta = $this->delta($current['average_lead_score'], $previous['average_lead_score']);
        $sentimentDelta = $this->delta($current['average_sentiment'], $previous['average_sentiment']);

        return [
            'quality_improvement_percent' => $qualityDelta,
            'lead_improvement_percent' => $leadDelta,
            'sentiment_improvement_percent' => $sentimentDelta,
            'quality_trend' => $this->trendLabel($qualityDelta),
            'lead_trend' => $this->trendLabel($leadDelta),
            'sentiment_trend' => $this->trendLabel($sentimentDelta),
        ];
    }

    private function effectivenessScore(ConversationAnalysis $analysis): ?float
    {
        $dimensions = $analysis->performance_dimensions_json ?? [];
        if ($dimensions !== []) {
            $scores = collect($dimensions)->pluck('score')->filter();
            if ($scores->isNotEmpty()) {
                return (float) $scores->avg();
            }
        }

        return $analysis->score ? (float) $analysis->score : null;
    }

    private function delta(float|int|null $current, float|int|null $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function trendLabel(?float $delta): string
    {
        return match (true) {
            $delta === null => 'stable',
            $delta > 3 => 'improving',
            $delta < -3 => 'declining',
            default => 'stable',
        };
    }
}
