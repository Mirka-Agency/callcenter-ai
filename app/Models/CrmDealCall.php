<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'crm_deal_id',
    'call_id',
    'conversation_analysis_id',
    'source',
    'evidence_json',
    'linked_by_user_id',
])]
class CrmDealCall extends Model
{
    protected function casts(): array
    {
        return [
            'evidence_json' => 'array',
        ];
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

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by_user_id');
    }
}
