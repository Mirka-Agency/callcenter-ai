<?php

namespace App\Services\PlatformAnalytics;

use App\Models\NpsResponse;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Net promoter score: share of promoters (9-10) minus share of detractors (0-6), from -100 to 100.
 * Skipped surveys (no score) only count toward the response rate.
 */
class NpsMetrics
{
    public const WINDOW_DAYS = 90;

    /**
     * @return array{score: int|null, previous_score: int|null, responses: int, promoters: int, passives: int, detractors: int, skipped: int, response_rate: float|null}
     */
    public function summary(?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now())->addSecond();
        $start = $now->copy()->subDays(self::WINDOW_DAYS);

        $current = $this->breakdown($start, $now);
        $previous = $this->breakdown($start->copy()->subDays(self::WINDOW_DAYS), $start);
        $asked = $current['responses'] + $current['skipped'];

        return [
            ...$current,
            'previous_score' => $previous['score'],
            'response_rate' => $asked === 0 ? null : $current['responses'] / $asked,
        ];
    }

    /** @return array{score: int|null, responses: int, promoters: int, passives: int, detractors: int, skipped: int} */
    public function breakdown(CarbonInterface $start, CarbonInterface $end): array
    {
        $scores = NpsResponse::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->pluck('score');

        $answered = $scores->filter(fn (?int $score): bool => $score !== null);
        $promoters = $answered->filter(fn (int $score): bool => $score >= 9)->count();
        $detractors = $answered->filter(fn (int $score): bool => $score <= 6)->count();
        $responses = $answered->count();

        return [
            'score' => $responses === 0 ? null : (int) round(($promoters - $detractors) / $responses * 100),
            'responses' => $responses,
            'promoters' => $promoters,
            'passives' => $responses - $promoters - $detractors,
            'detractors' => $detractors,
            'skipped' => $scores->count() - $responses,
        ];
    }

    public static function category(?int $score): ?string
    {
        return match (true) {
            $score === null => null,
            $score >= 9 => 'promoter',
            $score >= 7 => 'passive',
            default => 'detractor',
        };
    }
}
