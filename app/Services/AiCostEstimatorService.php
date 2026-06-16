<?php

namespace App\Services;

use App\Domain\Billing\Enums\ConversationEstimateType;
use App\Models\LlmModel;
use App\Models\PlatformAiSettings;
use Illuminate\Support\Collection;

class AiCostEstimatorService
{
    public const SIMULATION_DURATIONS = [5, 15, 30, 60, 120];

    public function wordsPerMinute(): float
    {
        return (float) PlatformAiSettings::current()->estimation_words_per_minute;
    }

    public function tokensPerWord(): float
    {
        return (float) PlatformAiSettings::current()->estimation_tokens_per_word;
    }

    public function outputRatio(ConversationEstimateType $type, ?float $customRatio = null): float
    {
        if ($type === ConversationEstimateType::Custom && $customRatio !== null) {
            return max(0, $customRatio);
        }

        $ratios = PlatformAiSettings::current()->estimation_conversation_ratios ?? [];

        return (float) ($ratios[$type->value] ?? $type->defaultOutputRatio());
    }

    /** @return array{input_tokens: int, output_tokens: int, total_tokens: int} */
    public function estimateTokens(
        float $audioMinutes,
        ConversationEstimateType $type = ConversationEstimateType::ShortSupport,
        ?float $customOutputRatio = null,
    ): array {
        $audioMinutes = max(0, $audioMinutes);
        $inputTokens = (int) round($audioMinutes * $this->wordsPerMinute() * $this->tokensPerWord());
        $outputTokens = (int) round($inputTokens * $this->outputRatio($type, $customOutputRatio));

        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
        ];
    }

    /** @return array{input_tokens: int, output_tokens: int, total_tokens: int, cost: float} */
    public function estimateCost(
        LlmModel $model,
        float $audioMinutes,
        ConversationEstimateType $type = ConversationEstimateType::ShortSupport,
        ?float $customOutputRatio = null,
    ): array {
        $tokens = $this->estimateTokens($audioMinutes, $type, $customOutputRatio);

        return [
            ...$tokens,
            'cost' => $model->calculateCost($tokens['input_tokens'], $tokens['output_tokens']),
        ];
    }

    public function costPerMinute(
        LlmModel $model,
        ConversationEstimateType $type = ConversationEstimateType::ShortSupport,
        ?float $customOutputRatio = null,
    ): float {
        return $this->estimateCost($model, 1, $type, $customOutputRatio)['cost'];
    }

    public function costPerHour(
        LlmModel $model,
        ConversationEstimateType $type = ConversationEstimateType::ShortSupport,
        ?float $customOutputRatio = null,
    ): float {
        return $this->estimateCost($model, 60, $type, $customOutputRatio)['cost'];
    }

    /** @return array<int, float> */
    public function simulateDurations(
        LlmModel $model,
        ConversationEstimateType $type = ConversationEstimateType::ShortSupport,
        ?float $customOutputRatio = null,
        ?array $durations = null,
    ): array {
        $durations ??= self::SIMULATION_DURATIONS;
        $results = [];

        foreach ($durations as $minutes) {
            $results[$minutes] = $this->estimateCost($model, (float) $minutes, $type, $customOutputRatio)['cost'];
        }

        return $results;
    }

    /** @return Collection<int, array{model: LlmModel, durations: array<int, float>, cost_per_minute: float, cost_per_hour: float}> */
    public function simulateAllModels(
        ConversationEstimateType $type = ConversationEstimateType::ShortSupport,
        ?float $customOutputRatio = null,
    ): Collection {
        return LlmModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (LlmModel $model) => [
                'model' => $model,
                'durations' => $this->simulateDurations($model, $type, $customOutputRatio),
                'cost_per_minute' => $this->costPerMinute($model, $type, $customOutputRatio),
                'cost_per_hour' => $this->costPerHour($model, $type, $customOutputRatio),
            ]);
    }

    /** @return array<string, mixed> */
    public function modelCostSummary(
        LlmModel $model,
        ConversationEstimateType $type = ConversationEstimateType::ShortSupport,
    ): array {
        $durations = [1, 10, 30, 60];

        return [
            'model' => $model,
            'input_price' => PlatformAiSettings::convertFromUnits((float) $model->input_price_per_million_tokens),
            'output_price' => PlatformAiSettings::convertFromUnits((float) $model->output_price_per_million_tokens),
            'cost_per_minute' => $this->costPerMinute($model, $type),
            'cost_per_hour' => $this->costPerHour($model, $type),
            'duration_estimates' => collect($durations)
                ->mapWithKeys(fn (int $minutes) => [
                    $minutes => $this->estimateCost($model, (float) $minutes, $type),
                ])
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function organizationForecast(int $organizationId): array
    {
        $model = app(LlmModelResolver::class)->resolveForOrganization($organizationId);
        $overview = app(AiUsageAnalyticsService::class)->organizationOverview(
            $organizationId,
            now()->subDays(30)->startOfDay(),
            now()->endOfDay(),
        );

        $costPerHour = $this->costPerHour($model);
        $tokensPerMinute = $this->wordsPerMinute() * $this->tokensPerWord();
        $estimatedMinutes = $overview['input_tokens'] > 0 && $tokensPerMinute > 0
            ? $overview['input_tokens'] / $tokensPerMinute
            : 0;
        $averageMonthlyHours = round($estimatedMinutes / 60, 1);

        return [
            'model_id' => $model->id,
            'model_name' => $model->name,
            'provider_name' => $model->provider?->name,
            'cost_per_hour' => $costPerHour,
            'average_monthly_hours' => $averageMonthlyHours,
            'estimated_monthly_spend' => round($costPerHour * $averageMonthlyHours, 2),
            'actual_monthly_spend' => $overview['total_cost'],
            'has_usage_data' => $overview['analyses_count'] > 0,
            'analyses_count' => $overview['analyses_count'],
        ];
    }
}
