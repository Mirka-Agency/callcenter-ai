<?php

namespace App\Models;

use App\Domain\Crm\Tasks\TaskEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'crm_task_id',
    'type',
    'actor_user_id',
    'source',
    'previous_json',
    'next_json',
    'summary',
])]
class CrmTaskEvent extends Model
{
    protected function casts(): array
    {
        return [
            'type' => TaskEventType::class,
            'previous_json' => 'array',
            'next_json' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(CrmTask::class, 'crm_task_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
