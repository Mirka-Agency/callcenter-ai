<?php

namespace App\Models;

use App\Domain\Crm\Deals\DealSuggestionStatus;
use App\Domain\Crm\Deals\DealSuggestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'conversation_analysis_id',
    'customer_id',
    'call_id',
    'suggestion_type',
    'status',
    'proposed_title',
    'payload',
    'applied_crm_deal_id',
    'resolved_by_user_id',
    'resolved_at',
])]
class CrmDealAiSuggestion extends Model
{
    protected function casts(): array
    {
        return [
            'suggestion_type' => DealSuggestionType::class,
            'status' => DealSuggestionStatus::class,
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

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function appliedDeal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'applied_crm_deal_id');
    }
}
