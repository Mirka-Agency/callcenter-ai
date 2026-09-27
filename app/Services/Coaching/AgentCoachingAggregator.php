<?php

namespace App\Services\Coaching;

use App\Domain\Coaching\CoachingCatalog;
use App\DTOs\ReportFilter;
use App\Models\ConversationAnalysis;
use App\Services\Performance\Calculators\PerformanceTrendCalculator;
use App\Support\JalaliDate;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Agent coaching scores are calculated here, not by the model.
 *
 * A call counts only when it is evaluable and has at least one valid skill
 * score. A missing skill is ignored. It is never treated as zero.
 *
 * Skill score = confidence-weighted average of that skill across counted calls.
 * Weight is the call confidence when it is between 0 and 1. A missing
 * confidence weighs 1. A confidence of 0 is untrusted and is skipped.
 *
 * Overall score = unweighted mean of the skill scores, so one frequently
 * observed skill does not outweigh the others.
 *
 * Status comes only from config/coaching.php bands applied to the rounded score.
 *
 * Previous-period change is shown only when both periods meet the minimum
 * call count. Otherwise the result stays null.
 */
class AgentCoachingAggregator
{
    /** @var list<string> */
    private const TREND_COLORS = [
        'rgb(99, 102, 241)',
        'rgb(245, 158, 11)',
        'rgb(16, 185, 129)',
        'rgb(244, 63, 94)',
    ];

    public function __construct(private PerformanceTrendCalculator $trends) {}

    /**
     * @param  Collection<int, ConversationAnalysis>  $current
     * @param  Collection<int, ConversationAnalysis>  $previous
     * @return array<string, mixed>
     */
    public function aggregate(ReportFilter $filter, Collection $current, Collection $previous): array
    {
        $included = $this->included($current);
        $count = $included->count();
        $minimum = CoachingCatalog::minimumCalls();
        $evaluableCount = $current->filter(fn (ConversationAnalysis $analysis) => $analysis->isEvaluable())->count();

        if ($count === 0) {
            return $this->unavailable('empty', $count, $minimum, $evaluableCount);
        }

        if ($count < $minimum) {
            return $this->unavailable('insufficient', $count, $minimum, $evaluableCount);
        }

        $this->logMissingCalls($included);

        $skillRows = $this->skillRows($included);
        $previousIncluded = $this->included($previous);
        $previousSkills = $previousIncluded->count() >= $minimum
            ? $this->skillRows($previousIncluded)
            : [];

        $overall = $this->mean(array_column($skillRows, 'score'));
        $previousOverall = $previousSkills === []
            ? null
            : $this->mean(array_column($previousSkills, 'score'));

        $skills = [];
        foreach ($skillRows as $row) {
            $previousScore = $previousSkills[$row['skill_key']]['score'] ?? null;
            $row['delta'] = $previousScore === null ? null : round($row['score'] - $previousScore, 1);
            $skills[] = $row;
        }

        $weak = array_values(array_filter(
            $skills,
            fn (array $skill) => CoachingCatalog::isWeakStatus($skill['status']),
        ));
        usort($weak, function (array $left, array $right): int {
            $severity = $this->severityRank($right['severity']) <=> $this->severityRank($left['severity']);

            return $severity !== 0 ? $severity : ($left['score'] <=> $right['score']);
        });

        $strong = array_values(array_filter(
            $skills,
            fn (array $skill) => $skill['status'] === CoachingCatalog::STATUS_STRENGTH,
        ));
        usort($strong, fn (array $left, array $right) => $right['score'] <=> $left['score']);
        $strong = array_slice($strong, 0, CoachingCatalog::strengthLimit());

        return [
            'status' => 'ready',
            'title' => null,
            'description' => null,
            'overall_score' => $overall,
            'previous_period_score' => $previousOverall,
            'score_change' => $previousOverall === null || $overall === null ? null : round($overall - $previousOverall, 1),
            'score_change_label' => $this->changeLabel(
                $previousOverall === null || $overall === null ? null : round($overall - $previousOverall, 1),
            ),
            'analyzed_calls' => $count,
            'evaluable_calls' => $evaluableCount,
            'minimum_calls' => $minimum,
            'coverage_note' => $count < $evaluableCount
                ? $count.' تماس دارای ارزیابی مهارت؛ تماس‌های بدون این ارزیابی در امتیاز حساب نشده‌اند.'
                : null,
            'skills_needing_improvement' => count($weak),
            'strong_skill_count' => count(array_filter(
                $skills,
                fn (array $skill) => $skill['status'] === CoachingCatalog::STATUS_STRENGTH,
            )),
            'skills' => array_map(function (array $skill): array {
                unset($skill['observations']);

                return $skill;
            }, $skills),
            'strengths' => array_map(fn (array $skill) => $this->strengthCard($skill), $strong),
            'areas_for_improvement' => array_map(fn (array $skill) => $this->improvementCard($skill), $weak),
            'recommendations' => $this->recommendations($weak),
            'evidence' => $this->evidence($weak),
            'trend' => $this->trend($filter, $included, $weak !== [] ? $weak : $skills),
            'last_analyzed_at' => $this->lastAnalyzedAt($included),
        ];
    }

