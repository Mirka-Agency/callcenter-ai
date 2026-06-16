<?php

namespace App\Services\Performance\Calculators;

use App\Models\ConversationAnalysis;
use App\Support\AnalysisInsightPresenter;
use Illuminate\Support\Collection;

class JsonFieldAggregator
{
    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return array<string, int>
     */
    public function countItems(Collection $analyses, string $column, int $limit = 100): array
    {
        $counts = [];

        foreach ($analyses->take($limit) as $analysis) {
            foreach ($analysis->{$column} ?? [] as $item) {
                $text = $this->extractItemText($item);
                if ($text) {
                    $counts[$text] = ($counts[$text] ?? 0) + 1;
                }
            }
        }

        arsort($counts);

        return $counts;
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return list<string>
     */
    public function topItems(Collection $analyses, string $column, int $limit = 5): array
    {
        return array_keys(array_slice($this->countItems($analyses, $column), 0, $limit, true));
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return list<array{item: string, count: int}>
     */
    public function rankedItems(Collection $analyses, string $column, int $limit = 8): array
    {
        return collect($this->countItems($analyses, $column))
            ->take($limit)
            ->map(fn (int $count, string $item) => ['item' => $item, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return array{items: list<array{item: string, count: int}>, derived: bool}
     */
    public function rankedImprovementAreas(Collection $analyses, int $limit = 8): array
    {
        $direct = $this->rankedItems($analyses, 'weaknesses_json', $limit);

        if ($direct !== []) {
            return ['items' => $direct, 'derived' => false];
        }

        $derived = $this->derivedImprovementAreas($analyses, $limit);

        return ['items' => $derived, 'derived' => $derived !== []];
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return list<array{item: string, count: int}>
     */
    private function derivedImprovementAreas(Collection $analyses, int $limit): array
    {
        if ($analyses->isEmpty()) {
            return [];
        }

        $merged = array_merge(
            $this->countConcernItems($analyses),
            $this->countOperationalItems($analyses, 'missed_opportunities'),
            $this->countOperationalItems($analyses, 'escalation_risks'),
            $this->lowestDimensionAreas($analyses, $limit),
        );

        if ($merged === []) {
            return [];
        }

        arsort($merged);

        return collect($merged)
            ->take($limit)
            ->map(fn (int $count, string $item) => ['item' => $item, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return array<string, int>
     */
    private function countConcernItems(Collection $analyses): array
    {
        $counts = [];

        foreach ($analyses as $analysis) {
            foreach ($analysis->concerns_json ?? [] as $concern) {
                $text = $this->extractItemText($concern);
                if ($text) {
                    $counts[$text] = ($counts[$text] ?? 0) + 1;
                }
            }
        }

        arsort($counts);

        return $counts;
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return array<string, int>
     */
    private function countOperationalItems(Collection $analyses, string $key): array
    {
        $counts = [];

        foreach ($analyses as $analysis) {
            foreach (($analysis->operational_insights_json[$key] ?? []) as $item) {
                $text = $this->extractItemText($item);
                if ($text) {
                    $counts[$text] = ($counts[$text] ?? 0) + 1;
                }
            }
        }

        arsort($counts);

        return $counts;
    }

    /**
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return array<string, int>
     */
    private function lowestDimensionAreas(Collection $analyses, int $limit): array
    {
        $sums = [];
        $counts = [];

        foreach ($analyses as $analysis) {
            foreach ($analysis->performance_dimensions_json ?? [] as $key => $data) {
                $score = AnalysisInsightPresenter::dimensionScore($data);
                if ($score <= 0) {
                    continue;
                }

                $label = AnalysisInsightPresenter::dimensionLabel($key);
                $sums[$label] = ($sums[$label] ?? 0) + $score;
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            return [];
        }

        $averages = collect($counts)
            ->mapWithKeys(fn (int $count, string $label) => [
                $label => round($sums[$label] / $count, 1),
            ])
            ->sort();

        $spread = $averages->max() - $averages->min();
        $filtered = $spread <= 5
            ? $averages->take(min(2, $limit))
            : $averages->take($limit);

        $result = [];

        foreach ($filtered as $label => $average) {
            $result["تقویت {$label} (میانگین {$average})"] = $counts[$label];
        }

        return $result;
    }

    private function extractItemText(mixed $item): ?string
    {
        if (is_string($item)) {
            $text = trim($item);

            return $text !== '' ? $text : null;
        }

        if (! is_array($item)) {
            return null;
        }

        foreach (['text', 'title', 'weakness', 'description', 'label', 'action'] as $key) {
            if (! empty($item[$key]) && is_string($item[$key])) {
                return trim($item[$key]);
            }
        }

        return null;
    }
}
