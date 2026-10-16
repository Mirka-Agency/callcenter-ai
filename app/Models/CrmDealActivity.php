<?php

namespace App\Models;

use App\Domain\Crm\Deals\DealActivityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'crm_deal_id',
    'type',
    'actor_user_id',
    'source',
    'previous_json',
    'next_json',
])]
class CrmDealActivity extends Model
{
    protected function casts(): array
    {
        return [
            'type' => DealActivityType::class,
            'previous_json' => 'array',
            'next_json' => 'array',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
