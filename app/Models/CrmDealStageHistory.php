<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'crm_deal_id',
    'from_stage_id',
    'to_stage_id',
    'from_stage_name',
    'to_stage_name',
    'from_status',
    'to_status',
    'actor_user_id',
    'source',
    'changed_at',
])]
class CrmDealStageHistory extends Model
{
    protected $table = 'crm_deal_stage_history';

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
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
