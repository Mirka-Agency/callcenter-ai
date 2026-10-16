<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'customer_id',
    'from_stage',
    'to_stage',
    'actor_user_id',
    'source',
    'reason',
    'changed_at',
])]
class CustomerLifecycleEvent extends Model
{
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
