<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'customer_id',
    'call_id',
    'conversation_analysis_id',
    'kind',
    'field',
    'current_value',
    'suggested_value',
    'evidence',
    'status',
    'context',
    'resolved_by',
    'resolved_at',
])]
class CustomerIdentityReview extends Model
{
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(ConversationAnalysis::class, 'conversation_analysis_id');
    }
}
