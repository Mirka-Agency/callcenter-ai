<?php

namespace App\Infrastructure\Llm\Adapters;

use App\Application\Llm\Services\PromptBuilder;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use App\Services\PersianOutputGuard;
use App\Services\RecordingStorage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider extends AbstractLlmProvider
{
    public const DEFAULT_MODEL = 'gemini-3.8-flash';

    public function getProviderCode(): LlmProviderCode
    {
        return LlmProviderCode::Gemini;
    }

    public function supportedModels(): array
    {
        return [
            'gemini-3.8-flash',
            'gemini-3.7-flash',
            'gemini-3.6-flash',
            'gemini-3.5-flash',
            'gemini-3.5-flash-lite',
            'gemini-3.1-pro-preview',
            'gemini-3.1-flash-lite',
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-2.0-flash-lite',
            'gemini-1.5-pro',
            'gemini-1.5-flash',
            'gemini-1.5-flash-8b',
        ];
    }

    public function testConnection(): LlmOperationResult
    {
        if ($refused = $this->refuseIfRemoteDisabled()) {
            return $refused;
        }

        if (! $this->hasApiKey()) {
            return LlmOperationResult::success(message: 'Gemini configured in demo mode (no API key).');
        }

        $model = $this->resolveModel(null, self::DEFAULT_MODEL);
        $response = Http::timeout(30)->get(
            $this->modelsEndpoint($model),
            ['key' => $this->config->credentials->apiKey],
        );

        return $response->successful()
            ? LlmOperationResult::success(message: 'Gemini connection successful.')
            : $this->failure('Gemini connection failed: '.$response->body());
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

        [$audioBase64, $mimeType] = $this->resolveInlineAudio($request, $audioFormat);

        if ($this->hasRealAudio($request) && ! $audioBase64) {
            return $this->failure('فایل صوتی برای تحلیل یافت نشد.');
        }

        $guard = app(PersianOutputGuard::class);
        $response = $this->postGenerateContent(
            model: $model,
            promptBuilder: $promptBuilder,
            request: $request,
            audioBase64: $audioBase64,
            mimeType: $mimeType ?? $this->mimeTypeForFormat($audioFormat),
        );

        if (! $response->successful()) {
            $status = $response->status();

            if (in_array($status, [429, 502, 503, 504], true)) {
                return $this->failure('Gemini API error (HTTP '.$status.'): '.$response->body());
            }

            return $this->failure('Gemini API error: '.$response->body());
        }

        $body = $response->json();
        $content = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $parsed = $this->parseJsonResponse($content);

        if (! $parsed) {
            return $this->failure('Failed to parse Gemini audio analysis response.');
        }

        if ($guard->containsEnglish($parsed)) {
            Log::warning('Gemini analysis contains English and was stored without a second model request', [
                'call_id' => $request->callId,
            ]);
        }

        $usage = $body['usageMetadata'] ?? [];
        $outputTokens = (int) ($usage['candidatesTokenCount'] ?? $parsed['output_tokens'] ?? 0)
            + (int) ($usage['thoughtsTokenCount'] ?? 0);

        return LlmOperationResult::success(
            data: $parsed,
            message: 'Audio analysis completed.',
            inputTokens: (int) ($usage['promptTokenCount'] ?? $parsed['input_tokens'] ?? 0),
            outputTokens: $outputTokens,
            cost: 0,
            durationMs: (int) ((microtime(true) - $started) * 1000),
            model: (string) ($parsed['model'] ?? $model),
        );
    }

    private function postGenerateContent(
        string $model,
        PromptBuilder $promptBuilder,
        AudioAnalysisRequestData $request,
        ?string $audioBase64,
        string $mimeType,
    ): Response {
        $systemPrompt = $promptBuilder->systemPrompt($request->promptVersion);

        $userParts = [
            ['text' => $promptBuilder->contextPrompt($request)],
        ];

        if ($audioBase64) {
            $userParts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $audioBase64,
                ],
            ];
        }

        return Http::timeout(300)
            ->withQueryParameters(['key' => $this->config->credentials->apiKey])
            ->post($this->generateContentEndpoint($model), [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemPrompt],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => $userParts,
                    ],
                ],
                'generationConfig' => $this->generationConfig($model),
            ]);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function resolveInlineAudio(AudioAnalysisRequestData $request, string $audioFormat): array
    {
        if ($request->sendAudioFile) {
            return $this->resolveAudioFromStorageOrUrl($request);
        }

        if ($request->playbackUrl) {
            return $this->resolveAudioFromHttpUrl($request->playbackUrl, $request->mimeType, $audioFormat);
        }

        if ($request->recordingUrl) {
            return $this->resolveAudioFromHttpUrl($request->recordingUrl, $request->mimeType, $audioFormat);
        }

        return [null, null];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function resolveAudioFromStorageOrUrl(AudioAnalysisRequestData $request): array
    {
        if ($request->storagePath) {
            $payload = app(RecordingStorage::class)->readForAnalysis(
                $request->storagePath,
                $request->storageDisk,
            );

            $format = $payload['format'];

            return [
                base64_encode($payload['content']),
                $request->mimeType ?? $this->mimeTypeForFormat($format),
            ];
        }

        if ($request->recordingUrl) {
            return $this->resolveAudioFromHttpUrl(
                $request->recordingUrl,
                $request->mimeType,
                $this->resolveAudioFormat($request),
            );
        }

        return [null, null];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function resolveAudioFromHttpUrl(string $url, ?string $mimeType, string $audioFormat): array
    {
        $response = Http::timeout(120)->get($url);

        if (! $response->successful()) {
            return [null, null];
        }

        return [
            base64_encode($response->body()),
            $mimeType ?? $this->mimeTypeForFormat($audioFormat),
        ];
    }

    private function resolveAudioFormat(AudioAnalysisRequestData $request): string
    {
        if ($request->storagePath) {
            return strtolower(pathinfo($request->storagePath, PATHINFO_EXTENSION)) ?: 'mp3';
        }

        foreach ([$request->recordingUrl, $request->playbackUrl] as $url) {
            if ($url) {
                return strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'mp3';
            }
        }

        return 'mp3';
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

    private function resolveBaseUrl(): string
    {
        return rtrim($this->config->credentials->baseUrl ?? 'https://generativelanguage.googleapis.com/v1beta', '/');
    }

    private function modelsEndpoint(string $model): string
    {
        return $this->resolveBaseUrl().'/models/'.$model;
    }

    private function generateContentEndpoint(string $model): string
    {
        return $this->modelsEndpoint($model).':generateContent';
    }

    private function resolveAudioAnalysisModel(?string $requestedModel): string
    {
        $model = $this->resolveModel($requestedModel, self::DEFAULT_MODEL);

        if (in_array($model, $this->supportedModels(), true) || str_starts_with($model, 'gemini-')) {
            return $model;
        }

        return self::DEFAULT_MODEL;
    }

    /** @return array<string, mixed> */
    private function generationConfig(string $model): array
    {
        $config = [
            'maxOutputTokens' => $this->config->settings->maxOutputTokens ?? ($this->isGemini3($model) ? 16384 : 8192),
            'responseMimeType' => 'application/json',
        ];

        if ($this->isGemini3($model)) {
            $config['thinkingConfig'] = [
                'thinkingLevel' => 'medium',
            ];

            return $config;
        }

        $config['temperature'] = $this->config->settings->temperature ?? 0.3;

        return $config;
    }

    private function isGemini3(string $model): bool
    {
        return str_starts_with($model, 'gemini-3');
    }
}
