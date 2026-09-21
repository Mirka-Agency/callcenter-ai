<?php

namespace App\Infrastructure\Llm\Adapters;

use App\Application\Llm\Services\PromptBuilder;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use App\Infrastructure\Llm\Clients\OpenAiApiClient;
use App\Services\PersianOutputGuard;
use App\Services\RecordingStorage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        if ($refused = $this->refuseIfRemoteDisabled()) {
            return $refused;
        }

        if (! $this->hasApiKey()) {
            return LlmOperationResult::success(message: 'OpenAI configured in demo mode (no API key).');
        }

        $client = new OpenAiApiClient(
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

        if ($refused = $this->refuseIfRemoteDisabled()) {
            return $refused;
        }

        $started = microtime(true);
        $promptBuilder = app(PromptBuilder::class);

        $audioFormat = $this->resolveAudioFormat($request);
        [$audioBase64, $audioFormat, $mimeType] = $request->sendAudioFile
            ? $this->resolveAudioPayload($request)
            : [null, $audioFormat, null];

        if ($this->hasRealAudio($request) && $request->sendAudioFile && ! $audioBase64) {
            return $this->failure('فایل صوتی برای تحلیل یافت نشد.');
        }

        if ($this->hasRealAudio($request) && ! $request->sendAudioFile && ! $request->playbackUrl) {
            return $this->failure('آدرس قابل‌دسترس فایل صوتی برای تحلیل یافت نشد.');
        }

        $guard = app(PersianOutputGuard::class);
        $messages = $promptBuilder->buildAudioMessages(
            $request,
            $audioFormat,
            false,
            $request->sendAudioFile ? null : $request->playbackUrl,
            $audioBase64,
            $mimeType,
        );

        $response = $this->postChatCompletion($messages, $model);

        if (! $response->successful()) {
            return $this->failure('OpenAI API error (HTTP '.$response->status().'): '.$response->body());
        }

        $body = $response->json();
        $content = $body['choices'][0]['message']['content'] ?? '';
        $parsed = $this->parseJsonResponse($content);

        if (! $parsed) {
            return $this->failure('Failed to parse OpenAI audio analysis response.');
        }

        if ($guard->containsEnglish($parsed)) {
            Log::warning('AI analysis contains English and was stored without a second model request', [
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

    /** @return array{0: ?string, 1: string, 2: ?string} */
    private function resolveAudioPayload(AudioAnalysisRequestData $request): array
    {
        if ($request->storagePath) {
            $payload = app(RecordingStorage::class)->readForAnalysis(
                $request->storagePath,
                $request->storageDisk,
            );

            $format = $payload['format'];

            return [
                base64_encode($payload['content']),
                $format,
                $request->mimeType ?? $this->mimeTypeForFormat($format),
            ];
        }

        if ($request->recordingUrl) {
            $response = Http::timeout(120)->get($request->recordingUrl);

            if ($response->successful()) {
                $format = strtolower(pathinfo(parse_url($request->recordingUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'mp3';

                return [
                    base64_encode($response->body()),
                    $format,
                    $request->mimeType ?? $this->mimeTypeForFormat($format),
                ];
            }
        }

        return [null, 'mp3', null];
    }

    /** @param  list<array{role: string, content: mixed}>  $messages */
    private function postChatCompletion(array $messages, string $model): Response
    {
        return Http::withToken($this->config->credentials->apiKey)
            ->timeout(300)
            ->post(rtrim($this->resolveBaseUrl(), '/').'/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $this->config->settings->temperature ?? 0.3,
                'max_tokens' => $this->config->settings->maxOutputTokens ?? 2000,
                'response_format' => ['type' => 'json_object'],
            ]);
    }

    private function mimeTypeForFormat(string $format): string
    {
        return match (strtolower($format)) {
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'webm' => 'audio/webm',
            'm4a', 'mp4' => 'audio/mp4',
            default => 'audio/mpeg',
        };
    }

    private function resolveAudioFormat(AudioAnalysisRequestData $request): string
    {
        if ($request->storagePath) {
            return strtolower(pathinfo($request->storagePath, PATHINFO_EXTENSION)) ?: 'mp3';
        }

        if ($request->recordingUrl) {
            return strtolower(pathinfo(parse_url($request->recordingUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'mp3';
        }

        if ($request->playbackUrl) {
            return strtolower(pathinfo(parse_url($request->playbackUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'mp3';
        }

        return 'mp3';
    }

    protected function resolveBaseUrl(): string
    {
        return $this->config->credentials->baseUrl ?? 'https://api.openai.com/v1';
    }

    private function resolveAudioAnalysisModel(?string $requestedModel): string
    {
        $model = $this->resolveModel($requestedModel, 'gpt-4o-audio-preview');

        if ($this->usesIntermediaryEndpoint() || str_contains($model, '/')) {
            return $model;
        }

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

    private function usesIntermediaryEndpoint(): bool
    {
        $baseUrl = $this->config->credentials->baseUrl;

        if (! filled($baseUrl)) {
            return false;
        }

        return ! str_contains(rtrim(strtolower($baseUrl), '/'), 'api.openai.com');
    }
}
