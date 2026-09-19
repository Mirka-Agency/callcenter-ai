<?php

namespace App\Support;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\ConversationAnalysis;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Collection;

class CustomerNextActionAggregator
{
    public const LIMIT = 5;

    /**
     * Rank unique next actions so the profile only shows the most urgent ones.
     *
     * @param  Collection<int, ConversationAnalysis>|iterable<ConversationAnalysis>  $analyses
     * @return list<string>
     */
    public static function prioritized(iterable $analyses, int $limit = self::LIMIT): array
    {
        $scores = [];
        $labels = [];
        $order = [];

        foreach ($analyses as $analysis) {
            if (! $analysis instanceof ConversationAnalysis) {
                continue;
            }

            $contextScore = self::analysisContextScore($analysis);

            foreach (self::actionsFromAnalysis($analysis) as $index => $action) {
                $text = $action['text'];

                if (! isset($labels[$text])) {
                    $labels[$text] = $text;
                    $order[$text] = count($order);
                    $scores[$text] = 0;
                }

                $scores[$text] += $contextScore
                    + self::actionPriorityScore($action['raw'], $text, $analysis, $index);
            }
        }

        if ($scores === []) {
            return [];
        }

        uksort($scores, function (string $left, string $right) use ($scores, $order): int {
            return $scores[$right] <=> $scores[$left]
                ?: $order[$left] <=> $order[$right];
        });

        $ranked = [];
        foreach (array_keys($scores) as $text) {
            $ranked[] = $labels[$text];
        }

        return array_slice($ranked, 0, $limit);
    }

    /**
     * @return list<array{text: string, raw: mixed}>
     */
    private static function actionsFromAnalysis(ConversationAnalysis $analysis): array
    {
        $seen = [];
        $actions = [];

        foreach ([
            $analysis->next_actions_json ?? [],
            $analysis->operational_insights_json['follow_up_suggestions'] ?? [],
        ] as $items) {
            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $text = self::actionText($item);

                if ($text === null || isset($seen[$text])) {
                    continue;
                }

                $seen[$text] = true;
                $actions[] = ['text' => $text, 'raw' => $item];
            }
        }

        return $actions;
    }

    private static function analysisContextScore(ConversationAnalysis $analysis): int
    {
        $score = self::recencyScore($analysis->analyzed_at);

        if ($analysis->needs_attention) {
            $score += 24;
        }

        $insights = is_array($analysis->customer_insights_json) ? $analysis->customer_insights_json : [];
        $score += self::levelScore($insights['urgency_level'] ?? null);
        $score += (int) round(self::levelScore($insights['risk_level'] ?? null) * 0.8);

        if ($analysis->sentiment === AnalysisSentiment::Negative) {
            $score += 12;
        }

        $operational = is_array($analysis->operational_insights_json) ? $analysis->operational_insights_json : [];
        if (! empty($operational['escalation_risks'])) {
            $score += 18;
        }

        foreach ($analysis->concerns_json ?? [] as $concern) {
            if (! is_array($concern)) {
                continue;
            }

            if (mb_strtolower((string) ($concern['severity'] ?? '')) === 'high') {
                $score += 12;
                break;
            }
        }

        return $score;
    }

    private static function actionPriorityScore(
        mixed $raw,
        string $text,
        ConversationAnalysis $analysis,
        int $position,
    ): int {
        $score = max(0, 8 - $position);

        if (is_array($raw)) {
            $score += self::levelScore(
                $raw['priority'] ?? $raw['urgency'] ?? $raw['severity'] ?? $raw['urgency_level'] ?? null,
            );
        }

        $score += self::keywordScore($text);
        $score += self::dueDateScore($text, $analysis->analyzed_at);

        return $score;
    }

    private static function recencyScore(mixed $analyzedAt): int
    {
        if ($analyzedAt === null) {
            return 2;
        }

        $days = (int) Carbon::parse($analyzedAt)->startOfDay()->diffInDays(Carbon::today());

        return match (true) {
            $days <= 1 => 18,
            $days <= 7 => 12,
            $days <= 30 => 6,
            default => 2,
        };
    }

    private static function dueDateScore(string $text, mixed $analyzedAt): int
    {
        if (! $analyzedAt instanceof DateTimeInterface) {
            return 0;
        }

        $from = $analyzedAt instanceof CarbonInterface
            ? $analyzedAt
            : Carbon::parse($analyzedAt);
        $due = FollowUpDueDateParser::parse($text, $from);

        if ($due === null && self::containsAny($text, ['اضطراری', 'حیاتی', 'فوراً', 'فورا', 'فوری'])) {
            $due = $from->copy()->startOfDay();
        }

        if ($due === null) {
            return 0;
        }

        $dueDay = $due->copy()->startOfDay();
        $today = Carbon::today();

        if ($dueDay->lt($today)) {
            return 32 + min(16, (int) $dueDay->diffInDays($today));
        }

        if ($dueDay->isSameDay($today)) {
            return 22;
        }

        if ((int) $today->diffInDays($dueDay) <= 2) {
            return 14;
        }

        return 0;
    }

    private static function keywordScore(string $text): int
    {
        if (self::containsAny($text, ['اضطراری', 'حیاتی', 'فوراً', 'فورا', 'فوری'])) {
            return 28;
        }

        if (self::containsAny($text, ['امروز ساعت', 'همین روز', 'همین امروز', 'امروز'])) {
            return 14;
        }

        return 0;
    }

    private static function levelScore(mixed $level): int
    {
        $normalized = mb_strtolower(trim((string) $level));

        return match ($normalized) {
            'critical', 'بحرانی' => 36,
            'high', 'بالا', 'urgent', 'فوری' => 24,
            'medium', 'متوسط' => 8,
            default => 0,
        };
    }

    private static function actionText(mixed $item): ?string
    {
        if (is_string($item)) {
            $text = trim($item);

            return $text !== '' ? $text : null;
        }

        if (! is_array($item)) {
            return null;
        }

        foreach (['action', 'title', 'text'] as $key) {
            if (! empty($item[$key]) && is_string($item[$key])) {
                $text = trim($item[$key]);

                if ($text !== '') {
                    return $text;
                }
            }
        }

        return null;
    }

    /** @param  list<string>  $needles */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
