<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider_id',
    'name',
    'model_key',
    'is_default',
    'is_transcription_default',
    'is_active',
    'sends_audio_file',
])]
class LlmModel extends Model
{
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_transcription_default' => 'boolean',
            'is_active' => 'boolean',
            'sends_audio_file' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'provider_id');
    }
}
