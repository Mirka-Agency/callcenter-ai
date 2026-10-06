<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilter;
use App\Models\ConversationAnalysis;
use App\Services\CallIntake\CallIntakePolicy;
use Illuminate\Support\Collection;

class LeadConcernsAnalytics
{
    /** @return array{high: int, medium: int, low: int, total: int, average_score: float} */
    public function leadQualityDistribution(ReportFilter $filter): array
    {
        $distribution = ['high' => 0, 'medium' => 0, 'low' => 0];
        $scores = [];

        $this->analyses($filter)
            ->evaluable()
            ->select(['id', 'lead_quality_json'])
            ->chunkById(200, function (Collection $chunk) use (&$distribution, &$scores): void {
                foreach ($chunk as $analysis) {
                    $this->accumulateLead($analysis->lead_quality_json, $distribution, $scores);
                }
            });

        return $this->leadDistributionResult($distribution, $scores);
    }

    /**
     * Same rollup as the database scan, over analyses already loaded for this dashboard.
     *
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return array{high: int, medium: int, low: int, total: int, average_score: float}
     */
    public function summarizeLoaded(Collection $analyses): array
    {
        $distribution = ['high' => 0, 'medium' => 0, 'low' => 0];
        $scores = [];

        foreach ($analyses as $analysis) {
            if ($analysis->is_evaluable !== true || (int) $analysis->score <= 0) {
                continue;
            }

            $this->accumulateLead($analysis->lead_quality_json, $distribution, $scores);
        }

        return $this->leadDistributionResult($distribution, $scores);
    }

    /**
     * @param  array<string, mixed>|null  $lead
     * @param  array{high: int, medium: int, low: int}  $distribution
     * @param  list<int>  $scores
     */
    private function accumulateLead(mixed $lead, array &$distribution, array &$scores): void
    {
        if (! is_array($lead) || $lead === []) {
            return;
        }

        $level = strtolower((string) ($lead['level'] ?? 'medium'));
        if (! isset($distribution[$level])) {
            $level = 'medium';
        }
        $distribution[$level]++;

        if (isset($lead['score'])) {
            $scores[] = (int) $lead['score'];
        }
    }

    /**
     * @param  array{high: int, medium: int, low: int}  $distribution
     * @param  list<int>  $scores
     * @return array{high: int, medium: int, low: int, total: int, average_score: float}
     */
    private function leadDistributionResult(array $distribution, array $scores): array
    {
        return [
            'high' => $distribution['high'],
            'medium' => $distribution['medium'],
            'low' => $distribution['low'],
            'total' => array_sum($distribution),
            'average_score' => $scores !== [] ? round(array_sum($scores) / count($scores), 1) : 0,
        ];
    }

    /** @return list<array{type: string, label: string, count: int}> */
    public function concernsByType(ReportFilter $filter): array
    {
        $counts = [
            'price' => 0,
            'trust' => 0,
            'timing' => 0,
            'technical' => 0,
            'other' => 0,
        ];

        $this->analyses($filter)
            ->select(['id', 'concerns_json'])
            ->chunkById(200, function (Collection $chunk) use (&$counts): void {
                foreach ($chunk as $analysis) {
                    foreach ($analysis->concerns_json ?? [] as $concern) {
                        if (! is_array($concern)) {
                            continue;
                        }
                        $type = strtolower((string) ($concern['type'] ?? 'other'));
                        if (! isset($counts[$type])) {
                            $type = 'other';
                        }
                        $counts[$type]++;
                    }
                }
            });

        $labels = [
            'price' => 'قیمت',
            'trust' => 'اعتماد',
            'timing' => 'زمان‌بندی',
            'technical' => 'فنی',
            'other' => 'سایر',
        ];

        return collect($counts)
            ->map(fn (int $count, string $type) => [
                'type' => $type,
                'label' => $labels[$type],
                'count' => $count,
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    public function totalConcerns(ReportFilter $filter): int
    {
        $total = 0;

        $this->analyses($filter)
            ->select(['id', 'concerns_json'])
            ->chunkById(200, function (Collection $chunk) use (&$total): void {
                foreach ($chunk as $analysis) {
                    $total += count($analysis->concerns_json ?? []);
                }
            });

        return $total;
    }

    /** @return list<array{id: int, name: string, average_lead_score: float, high_leads: int, total_leads: int}> */
    public function employeeLeadQuality(ReportFilter $filter): array
    {
        $byEmployee = [];

        $this->analyses($filter)
            ->evaluable()
            ->with('employee:id,first_name,last_name,user_id')
            ->select(['id', 'organization_user_id', 'lead_quality_json'])
            ->chunkById(200, function (Collection $chunk) use (&$byEmployee): void {
                foreach ($chunk as $analysis) {
                    $employeeId = $analysis->organization_user_id;
                    if (! $employeeId) {
                        continue;
                    }

                    if (! isset($byEmployee[$employeeId])) {
                        $byEmployee[$employeeId] = [
                            'id' => $employeeId,
                            'name' => $analysis->employee?->full_name ?? '—',
                            'scores' => [],
                            'high_leads' => 0,
                            'total_leads' => 0,
                        ];
                    }

                    $lead = $analysis->lead_quality_json;
                    if (! is_array($lead) || empty($lead)) {
                        continue;
                    }

                    $byEmployee[$employeeId]['total_leads']++;
                    if (($lead['level'] ?? '') === 'high') {
                        $byEmployee[$employeeId]['high_leads']++;
                    }
                    if (isset($lead['score'])) {
                        $byEmployee[$employeeId]['scores'][] = (int) $lead['score'];
                    }
                }
            });

        return collect($byEmployee)
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'average_lead_score' => $row['scores'] !== []
                    ? round(array_sum($row['scores']) / count($row['scores']), 1)
                    : 0,
                'high_leads' => $row['high_leads'],
                'total_leads' => $row['total_leads'],
            ])
            ->sortByDesc('average_lead_score')
            ->values()
            ->all();
    }

    private function analyses(ReportFilter $filter)
    {
        return app(CallIntakePolicy::class)->applyToAnalyses(
            $filter->applyToAnalysisQuery(ConversationAnalysis::query()),
            $filter->organizationId,
        );
    }
}
