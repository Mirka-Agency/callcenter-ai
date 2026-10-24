<?php

namespace App\Services\PlatformAnalytics;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Crm\OrganizationCrmMode;
use App\Enums\UserRole;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\CrmAiTaskSuggestion;
use App\Models\CrmDeal;
use App\Models\CrmDealAiSuggestion;
use App\Models\CrmTask;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\UserActivityDay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class EngagementMetrics
{
    public const ONBOARDING_STEPS = [
        'employees_added',
        'call_intake_connected',
        'first_call_analyzed',
        'crm_configured',
        'first_deal_created',
        'employer_logged_in',
    ];

    public function __construct(private readonly PlatformMetrics $platform) {}

    /**
     * Share of organizations active in the last 30 days that used each feature in that window.
     *
     * @return array{active: int, features: array<string, array{organizations: int, rate: float|null}>}
     */
    public function featureAdoption(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $since = $now->copy()->subDays(30);
        $active = $this->platform->activeOrganizationIds($since, $now->copy()->addSecond());

        $features = [
            'deals' => CrmDeal::query()->where('created_at', '>=', $since),
            'tasks' => CrmTask::query()->where('created_at', '>=', $since),
            'ai_deal_suggestions' => CrmDealAiSuggestion::query()->whereNotNull('resolved_at')->where('resolved_at', '>=', $since),
            'ai_task_suggestions' => CrmAiTaskSuggestion::query()->whereNotNull('resolved_at')->where('resolved_at', '>=', $since),
            'customers' => Customer::query()->where('created_at', '>=', $since),
            'manual_uploads' => Call::query()->where('source', ConversationSource::ManualUpload)->where('created_at', '>=', $since),
        ];

        $result = [];

        foreach ($features as $key => $query) {
            $users = $active === [] ? 0 : $this->distinctOrganizations($query->whereIn('organization_id', $active));
            $result[$key] = [
                'organizations' => $users,
                'rate' => $active === [] ? null : $users / count($active),
            ];
        }

        return ['active' => count($active), 'features' => $result];
    }

    /**
     * Organizations that added employees in the last 30 days.
     *
     * @return array{organizations: int, seats_added: int}
     */
    public function expansion(?CarbonInterface $now = null): array
    {
        $now ??= now();

        $query = OrganizationUser::query()
            ->whereIn('organization_id', $this->platform->realOrganizations()->select('id'))
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->whereIn('organization_id', Organization::query()->where('created_at', '<', $now->copy()->subDays(30))->select('id'));

        return [
            'organizations' => $this->distinctOrganizations(clone $query),
            'seats_added' => $query->count(),
        ];
    }

    /**
     * Onboarding progress per organization, keyed by organization id.
     *
     * @param  list<int>|null  $organizationIds
     * @return array<int, array<string, bool>>
     */
    public function onboarding(?array $organizationIds = null): array
    {
        $organizations = $this->platform->realOrganizations()
            ->when($organizationIds !== null, fn (Builder $query) => $query->whereKey($organizationIds))
            ->with('employer:id,last_login_at')
            ->get(['id', 'user_id', 'crm_mode']);

        if ($organizations->isEmpty()) {
            return [];
        }

        $ids = $organizations->modelKeys();
        $has = fn (Builder $query): array => array_flip($query->whereIn('organization_id', $ids)->distinct()->pluck('organization_id')->map(fn ($id): int => (int) $id)->all());

        $withEmployees = $has(OrganizationUser::query());
        $withVoip = $has(OrganizationVoipConnection::query()->where('is_active', true));
        $withUploads = $has(Call::query()->where('source', ConversationSource::ManualUpload));
        $withAnalysis = $has(ConversationAnalysis::query());
        $withCrmConnection = $has(OrganizationCrmConnection::query()->where('is_active', true));
        $withDeals = $has(CrmDeal::query());

        $result = [];

        foreach ($organizations as $organization) {
            $id = $organization->id;

            $crmConfigured = match ($organization->crm_mode) {
                OrganizationCrmMode::External => isset($withCrmConnection[$id]),
                OrganizationCrmMode::Internal => true,
                default => false,
            };

            $result[$id] = [
                'employees_added' => isset($withEmployees[$id]),
                'call_intake_connected' => isset($withVoip[$id]) || isset($withUploads[$id]),
                'first_call_analyzed' => isset($withAnalysis[$id]),
                'crm_configured' => $crmConfigured,
                'first_deal_created' => isset($withDeals[$id]) || $organization->crm_mode === OrganizationCrmMode::External,
                'employer_logged_in' => $organization->employer?->last_login_at !== null,
            ];
        }

        return $result;
    }

    /**
     * For organizations created in the last $days, how many are stuck at each onboarding step
     * (the first step they have not completed).
     *
     * @return array{organizations: int, completed: int, stuck_at: array<string, int>}
     */
    public function onboardingFunnel(int $days = 90, ?CarbonInterface $now = null): array
    {
        $now ??= now();

        $ids = $this->platform->realOrganizations()
            ->where('created_at', '>=', $now->copy()->subDays($days))
            ->pluck('id')
            ->all();

        $stuckAt = array_fill_keys(self::ONBOARDING_STEPS, 0);
        $completed = 0;

        foreach ($this->onboarding($ids) as $steps) {
            $first = collect(self::ONBOARDING_STEPS)->first(fn (string $step): bool => ! $steps[$step]);

            if ($first === null) {
                $completed++;
            } else {
                $stuckAt[$first]++;
            }
        }

        return ['organizations' => count($ids), 'completed' => $completed, 'stuck_at' => $stuckAt];
    }

    /**
     * Daily, weekly and monthly active customer users, and stickiness (average DAU / MAU over 30 days).
     *
     * @return array{dau: int, wau: int, mau: int, stickiness: float|null, by_role: array<string, int>}
     */
    public function activeUsers(?CarbonInterface $now = null): array
    {
        $today = ($now ?? now())->copy()->startOfDay();
        $distinctSince = fn (CarbonInterface $since): int => UserActivityDay::query()
            ->where('activity_date', '>=', $since->toDateString())
            ->where('activity_date', '<=', $today->toDateString())
            ->distinct()
            ->count('user_id');

        $mau = $distinctSince($today->copy()->subDays(29));
        $dailyTotal = UserActivityDay::query()
            ->where('activity_date', '>=', $today->copy()->subDays(29)->toDateString())
            ->where('activity_date', '<=', $today->toDateString())
            ->count();

        $byRole = UserActivityDay::query()
            ->where('activity_date', '>=', $today->copy()->subDays(29)->toDateString())
            ->selectRaw('role, COUNT(DISTINCT user_id) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return [
            'dau' => $distinctSince($today),
            'wau' => $distinctSince($today->copy()->subDays(6)),
            'mau' => $mau,
            'stickiness' => $mau === 0 ? null : ($dailyTotal / 30) / $mau,
            'by_role' => [
                UserRole::Employer->value => $byRole[UserRole::Employer->value] ?? 0,
                UserRole::Employee->value => $byRole[UserRole::Employee->value] ?? 0,
            ],
        ];
    }

    private function distinctOrganizations(Builder $query): int
    {
        return $query->distinct()->count('organization_id');
    }
}
