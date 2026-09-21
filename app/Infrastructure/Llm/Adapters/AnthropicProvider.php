<?php

namespace App\Infrastructure\Llm\Adapters;

use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use Illuminate\Support\Facades\Http;

class AnthropicProvider extends AbstractLlmProvider
{
    public function getProviderCode(): LlmProviderCode
    {
        return LlmProviderCode::Anthropic;
    }

    public function supportedModels(): array
    {
        return ['claude-sonnet-4-20250514', 'claude-3-5-sonnet-latest', 'claude-3-haiku-20240307'];
    }

    public function testConnection(): LlmOperationResult
    {
        if ($refused = $this->refuseIfRemoteDisabled()) {
            return $refused;
        }

        if (! $this->hasApiKey()) {
            return LlmOperationResult::success(message: 'Anthropic configured in demo mode (no API key).');
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->config->credentials->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->get($this->config->credentials->baseUrl ?? 'https://api.anthropic.com/v1/models');

        return $response->successful()
            ? LlmOperationResult::success(message: 'Anthropic connection successful.')
            : $this->failure('Anthropic connection failed.');
    }

    public function analyzeAudio(AudioAnalysisRequestData $request): LlmOperationResult
    {
        $model = $this->resolveModel($request->model, 'claude-3-5-sonnet-latest');

        if ($refused = $this->refuseDemoAnalysis($request, $model)) {
            return $refused;
        }

        return $this->demoAudioAnalysis($request, $model);
    }
}
