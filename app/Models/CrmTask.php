<?php

namespace App\Models;

use App\Domain\Crm\Tasks\TaskDueBasis;
use App\Domain\Crm\Tasks\TaskPriority;
use App\Domain\Crm\Tasks\TaskSource;
use App\Domain\Crm\Tasks\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id',
    'title',
    'description',
    'status',
    'priority',
    'source',
    'assignee_organization_user_id',
    'created_by_user_id',
    'customer_id',
    'customer_company_id',
    'crm_deal_id',
    'call_id',
    'conversation_analysis_id',
    'due_at',
    'due_source',
    'completed_at',
    'completed_by_user_id',
    'completion_outcome',
    'version',
])]
class CrmTask extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (CrmTask $task): void {
            if (! $task->organization_id) {
                throw new \RuntimeException('Tasks must belong to an organization.');
            }

            $status = $task->status instanceof TaskStatus
                ? $task->status
                : TaskStatus::from((string) $task->status);

            if ($status->isOpen() && ($task->completed_at !== null || filled($task->completion_outcome))) {
                throw new \RuntimeException('An open task cannot keep completion data.');
            }

            if ($status === TaskStatus::Completed && $task->completed_at === null) {
                throw new \RuntimeException('A completed task requires completed_at.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'source' => TaskSource::class,
            'due_source' => TaskDueBasis::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->due_at !== null
            && $this->due_at->lt(now());
    }

    /** @return array<string, mixed> */
    public function auditSnapshot(): array
    {
        return [
            'title' => $this->title,
            'status' => $this->status instanceof TaskStatus ? $this->status->value : (string) $this->status,
            'priority' => $this->priority instanceof TaskPriority ? $this->priority->value : (string) $this->priority,
            'assignee_organization_user_id' => $this->assignee_organization_user_id ? (int) $this->assignee_organization_user_id : null,
            'customer_id' => $this->customer_id ? (int) $this->customer_id : null,
            'customer_company_id' => $this->customer_company_id ? (int) $this->customer_company_id : null,
            'crm_deal_id' => $this->crm_deal_id ? (int) $this->crm_deal_id : null,
            'due_at' => $this->due_at?->toDateTimeString(),
            'due_source' => $this->due_source instanceof TaskDueBasis ? $this->due_source->value : $this->due_source,
            'completion_outcome' => $this->completion_outcome,
            'version' => (int) $this->version,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(OrganizationUser::class, 'assignee_organization_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CustomerCompany::class, 'customer_company_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(ConversationAnalysis::class, 'conversation_analysis_id');
    }

    /** @return HasMany<CrmTaskEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(CrmTaskEvent::class);
    }
}