    /** @return array<string, mixed> */
    public function errorPayload(): array
    {
        return $this->unavailable('error', 0, CoachingCatalog::minimumCalls(), 0);
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return Collection<int, ConversationAnalysis>
     */
    private function included(Collection $analyses): Collection
    {
        return $analyses
            ->filter(function (ConversationAnalysis $analysis): bool {
                if (! $analysis->isEvaluable()) {
                    return false;
                }

                foreach (CoachingCatalog::skillKeys() as $key) {
                    if ($this->observations(collect([$analysis]), $key) !== []) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return array<string, array<string, mixed>>
     */
    private function skillRows(Collection $analyses): array
    {
        $rows = [];

        foreach (CoachingCatalog::skills() as $skill) {
            $observations = $this->observations($analyses, $skill['key']);
            $score = $this->weightedAverage($observations);

            if ($score === null) {
                continue;
            }

            $weakCalls = 0;
            foreach ($observations as $observation) {
                if (CoachingCatalog::isWeakStatus(CoachingCatalog::statusFor($observation['score']))) {
                    $weakCalls++;
                }
            }

            $status = CoachingCatalog::statusFor($score);
            $rows[$skill['key']] = [
                'skill_key' => $skill['key'],
                'label' => $skill['label'],
                'score' => $score,
                'status' => $status,
                'status_label' => CoachingCatalog::statusLabel($status),
                'observed_calls' => count($observations),
                'weak_calls' => $weakCalls,
                'severity' => $this->severity($status, $weakCalls, count($observations)),
                'observations' => $observations,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return list<array{score: float, confidence: ?float, analysis: ConversationAnalysis, skill: array<string, mixed>}>
     */
    private function observations(Collection $analyses, string $skillKey): array
    {
        $rows = [];

        foreach ($analyses as $analysis) {
            if (! $analysis->isEvaluable()) {
                continue;
            }

            $skills = $analysis->coaching_analysis_json['skills'] ?? null;
            if (! is_array($skills)) {
                continue;
            }

            foreach ($skills as $skill) {
                if (! is_array($skill) || ($skill['skill_key'] ?? null) !== $skillKey) {
                    continue;
                }

                if (! is_numeric($skill['score'] ?? null)) {
                    continue;
                }

                $score = (float) $skill['score'];
                if ($score < 0 || $score > 100) {
                    continue;
                }

                $confidence = $skill['confidence'] ?? null;
                if ($confidence !== null) {
                    if (! is_numeric($confidence)) {
                        continue;
                    }

                    $confidence = (float) $confidence;
                    if ($confidence <= 0 || $confidence > 1) {
                        continue;
                    }
                }

                $rows[] = [
                    'score' => $score,
                    'confidence' => $confidence,
                    'analysis' => $analysis,
                    'skill' => $skill,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{score: float, confidence: ?float}>  $observations
     */
    private function weightedAverage(array $observations): ?float
    {
        $weight = 0.0;
        $total = 0.0;

        foreach ($observations as $observation) {
            $itemWeight = $observation['confidence'] ?? 1.0;
            $total += $observation['score'] * $itemWeight;
            $weight += $itemWeight;
        }

        if ($weight <= 0) {
            return null;
        }

        return round($total / $weight, 1);
    }

    /** @param  list<float>  $scores */
    private function mean(array $scores): ?float
    {
        if ($scores === []) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 1);
    }

    private function severity(string $status, int $weakCalls, int $observed): string
    {
        if ($status === CoachingCatalog::STATUS_CRITICAL) {
            return 'high';
        }

        if ($status !== CoachingCatalog::STATUS_NEEDS_IMPROVEMENT) {
            return 'low';
        }

        $ratio = $observed > 0 ? $weakCalls / $observed : 0;

        return $ratio >= CoachingCatalog::highSeverityWeakRatio() ? 'high' : 'medium';
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    /** @param  array<string, mixed>  $skill */
    private function strengthCard(array $skill): array
    {
        $source = $this->preferredObservation($skill['observations'], strongest: true);

        return [
            'skill_key' => $skill['skill_key'],
            'title' => $skill['label'],
            'score' => $skill['score'],
            'status_label' => $skill['status_label'],
            'explanation' => $this->narrative($source, 'feedback'),
            'evidence' => $source === null ? [] : $this->evidenceItems([$source]),
        ];
    }

    /** @param  array<string, mixed>  $skill */
    private function improvementCard(array $skill): array
    {
        $source = $this->preferredObservation($skill['observations'], strongest: false);
        $explanation = $this->narrative($source, 'feedback');
        $frequency = 'در '.$skill['weak_calls'].' از '.$skill['observed_calls'].' تماس تحلیل‌شده، این مهارت پایین‌تر از حد خوب بوده است.';

        return [
            'skill_key' => $skill['skill_key'],
            'title' => $skill['label'],
            'score' => $skill['score'],
            'status_label' => $skill['status_label'],
            'severity' => $skill['severity'],
            'severity_label' => $this->severityLabel($skill['severity']),
            'explanation' => $explanation,
            'frequency' => $frequency,
            'recommendation' => $this->narrative($source, 'recommendation'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $weak
     * @return list<array<string, mixed>>
     */
    private function recommendations(array $weak): array
    {
        $items = [];

        foreach (array_slice($weak, 0, CoachingCatalog::recommendationLimit()) as $index => $skill) {
            $source = $this->preferredObservation($skill['observations'], strongest: false);
            $action = $this->narrative($source, 'recommendation');

            if ($action === '') {
                continue;
            }

            $items[] = [
                'priority' => $index + 1,
                'level' => $skill['severity'],
                'level_label' => $this->severityLabel($skill['severity']),
                'skill_key' => $skill['skill_key'],
                'title' => $skill['label'],
                'suggested_action' => $action,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $weak
     * @return list<array<string, mixed>>
     */
    private function evidence(array $weak): array
    {
        $observations = [];

        foreach ($weak as $skill) {
            foreach ($skill['observations'] as $observation) {
                if (CoachingCatalog::isWeakStatus(CoachingCatalog::statusFor($observation['score']))) {
                    $observations[] = $observation;
                }
            }
        }

        usort($observations, function (array $left, array $right): int {
            return ($right['analysis']->analyzed_at?->getTimestamp() ?? 0)
                <=> ($left['analysis']->analyzed_at?->getTimestamp() ?? 0);
        });

        return array_slice($this->evidenceItems($observations), 0, CoachingCatalog::evidenceLimit());
    }

    /**
     * @param  list<array{analysis: ConversationAnalysis, skill: array<string, mixed>}>  $observations
     * @return list<array<string, mixed>>
     */
    private function evidenceItems(array $observations): array
    {
        $items = [];

        foreach ($observations as $observation) {
            $analysis = $observation['analysis'];
            $pieces = is_array($observation['skill']['evidence'] ?? null) ? $observation['skill']['evidence'] : [];

            if ($pieces === []) {
                continue;
            }

            foreach ($pieces as $piece) {
                if (! is_array($piece)) {
                    continue;
                }

                $description = trim((string) ($piece['description'] ?? ''));
                $quote = trim((string) ($piece['quote_or_summary'] ?? ''));

                if ($description === '' && $quote === '') {
                    continue;
                }

                $items[] = [
                    'analysis_id' => $analysis->id,
                    'call_label' => $analysis->call_id ? 'تماس #'.$analysis->call_id : 'تحلیل #'.$analysis->id,
                    'time_label' => $this->timeLabel($piece['start_time'] ?? null, $piece['end_time'] ?? null),
                    'skill_label' => CoachingCatalog::label((string) ($observation['skill']['skill_key'] ?? '')),
                    'description' => $description,
                    'quote' => $quote,
                ];
            }
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $rankedSkills
     * @return array{has_data: bool, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function trend(ReportFilter $filter, Collection $analyses, array $rankedSkills): array
    {
        $empty = ['has_data' => false, 'labels' => [], 'datasets' => []];
        $selected = array_slice($rankedSkills, 0, CoachingCatalog::trendSkillLimit());

        if ($selected === []) {
            return $empty;
        }

        $keys = array_column($selected, 'skill_key');
        $buckets = $this->trends->mapByPeriod($filter, $analyses, function (Collection $items) use ($keys): array {
            $scores = [];
            foreach ($keys as $key) {
                $scores[$key] = $this->weightedAverage($this->observations($items, $key));
            }

            return ['skill_scores' => $scores];
        });

        if ($buckets === []) {
            return $empty;
        }

        $labels = array_column($buckets, 'label');
        $datasets = [];
        $populated = 0;

        foreach ($selected as $index => $skill) {
            $data = [];
            $points = 0;

            foreach ($buckets as $bucket) {
                $value = $bucket['skill_scores'][$skill['skill_key']] ?? null;
                $data[] = $value;
                if ($value !== null) {
                    $points++;
                }
            }

            $populated = max($populated, $points);
            $datasets[] = [
                'label' => $skill['label'],
                'data' => $data,
                'borderColor' => self::TREND_COLORS[$index % count(self::TREND_COLORS)],
                'backgroundColor' => 'transparent',
                'fill' => false,
                'tension' => 0.35,
                'spanGaps' => true,
            ];
        }

        if ($populated < CoachingCatalog::minimumTrendPoints()) {
            return $empty;
        }

        return [
            'has_data' => true,
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * @param  list<array{score: float, confidence: ?float, skill: array<string, mixed>}>  $observations
     * @return array{score: float, confidence: ?float, skill: array<string, mixed>}|null
     */
    private function preferredObservation(array $observations, bool $strongest): ?array
    {
        $withText = array_values(array_filter($observations, function (array $observation): bool {
            $skill = $observation['skill'];

            return trim((string) ($skill['feedback'] ?? '')) !== ''
                || trim((string) ($skill['recommendation'] ?? '')) !== '';
        }));

        $pool = $withText !== [] ? $withText : $observations;

        if ($pool === []) {
            return null;
        }

        usort($pool, function (array $left, array $right) use ($strongest): int {
            return $strongest
                ? $right['score'] <=> $left['score']
                : $left['score'] <=> $right['score'];
        });

        return $pool[0];
    }

    /** @param  array{skill: array<string, mixed>}|null  $observation */
    private function narrative(?array $observation, string $field): string
    {
        if ($observation === null) {
            return '';
        }

        return trim((string) ($observation['skill'][$field] ?? ''));
    }

    /** @param  Collection<int, ConversationAnalysis>  $analyses */
    private function lastAnalyzedAt(Collection $analyses): ?string
    {
        $latest = $analyses
            ->map(fn (ConversationAnalysis $analysis) => $analysis->analyzed_at)
            ->filter()
            ->sort()
            ->last();

        return $latest instanceof CarbonInterface ? JalaliDate::datetime($latest) : null;
    }

    /** @param  Collection<int, ConversationAnalysis>  $analyses */
    private function logMissingCalls(Collection $analyses): void
    {
        $missing = $analyses
            ->filter(fn (ConversationAnalysis $analysis) => $analysis->call_id === null)
            ->count();

        if ($missing === 0) {
            return;
        }

        try {
            Log::warning('coaching_missing_call_relationship', ['analyses' => $missing]);
        } catch (\Throwable) {
            // Aggregation unit tests may run before the logger is available.
        }
    }

    private function timeLabel(mixed $start, mixed $end): ?string
    {
        $startLabel = $this->clock($start);
        $endLabel = $this->clock($end);

        if ($startLabel === null) {
            return $endLabel;
        }

        if ($endLabel === null || $endLabel === $startLabel) {
            return $startLabel;
        }

        return $startLabel.' تا '.$endLabel;
    }

    private function clock(mixed $seconds): ?string
    {
        if (! is_numeric($seconds)) {
            return null;
        }

        $seconds = max(0, (int) $seconds);
        $minutes = intdiv($seconds, 60);
        $remain = $seconds % 60;

        if ($minutes >= 60) {
            return sprintf('%d:%02d:%02d', intdiv($minutes, 60), $minutes % 60, $remain);
        }

        return sprintf('%02d:%02d', $minutes, $remain);
    }

    private function severityLabel(string $severity): string
    {
        return match ($severity) {
            'high' => 'زیاد',
            'low' => 'کم',
            default => 'متوسط',
        };
    }

    private function changeLabel(?float $change): ?string
    {
        if ($change === null) {
            return null;
        }

        $prefix = $change > 0 ? '+' : '';

        return $prefix.$change.' نسبت به دوره قبل';
    }

    /** @return array<string, mixed> */
    private function unavailable(string $status, int $count, int $minimum, int $evaluableCount): array
    {
        [$title, $description] = match ($status) {
            'insufficient' => [
                __('ui.empty.agent_coaching.insufficient_title'),
                __('ui.empty.agent_coaching.insufficient_progress', [
                    'count' => $count,
                    'minimum' => $minimum,
                ]),
            ],
            'error' => [
                __('ui.empty.agent_coaching.error_title'),
                __('ui.empty.agent_coaching.error_description'),
            ],
            default => [
                __('ui.empty.agent_coaching.empty_title'),
                __('ui.empty.agent_coaching.empty_description'),
            ],
        };

        return [
            'status' => $status,
            'title' => $title,
            'description' => $description,
            'overall_score' => null,
            'previous_period_score' => null,
            'score_change' => null,
            'score_change_label' => null,
            'analyzed_calls' => $count,
            'evaluable_calls' => $evaluableCount,
            'minimum_calls' => $minimum,
            'coverage_note' => null,
            'skills_needing_improvement' => 0,
            'strong_skill_count' => 0,
            'skills' => [],
            'strengths' => [],
            'areas_for_improvement' => [],
            'recommendations' => [],
            'evidence' => [],
            'trend' => ['has_data' => false, 'labels' => [], 'datasets' => []],
            'last_analyzed_at' => null,
        ];
    }
}
