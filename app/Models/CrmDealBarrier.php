<?php

namespace App\Models;

use App\Domain\Crm\Deals\DealBarrierStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'crm_deal_id',
    'category',
    'label',
    'status',
    'occurrence_count',
    'evidence_text',
    'suggested_action',
    'source_call_id',
    'source_conversation_analysis_id',
    'first_seen_at',
    'last_seen_at',
    'resolved_by_user_id',
    'resolved_at',
])]
class CrmDealBarrier extends Model
{
    protected function casts(): array
    {
        return [
            'status' => DealBarrierStatus::class,
            'occurrence_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function sourceCall(): BelongsTo
    {
        return $this->belongsTo(Call::class, 'source_call_id');
    }

    public function sourceAnalysis(): BelongsTo
    {
        return $this->belongsTo(ConversationAnalysis::class, 'source_conversation_analysis_id');
    }
}
