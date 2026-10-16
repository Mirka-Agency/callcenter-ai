<?php

namespace App\Models;

use App\Domain\Crm\Tasks\TaskDueBasis;
use App\Domain\Crm\Tasks\TaskSuggestionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'conversation_analysis_id',
    'customer_id',
    'crm_deal_id',
    'call_id',
    'action_fingerprint',
    'proposed_title',
    'proposed_description',
    'proposed_due_at',
    'due_basis',
    'status',
    'payload',
    'applied_crm_task_id',
    'resolved_by_user_id',
    'resolved_at',
])]
class CrmAiTaskSuggestion extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TaskSuggestionStatus::class,
            'due_basis' => TaskDueBasis::class,
            'proposed_due_at' => 'datetime',
            'payload' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(ConversationAnalysis::class, 'conversation_analysis_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function appliedTask(): BelongsTo
    {
        return $this->belongsTo(CrmTask::class, 'applied_crm_task_id');
    }
}
