<?php

namespace App\Services\PlatformAnalytics;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationVoipConnection;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Score out of 100:
 * usage trend 40, recency 20, integration 15, processing reliability 10, employer engagement 15.
 */
class OrganizationHealthScorer
{
    /** @var array<int, OrganizationHealth>|null */
    private ?array $reports = null;

    private ?Carbon $now = null;

    public function at(CarbonInterface $now): static
    {
        $this->now = Carbon::parse($now);
        $this->reports = null;

        return $this;
    }

    public function for(Organization|int $organization): ?OrganizationHealth
    {
        $id = $organization instanceof Organization ? $organization->id : $organization;

        return $this->all()[$id] ?? null;
    }

    /**
     * Reports for every enabled, non-demo organization, keyed by organization id.
     *
     * @return array<int, OrganizationHealth>
     */
    public function all(): array
    {
        return $this->reports ??= $this->score(
            Organization::query()
                ->where('is_demo', false)
                ->where('disabled', false)
                ->with('employer:id,last_login_at')
                ->get(['id', 'user_id', 'created_at']),
        );
    }

    /**
     * Organizations that used the product in the last 60 days and are no longer healthy, worst first.
     *
     * @return Collection<int, OrganizationHealth>
     */
    public function atRisk(int $limit = 10): Collection
    {
        return collect($this->all())
            ->filter(fn (OrganizationHealth $health): bool => $health->band() !== OrganizationHealth::HEALTHY
                && ($health->analysesLast30Days + $health->analysesPrevious30Days) > 0)
            ->sortBy('score')
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, Organization>  $organizations
     * @return array<int, OrganizationHealth>
     */
    private function score(Collection $organizations): array
    {
        if ($organizations->isEmpty()) {
            return [];
        }

        $now = $this->now ?? now();
        $ids = $organizations->modelKeys();
        $last30 = $now->copy()->subDays(30);
        $previous30 = $now->copy()->subDays(60);

        $analysesLast30 = $this->countByOrganization(
            ConversationAnalysis::query()->whereIn('organization_id', $ids)
                ->where('analyzed_at', '>=', $last30)->where('analyzed_at', '<=', $now),
        );
        $analysesPrevious30 = $this->countByOrganization(
            ConversationAnalysis::query()->whereIn('organization_id', $ids)
                ->where('analyzed_at', '>=', $previous30)->where('analyzed_at', '<', $last30),
        );
        $lastAnalysisAt = ConversationAnalysis::query()
            ->whereIn('organization_id', $ids)
            ->where('analyzed_at', '<=', $now)
            ->selectRaw('organization_id, MAX(analyzed_at) as last_at')
            ->groupBy('organization_id')
            ->pluck('last_at', 'organization_id');

        $recentCalls = Call::query()->whereIn('organization_id', $ids)
            ->where('created_at', '>=', $last30)->where('created_at', '<=', $now);
        $analyzedCalls = $this->countByOrganization((clone $recentCalls)->where('processing_status', CallProcessingStatus::Analyzed));
        $failedCalls = $this->countByOrganization((clone $recentCalls)->where('processing_status', CallProcessingStatus::Failed));
        $manualUploads = $this->countByOrganization((clone $recentCalls)->where('source', ConversationSource::ManualUpload));

        $withActiveVoip = OrganizationVoipConnection::query()
            ->whereIn('organization_id', $ids)
            ->where('is_active', true)
            ->distinct()
            ->pluck('organization_id')
            ->flip();

        $reports = [];

        foreach ($organizations as $organization) {
            $id = $organization->id;
            $recent = $analysesLast30[$id] ?? 0;
            $previous = $analysesPrevious30[$id] ?? 0;
            $lastAt = isset($lastAnalysisAt[$id]) ? Carbon::parse($lastAnalysisAt[$id]) : null;
            $analyzed = $analyzedCalls[$id] ?? 0;
            $failed = $failedCalls[$id] ?? 0;
            $lastLogin = $organization->employer?->last_login_at;
            $reasons = [];

            $usage = match (true) {
                $recent === 0 => 0,
                $previous === 0 => 40,
                default => (int) round(40 * min(1, $recent / $previous)),
            };

            if ($previous > 0 && $recent < $previous * 0.7) {
                $reasons[] = ['key' => 'usage_dropped', 'params' => ['percent' => (int) round(100 * (1 - $recent / $previous))]];
            }

            $daysSinceAnalysis = $lastAt ? (int) $lastAt->diffInDays($now) : null;
            $recency = match (true) {
                $daysSinceAnalysis === null => 0,
                $daysSinceAnalysis <= 7 => 20,
                $daysSinceAnalysis <= 14 => 10,
                default => 0,
            };

            if ($daysSinceAnalysis === null) {
                $reasons[] = ['key' => 'no_analysis_ever', 'params' => []];
            } elseif ($daysSinceAnalysis > 14) {
                $reasons[] = ['key' => 'no_analysis_for_days', 'params' => ['days' => $daysSinceAnalysis]];
            }

            $hasIntake = $withActiveVoip->has($id) || ($manualUploads[$id] ?? 0) > 0;
            $integration = $hasIntake ? 15 : 0;

            if (! $hasIntake) {
                $reasons[] = ['key' => 'no_call_intake', 'params' => []];
            }

            $finished = $analyzed + $failed;
            $failureRate = $finished === 0 ? 0 : $failed / $finished;
            $reliability = (int) round(10 * (1 - $failureRate));

            if ($finished >= 3 && $failureRate >= 0.2) {
                $reasons[] = ['key' => 'high_failure_rate', 'params' => ['percent' => (int) round($failureRate * 100)]];
            }

            $daysSinceLogin = $lastLogin ? (int) $lastLogin->diffInDays($now) : null;
            $engagement = match (true) {
                $daysSinceLogin === null => 0,
                $daysSinceLogin <= 14 => 15,
                $daysSinceLogin <= 30 => 7,
                default => 0,
            };

            if ($daysSinceLogin === null) {
                $reasons[] = ['key' => 'employer_never_logged_in', 'params' => []];
            } elseif ($daysSinceLogin > 14) {
                $reasons[] = ['key' => 'employer_inactive', 'params' => ['days' => $daysSinceLogin]];
            }

            $reports[$id] = new OrganizationHealth(
                organizationId: $id,
                score: $usage + $recency + $integration + $reliability + $engagement,
                analysesLast30Days: $recent,
                analysesPrevious30Days: $previous,
                lastAnalysisAt: $lastAt,
                reasons: $reasons,
            );
        }

        return $reports;
    }

    /** @return array<int, int> */
    private function countByOrganization(Builder $query): array
    {
        return $query
            ->selectRaw('organization_id, COUNT(*) as aggregate')
            ->groupBy('organization_id')
            ->pluck('aggregate', 'organization_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }
}
