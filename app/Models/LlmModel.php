<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider_id',
    'name',
    'model_key',
    'input_price_per_million_tokens',
    'output_price_per_million_tokens',
    'cached_input_price_per_million_tokens',
    'reasoning_price_per_million_tokens',
    'is_default',
    'is_active',
])]
class LlmModel extends Model
{
    protected function casts(): array
    {
        return [
            'input_price_per_million_tokens' => 'decimal:6',
            'output_price_per_million_tokens' => 'decimal:6',
            'cached_input_price_per_million_tokens' => 'decimal:6',
            'reasoning_price_per_million_tokens' => 'decimal:6',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'provider_id');
    }

    public function calculateCost(int $inputTokens, int $outputTokens, int $cachedTokens = 0, int $reasoningTokens = 0): float
    {
        $inputCost = ($inputTokens / 1_000_000) * (float) $this->input_price_per_million_tokens;
        $outputCost = ($outputTokens / 1_000_000) * (float) $this->output_price_per_million_tokens;
        $cachedCost = $cachedTokens > 0 && $this->cached_input_price_per_million_tokens
            ? ($cachedTokens / 1_000_000) * (float) $this->cached_input_price_per_million_tokens
            : 0;
        $reasoningCost = $reasoningTokens > 0 && $this->reasoning_price_per_million_tokens
            ? ($reasoningTokens / 1_000_000) * (float) $this->reasoning_price_per_million_tokens
            : 0;

        return PlatformAiSettings::convertFromUnits(
            round($inputCost + $outputCost + $cachedCost + $reasoningCost, 6),
        );
    }
}
