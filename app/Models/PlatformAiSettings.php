<?php

namespace App\Models;

use App\Models\Concerns\AuditedByAdmins;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'default_llm_provider_id',
    'default_llm_model_id',
    'default_transcription_provider_id',
    'default_transcription_model_id',
])]
class PlatformAiSettings extends Model
{
    use AuditedByAdmins;

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function defaultProvider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'default_llm_provider_id');
    }

    public function defaultModel(): BelongsTo
    {
        return $this->belongsTo(LlmModel::class, 'default_llm_model_id');
    }

    public function transcriptionProvider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'default_transcription_provider_id');
    }

    public function transcriptionModel(): BelongsTo
    {
        return $this->belongsTo(LlmModel::class, 'default_transcription_model_id');
    }
}
