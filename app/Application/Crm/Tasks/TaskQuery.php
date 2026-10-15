<?php

namespace App\Application\Crm\Tasks;

use App\Domain\Crm\Tasks\TaskStatus;
use App\Models\CrmTask;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TaskQuery
{
    public function __construct(private TaskAuthorization $auth) {}

    /** @return Builder<CrmTask> */
    public function visible(int $organizationId, User $actor): Builder
    {
        $query = CrmTask::query()->where('crm_tasks.organization_id', $organizationId);

        if (! $this->auth->canViewTeam($actor, $organizationId)) {
            $memberId = $this->auth->membershipId($actor, $organizationId) ?? 0;
            $query->where('crm_tasks.assignee_organization_user_id', $memberId);
        }

        return $query;
    }

    public function findVisible(int $organizationId, User $actor, int $taskId): CrmTask
    {
        $task = $this->visible($organizationId, $actor)->whereKey($taskId)->first();
        if (! $task instanceof CrmTask) {
            abort(404);
        }

        return $task;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CrmTask>
     */
    public function filtered(int $organizationId, User $actor, array $filters): Builder
    {
        $query = $this->visible($organizationId, $actor);
        $status = (string) ($filters['status'] ?? 'open');

        if ($status === 'open') {
            $query->whereIn('crm_tasks.status', [TaskStatus::Pending->value, TaskStatus::InProgress->value]);
        } elseif ($status === 'overdue') {
            $query->whereIn('crm_tasks.status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])
                ->whereNotNull('crm_tasks.due_at')
                ->where('crm_tasks.due_at', '<', now());
        } elseif ($status === 'today') {
            $query->whereIn('crm_tasks.status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])
                ->whereDate('crm_tasks.due_at', now()->toDateString());
        } elseif ($status !== 'all') {
            $query->where('crm_tasks.status', $status);
        }

        if (($filters['assignee'] ?? 'all') === 'mine') {
            $memberId = $this->auth->membershipId($actor, $organizationId) ?? 0;
            $query->where('crm_tasks.assignee_organization_user_id', $memberId);
        } elseif (($filters['assignee'] ?? 'all') === 'unassigned') {
            $query->whereNull('crm_tasks.assignee_organization_user_id');
        } elseif (! empty($filters['assignee_id'])) {
            $query->where('crm_tasks.assignee_organization_user_id', (int) $filters['assignee_id']);
        }

        if (! empty($filters['priority'])) {
            $query->where('crm_tasks.priority', $filters['priority']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('crm_tasks.customer_id', (int) $filters['customer_id']);
        }

        if (! empty($filters['crm_deal_id'])) {
            $query->where('crm_tasks.crm_deal_id', (int) $filters['crm_deal_id']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('crm_tasks.title', 'like', $term)
                    ->orWhere('crm_tasks.description', 'like', $term);
            });
        }

        $sort = $filters['sort'] ?? 'due';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return match ($sort) {
            'priority' => $query->orderBy('crm_tasks.priority', $direction)->orderBy('crm_tasks.id', 'desc'),
            'created' => $query->orderBy('crm_tasks.created_at', $direction),
            default => $query->orderByRaw('crm_tasks.due_at is null')->orderBy('crm_tasks.due_at', $direction)->orderByDesc('crm_tasks.id'),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CrmTask>
     */
    public function paginate(int $organizationId, User $actor, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->filtered($organizationId, $actor, $filters)
            ->with(['assignee', 'customer', 'company', 'deal'])
            ->paginate($perPage)
            ->withQueryString();
    }
}
