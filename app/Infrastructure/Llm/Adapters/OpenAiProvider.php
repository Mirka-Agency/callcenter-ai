<?php

namespace App\Infrastructure\Llm\Adapters;

use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use App\Services\RecordingStorage;
use Illuminate\Support\Facades\Http;

class OpenAiProvider extends AbstractLlmProvider
{
    public function getProviderCode(): LlmProviderCode
    {
        return LlmProviderCode::OpenAi;
    }

    public function supportedModels(): array
    {
        return ['gpt-4o', 'gpt-4o-mini', 'gpt-4o-audio-preview', 'gpt-4-turbo'];
    }

    public function testConnection(): LlmOperationResult
    {
        if (! $this->hasApiKey()) {
            return LlmOperationResult::success(message: 'OpenAI configured in demo mode (no API key).');
        }

        $client = new \App\Infrastructure\Llm\Clients\OpenAiApiClient(
            apiKey: $this->config->credentials->apiKey,
            baseUrl: $this->config->credentials->baseUrl,
        );

        return $client->testConnection();
    }

    public function analyzeAudio(AudioAnalysisRequestData $request): LlmOperationResult
    {
        $model = $this->resolveAudioAnalysisModel($request->model);

        if ($this->hasRealAudio($request) && ! $this->hasApiKey()) {
            return $this->failure('برای تحلیل واقعی تماس، کلید API هوش مصنوعی باید در پنل ادمین تنظیم شود.');
        }

        if (! $this->hasApiKey()) {
            return $this->demoAudioAnalysis($request, $model);
        }

        $started = microtime(true);
        $promptBuilder = app(\App\Application\Llm\Services\PromptBuilder::class);

        [$audioBase64, $audioFormat] = $this->resolveAudioPayload($request);

        if ($this->hasRealAudio($request) && ! $audioBase64) {
            return $this->failure('فایل صوتی برای تحلیل یافت نشد.');
        }

        $guard = app(\App\Services\PersianOutputGuard::class);
        $parsed = null;
        $body = null;

        foreach ([false, true] as $strictPersian) {
            $messages = $promptBuilder->buildAudioMessages($request, $audioBase64, $audioFormat, $strictPersian);

            $response = Http::withToken($this->config->credentials->apiKey)
                ->timeout(300)
                ->post(rtrim($this->resolveBaseUrl(), '/').'/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $this->config->settings->temperature ?? 0.3,
                    'max_tokens' => $this->config->settings->maxOutputTokens ?? 2000,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                $status = $response->status();

                if (in_array($status, [429, 502, 503, 504], true)) {
                    return $this->failure('OpenAI API error (HTTP '.$status.'): '.$response->body());
                }

                return $this->failure('OpenAI API error: '.$response->body());
            }

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? '';
            $parsed = $this->parseJsonResponse($content);

            if (! $parsed) {
                return $this->failure('Failed to parse OpenAI audio analysis response.');
            }

            if (! $guard->containsEnglish($parsed)) {
                break;
            }
        }

        if ($parsed && $guard->containsEnglish($parsed)) {
            \Illuminate\Support\Facades\Log::warning('AI analysis still contains English after Persian retry', [
                'call_id' => $request->callId,
            ]);
        }

        $inputTokens = (int) ($body['usage']['prompt_tokens'] ?? $parsed['input_tokens'] ?? 0);
        $outputTokens = (int) ($body['usage']['completion_tokens'] ?? $parsed['output_tokens'] ?? 0);

        return LlmOperationResult::success(
            data: $parsed,
            message: 'Audio analysis completed.',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: 0,
            durationMs: (int) ((microtime(true) - $started) * 1000),
            model: (string) ($parsed['model'] ?? $model),
        );
    }

    /** @return array{0: ?string, 1: string} */
    private function resolveAudioPayload(AudioAnalysisRequestData $request): array
    {
        if ($request->storagePath) {
            $payload = app(RecordingStorage::class)->readForAnalysis(
                $request->storagePath,
                $request->storageDisk,
            );

            return [base64_encode($payload['content']), $payload['format']];
        }

        if ($request->recordingUrl) {
            $response = Http::timeout(120)->get($request->recordingUrl);

            if ($response->successful()) {
                $format = strtolower(pathinfo(parse_url($request->recordingUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'mp3';

                return [base64_encode($response->body()), $format];
            }
        }

        return [null, 'mp3'];
    }

    protected function resolveBaseUrl(): string
    {
        return $this->config->credentials->baseUrl ?? 'https://api.openai.com/v1';
    }

    private function resolveAudioAnalysisModel(?string $requestedModel): string
    {
        $model = $this->resolveModel($requestedModel, 'gpt-4o-audio-preview');
        $audioCapableModels = [
            'gpt-4o-audio-preview',
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4o-mini-audio-preview',
            'gpt-4-turbo',
        ];

        if (in_array($model, $audioCapableModels, true)) {
            return $model;
        }

        return 'gpt-4o-audio-preview';
    }
}
