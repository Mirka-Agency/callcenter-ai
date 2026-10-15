<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'crm_task_id',
    'kind',
    'idempotency_key',
    'due_at_snapshot',
    'sent_at',
    'status',
])]
class CrmTaskReminder extends Model
{
    protected function casts(): array
    {
        return [
            'due_at_snapshot' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(CrmTask::class, 'crm_task_id');
    }
}
