<?php

namespace App\Infrastructure\Llm\Adapters;

use App\Application\Llm\Services\PromptBuilder;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use Illuminate\Support\Facades\Http;

class CustomLlmProvider extends AbstractLlmProvider
{
    public function getProviderCode(): LlmProviderCode
    {
        return LlmProviderCode::Custom;
    }

    public function supportedModels(): array
    {
        return ['custom-model'];
    }

    public function testConnection(): LlmOperationResult
    {
        if ($refused = $this->refuseIfRemoteDisabled()) {
            return $refused;
        }

        $baseUrl = $this->config->credentials->baseUrl;

        if (! $baseUrl) {
            return LlmOperationResult::success(message: 'Custom provider configured in demo mode.');
        }

        $response = Http::withToken($this->config->credentials->apiKey ?? '')
            ->get(rtrim($baseUrl, '/').'/health');

        return $response->successful()
            ? LlmOperationResult::success(message: 'Custom provider connection successful.')
            : $this->failure('Custom provider connection failed.');
    }

    public function analyzeAudio(AudioAnalysisRequestData $request): LlmOperationResult
    {
        $model = $this->resolveModel($request->model, 'custom-model');
        $baseUrl = $this->config->credentials->baseUrl;

        if (! $baseUrl || ! $this->hasApiKey()) {
            if ($refused = $this->refuseDemoAnalysis($request, $model)) {
                return $refused;
            }

            return $this->demoAudioAnalysis($request, $model);
        }

        if ($refused = $this->refuseIfRemoteDisabled()) {
            return $refused;
        }

        $started = microtime(true);
        $promptBuilder = app(PromptBuilder::class);

        $response = Http::withToken($this->config->credentials->apiKey)
            ->timeout(300)
            ->post(rtrim($baseUrl, '/').'/analyze-audio', [
                'call_id' => $request->callId,
                'storage_path' => $request->storagePath,
                'storage_disk' => $request->storageDisk,
                'recording_url' => $request->recordingUrl,
                'model' => $model,
                'system_prompt' => $promptBuilder->systemPrompt($request->promptVersion),
                'context_prompt' => $promptBuilder->contextPrompt($request),
            ]);

        if (! $response->successful()) {
            return $this->failure('Custom provider audio analysis failed.');
        }

        $body = $response->json();

        return LlmOperationResult::success(
            data: $body['analysis'] ?? $body,
            inputTokens: (int) ($body['input_tokens'] ?? 0),
            outputTokens: (int) ($body['output_tokens'] ?? 0),
            cost: (float) ($body['cost'] ?? 0),
            durationMs: (int) ((microtime(true) - $started) * 1000),
            model: $model,
        );
    }
}
