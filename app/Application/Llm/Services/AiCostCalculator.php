<?php

namespace App\Application\Llm\Services;

use App\Models\LlmModel;
use App\Models\PlatformAiSettings;

class AiCostCalculator
{
    public function calculate(LlmModel $model, int $inputTokens, int $outputTokens, int $cachedTokens = 0, int $reasoningTokens = 0): float
    {
        return $model->calculateCost($inputTokens, $outputTokens, $cachedTokens, $reasoningTokens);
    }

    /** @return array{cost: float, input_price: float, output_price: float, cached_price: ?float, reasoning_price: ?float} */
    public function calculateWithSnapshots(LlmModel $model, int $inputTokens, int $outputTokens, int $cachedTokens = 0, int $reasoningTokens = 0): array
    {
        return [
            'cost' => $this->calculate($model, $inputTokens, $outputTokens, $cachedTokens, $reasoningTokens),
            'input_price' => PlatformAiSettings::convertFromUnits((float) $model->input_price_per_million_tokens),
            'output_price' => PlatformAiSettings::convertFromUnits((float) $model->output_price_per_million_tokens),
            'cached_price' => $model->cached_input_price_per_million_tokens
                ? PlatformAiSettings::convertFromUnits((float) $model->cached_input_price_per_million_tokens)
                : null,
            'reasoning_price' => $model->reasoning_price_per_million_tokens
                ? PlatformAiSettings::convertFromUnits((float) $model->reasoning_price_per_million_tokens)
                : null,
        ];
    }
}
