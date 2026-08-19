<?php

namespace App\Infrastructure\Performance\Repositories;

use App\Domain\Performance\Contracts\EmployeePerformanceRepositoryInterface;
use App\Domain\Performance\Enums\PerformancePeriod;
use App\Models\ConversationAnalysis;
use App\Models\EmployeePerformanceSnapshot;
use App\Models\OrganizationUser;
use Carbon\Carbon;

class EloquentEmployeePerformanceRepository implements EmployeePerformanceRepositoryInterface
{
    public function recalculateForEmployee(int $organizationId, int $organizationUserId): void
    {
        foreach ([PerformancePeriod::Daily, PerformancePeriod::Weekly, PerformancePeriod::Monthly, PerformancePeriod::Overall] as $period) {
            [$start, $end] = $this->periodRange($period);

            $query = ConversationAnalysis::query()
                ->where('organization_id', $organizationId)
                ->where('organization_user_id', $organizationUserId)
                ->when($period !== PerformancePeriod::Overall, fn ($q) => $q->whereBetween('analyzed_at', [$start, $end]));

            $count = (clone $query)->count();

            if ($count === 0) {
                continue;
            }

            $strengths = [];
            $weaknesses = [];

            (clone $query)->latest('analyzed_at')->limit(30)->get()->each(function (ConversationAnalysis $a) use (&$strengths, &$weaknesses) {
                foreach ($a->strengths_json ?? [] as $s) {
                    $strengths[$s] = ($strengths[$s] ?? 0) + 1;
                }
                foreach ($a->weaknesses_json ?? [] as $w) {
                    $weaknesses[$w] = ($weaknesses[$w] ?? 0) + 1;
                }
            });

            arsort($strengths);
            arsort($weaknesses);

            EmployeePerformanceSnapshot::query()->updateOrCreate(
                [
                    'organization_user_id' => $organizationUserId,
                    'period' => $period->value,
                    'period_start' => $start->toDateString(),
                ],
                [
                    'organization_id' => $organizationId,
                    'period_end' => $end->toDateString(),
                    'average_score' => (int) round((clone $query)->evaluable()->avg('score') ?: 0),
                    'conversations_count' => $count,
                    'top_strengths_json' => array_slice(array_keys($strengths), 0, 5),
                    'top_weaknesses_json' => array_slice(array_keys($weaknesses), 0, 5),
                ],
            );
        }
    }

    public function recalculateForOrganization(int $organizationId): void
    {
        OrganizationUser::query()
            ->where('organization_id', $organizationId)
            ->whereHas('conversationAnalyses')
            ->pluck('id')
            ->each(fn ($id) => $this->recalculateForEmployee($organizationId, $id));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodRange(PerformancePeriod $period): array
    {
        return match ($period) {
            PerformancePeriod::Daily => [now()->startOfDay(), now()->endOfDay()],
            PerformancePeriod::Weekly => [now()->startOfWeek(), now()->endOfWeek()],
            PerformancePeriod::Monthly => [now()->startOfMonth(), now()->endOfMonth()],
            PerformancePeriod::Overall => [now()->subYears(10), now()],
        };
    }
}
