<?php

namespace App\Application\Crm\Deals;

use App\Domain\Crm\Deals\DealSuggestionStatus;
use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Tasks\TaskStatus;
use App\Models\CrmDeal;
use App\Models\CrmDealAiSuggestion;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DealQuery
{
    public function __construct(private DealAuthorization $auth) {}

    /** @return Builder<CrmDeal> */
    public function visible(int $organizationId, User $actor): Builder
    {
        $query = CrmDeal::query()->where('crm_deals.organization_id', $organizationId);

        if (! $this->auth->canViewAllDeals($actor, $organizationId)) {
            $memberId = $this->auth->membershipId($actor, $organizationId) ?? 0;
            $query->where('crm_deals.owner_organization_user_id', $memberId);
        }

        return $query;
    }

    public function findVisible(int $organizationId, User $actor, int $dealId): CrmDeal
    {
        $deal = $this->visible($organizationId, $actor)
            ->withTrashed()
            ->whereKey($dealId)
            ->first();

        if (! $deal instanceof CrmDeal) {
            abort(404);
        }

        return $deal;
    }

    /**
     * @param  array{search?: string, status?: string, pipeline_id?: int|null, owner?: string, customer_id?: int|null, sort?: string, direction?: string}  $filters
     * @return Builder<CrmDeal>
     */
    public function filtered(int $organizationId, User $actor, array $filters): Builder
    {
        $query = $this->visible($organizationId, $actor);
        $status = $filters['status'] ?? 'open';

        if ($status === 'archived') {
            $query->onlyTrashed();
        } elseif ($status === 'stale') {
            app(DealAging::class)->scopeStaleCandidates($query);
        } elseif ($status !== 'all') {
            $query->where('crm_deals.status', $status);
        }

        if (! empty($filters['pipeline_id'])) {
            $query->where('crm_deals.crm_pipeline_id', (int) $filters['pipeline_id']);
        }

        if (($filters['owner'] ?? 'all') === 'unassigned') {
            $query->whereNull('crm_deals.owner_organization_user_id');
        } elseif (($filters['owner'] ?? 'all') === 'mine') {
            $memberId = $this->auth->membershipId($actor, $organizationId);
            $query->where('crm_deals.owner_organization_user_id', $memberId ?? 0);
        }

        if (! empty($filters['customer_id'])) {
            $customerId = (int) $filters['customer_id'];
            $query->whereHas('contacts', fn (Builder $contacts) => $contacts->where('customer_id', $customerId));
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('crm_deals.title', 'like', $term)
                    ->orWhereHas('company', fn (Builder $company) => $company->where('name', 'like', $term))
                    ->orWhereHas('contacts.customer', function (Builder $customer) use ($term): void {
                        $customer->where('name', 'like', $term)
                            ->orWhere('phone_number', 'like', $term)
                            ->orWhere('company_name', 'like', $term);
                    });
            });
        }

        $sort = $filters['sort'] ?? 'activity';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sort === 'amount') {
            $query->orderBy('crm_deals.amount_rials', $direction);
        } elseif ($sort === 'close') {
            $query->orderBy('crm_deals.expected_close_on', $direction);
        } elseif ($sort === 'title') {
            $query->orderBy('crm_deals.title', $direction);
        } elseif ($sort === 'owner') {
            $query->leftJoin('organization_user as deal_owners', 'deal_owners.id', '=', 'crm_deals.owner_organization_user_id')
                ->select('crm_deals.*')
                ->orderBy('deal_owners.last_name', $direction);
        } elseif ($sort === 'stage') {
            $query->leftJoin('crm_pipeline_stages as deal_stages', 'deal_stages.id', '=', 'crm_deals.crm_pipeline_stage_id')
                ->select('crm_deals.*')
                ->orderBy('deal_stages.position', $direction);
        } else {
            $query->orderBy('crm_deals.last_activity_at', $direction);
        }

        return $query->orderBy('crm_deals.id', 'desc');
    }

    /**
     * @param  array{search?: string, status?: string, pipeline_id?: int|null, owner?: string, customer_id?: int|null, sort?: string, direction?: string}  $filters
     */
    public function paginate(int $organizationId, User $actor, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($organizationId, $actor, $filters)
            ->with(['stage', 'owner', 'company', 'primaryContact.customer'])
            ->paginate($perPage);
    }

    /** @return Collection<int, CrmDeal> */
    public function board(int $organizationId, User $actor, int $pipelineId, array $filters): Collection
    {
        $filters['status'] = 'open';
        $filters['pipeline_id'] = $pipelineId;

        return $this->filtered($organizationId, $actor, $filters)
            ->with(['stage', 'owner', 'company', 'primaryContact.customer'])
            ->withCount([
                'tasks as open_tasks_count' => fn (Builder $tasks) => $tasks->whereIn('status', [
                    TaskStatus::Pending->value,
                    TaskStatus::InProgress->value,
                ]),
            ])
            ->limit(300)
            ->get();
    }

    /** @return array<int, int> */
    public function openCountsByPipeline(int $organizationId, User $actor): array
    {
        return $this->visible($organizationId, $actor)
            ->where('status', DealStatus::Open)
            ->select('crm_pipeline_id')
            ->selectRaw('COUNT(*) as deal_count')
            ->groupBy('crm_pipeline_id')
            ->pluck('deal_count', 'crm_pipeline_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /** @return Collection<int, CrmDeal> */
    public function forCustomer(int $organizationId, User $actor, Customer $customer): Collection
    {
        return $this->visible($organizationId, $actor)
            ->whereHas('contacts', fn (Builder $contacts) => $contacts->where('customer_id', $customer->id))
            ->with(['stage', 'owner'])
            ->latest('last_activity_at')
            ->limit(10)
            ->get();
    }

    /** @return Collection<int, CrmDealAiSuggestion> */
    public function pendingSuggestions(int $organizationId, User $actor): Collection
    {
        $suggestions = CrmDealAiSuggestion::query()
            ->where('organization_id', $organizationId)
            ->where('status', DealSuggestionStatus::Pending)
            ->with(['customer', 'analysis'])
            ->latest('id')
            ->limit(20)
            ->get();

        if ($this->auth->canViewAllDeals($actor, $organizationId)) {
            return $suggestions;
        }

        $visibleDealIds = $this->visible($organizationId, $actor)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $suggestions->filter(function (CrmDealAiSuggestion $suggestion) use ($visibleDealIds): bool {
            if ($suggestion->suggestion_type->value === 'create_deal') {
                return true;
            }

            $candidates = array_map('intval', (array) ($suggestion->payload['candidate_deal_ids'] ?? []));

            return array_intersect($candidates, $visibleDealIds) !== [];
        })->values();
    }
}
