<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'customer_id',
    'type',
    'raw_value',
    'normalized_value',
    'label',
    'is_primary',
    'is_active',
    'verification_status',
    'source',
])]
class CustomerContactMethod extends Model
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @param  Builder<CustomerContactMethod>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isPhone(): bool
    {
        return in_array($this->type, ['mobile', 'phone'], true);
    }
}
