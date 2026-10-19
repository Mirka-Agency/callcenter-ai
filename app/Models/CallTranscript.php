<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'call_id',
    'voip_call_log_id',
    'organization_user_id',
    'organization_voip_connection_id',
    'content',
    'language',
    'duration_seconds',
    'status',
    'error_message',
    'processed_at',
    'transcription_provider',
    'model_name',
    'speakers_json',
    'confidence_scores_json',
    'processing_duration_ms',
])]
class CallTranscript extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'speakers_json' => 'array',
            'confidence_scores_json' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
