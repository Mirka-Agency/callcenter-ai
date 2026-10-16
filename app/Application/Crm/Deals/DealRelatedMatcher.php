<?php

namespace App\Application\Crm\Deals;

use App\Models\ConversationAnalysis;
use App\Models\CrmDeal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Ranks open deals for a customer against analysis evidence.
 * Never auto-merges deals; only ranks candidates for link/review.
 */
class DealRelatedMatcher
{
    public const AUTO_LINK_MIN_SCORE = 45;

    /**
     * @param  Collection<int, CrmDeal>  $openDeals
     * @return list<array{deal_id: int, score: int, reasons: list<string>}>
     */
    public function rank(Collection $openDeals, ConversationAnalysis $analysis): array
    {
        $tokens = $this->tokensFromAnalysis($analysis);
        $ranked = [];

        foreach ($openDeals as $deal) {
            $score = 10; // same customer open deal base
            $reasons = ['same_customer_open_deal'];
            $titleTokens = $this->tokenize((string) $deal->title);
            $overlap = count(array_intersect($tokens, $titleTokens));

            if ($overlap > 0) {
                $score += min(40, $overlap * 12);
                $reasons[] = 'title_keyword_overlap';
            }

            if ($deal->description) {
                $descOverlap = count(array_intersect($tokens, $this->tokenize((string) $deal->description)));
                if ($descOverlap > 0) {
                    $score += min(20, $descOverlap * 6);
                    $reasons[] = 'description_keyword_overlap';
                }
            }

            if ($deal->last_activity_at && $deal->last_activity_at->gt(now()->subDays(14))) {
                $score += 10;
                $reasons[] = 'recent_activity';
            }

            $ranked[] = [
                'deal_id' => (int) $deal->id,
                'score' => $score,
                'reasons' => $reasons,
            ];
        }

        usort($ranked, fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: $a['deal_id'] <=> $b['deal_id']);

        return $ranked;
    }

    /**
     * @param  Collection<int, CrmDeal>  $openDeals
     */
    public function bestClearMatch(Collection $openDeals, ConversationAnalysis $analysis): ?CrmDeal
    {
        if ($openDeals->count() === 1) {
            return $openDeals->first();
        }

        if ($openDeals->isEmpty()) {
            return null;
        }

        $ranked = $this->rank($openDeals, $analysis);
        $top = $ranked[0] ?? null;
        $second = $ranked[1] ?? null;

        if ($top === null) {
            return null;
        }

        $gap = $top['score'] - ($second['score'] ?? 0);

        if ($top['score'] < self::AUTO_LINK_MIN_SCORE || $gap < 15) {
            return null;
        }

        return $openDeals->firstWhere('id', $top['deal_id']);
    }

    /** @return list<string> */
    private function tokensFromAnalysis(ConversationAnalysis $analysis): array
    {
        $parts = [
            (string) $analysis->summary,
            implode(' ', array_map(fn ($item) => is_array($item) ? (string) ($item['text'] ?? '') : (string) $item, (array) $analysis->concerns_json)),
            implode(' ', (array) $analysis->next_actions_json),
        ];

        $ops = is_array($analysis->operational_insights_json) ? $analysis->operational_insights_json : [];
        if (! empty($ops['important_keywords']) && is_array($ops['important_keywords'])) {
            $parts[] = implode(' ', $ops['important_keywords']);
        }

        return $this->tokenize(implode(' ', $parts));
    }

    /** @return list<string> */
    private function tokenize(string $text): array
    {
        $text = Str::lower(trim($text));
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 3) {
                continue;
            }
            $tokens[$part] = $part;
        }

        return array_values($tokens);
    }
}
