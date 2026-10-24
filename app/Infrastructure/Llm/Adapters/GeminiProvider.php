<?php

namespace App\Infrastructure\Llm\Adapters;

use App\Application\Llm\Prompts\PromptBuilder;
use App\Application\Llm\Prompts\TranscriptionPrompt;
use App\Application\Llm\Services\AudioSegmenter;
use App\Application\Llm\Services\TranscriptSanitizer;
use App\Domain\Llm\Contracts\CompletesJsonPrompt;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\TranscriptionRequestData;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use App\Infrastructure\Llm\GeminiSystemPromptCache;
use App\Services\RecordingStorage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GeminiProvider extends AbstractLlmProvider implements CompletesJsonPrompt
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
        $response = Http::timeout(30)
            ->withHeaders(['x-goog-api-key' => $this->config->credentials->apiKey])
            ->get($this->modelsEndpoint($model));

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

        $response = $this->postGenerateContent(
            model: $model,
            promptBuilder: $promptBuilder,
            request: $request,
            audioBase64: $audioBase64,
            mimeType: $mimeType ?? $this->mimeTypeForFormat($audioFormat),
        );

        if (! $response->successful()) {
            return $this->failure('Gemini API error (HTTP '.$response->status().'): '.$response->body());
        }

        $body = $response->json();
        $content = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $parsed = $this->parseJsonResponse($content);

        if (! $parsed) {
            return $this->failure('Failed to parse Gemini audio analysis response.');
        }

        [$inputTokens, $outputTokens, $cachedTokens] = $this->usageTokens($body['usageMetadata'] ?? [], $parsed);

        return LlmOperationResult::success(
            data: $parsed,
            message: 'Audio analysis completed.',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: 0,
            durationMs: (int) ((microtime(true) - $started) * 1000),
            model: (string) ($parsed['model'] ?? $model),
            cachedTokens: $cachedTokens,
        );
    }

    public function transcribe(TranscriptionRequestData $request): LlmOperationResult
    {
        if (! $this->usesNativeGoogleEndpoint()) {
            $provider = new OpenAiProvider;
            $provider->configure($this->config);

            return $provider->transcribe($request);
        }

        $audioRequest = $request->asAudioRequest();
        $model = $this->resolveAudioAnalysisModel($request->model ?: $request->fallbackModel);

        if (! $this->hasApiKey()) {
            return $this->failure('برای تبدیل گفتار به متن، کلید API هوش مصنوعی باید در پنل ادمین تنظیم شود.');
        }

        if ($refused = $this->refuseIfRemoteDisabled()) {
            return $refused;
        }

        $audioFormat = $this->resolveAudioFormat($audioRequest);
        [$audioBase64, $mimeType] = $this->resolveInlineAudio($audioRequest, $audioFormat);

        if (! $audioBase64) {
            return $this->failure('فایل صوتی برای تبدیل به متن یافت نشد.');
        }

        $bytes = base64_decode($audioBase64, true);
        if (! is_string($bytes) || $bytes === '') {
            return $this->failure('فایل صوتی برای تبدیل به متن یافت نشد.');
        }

        $mimeType ??= $this->mimeTypeForFormat($audioFormat);
        $segments = app(AudioSegmenter::class)->segments($bytes, $audioFormat) ?? [$bytes];

        $started = microtime(true);
        $prompt = app(TranscriptionPrompt::class);
        $transcript = '';
        $inputTokens = 0;
        $outputTokens = 0;
        $cachedTokens = 0;

        foreach ($segments as $segment) {
            $piece = $this->transcribeSegment(
                request: $request,
                model: $model,
                mimeType: $mimeType,
                audioBase64: base64_encode($segment),
                prompt: $prompt,
                transcriptSoFar: $transcript,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                cachedTokens: $cachedTokens,
            );

            if ($piece instanceof LlmOperationResult) {
                return $piece;
            }

            $transcript = $prompt::append($transcript, $piece);
        }

        $transcript = app(TranscriptSanitizer::class)->clean($transcript);

        if (trim($transcript) === '') {
            return $this->failure('متن مکالمه از فایل صوتی استخراج نشد.');
        }

        return LlmOperationResult::success(
            data: [
                'transcript' => trim($transcript),
                'language' => $request->language,
            ],
            message: 'Transcription completed.',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            durationMs: (int) ((microtime(true) - $started) * 1000),
            model: $model,
            cachedTokens: $cachedTokens,
        );
    }

    public function completeJson(string $model, string $system, string $user, int $maxOutputTokens = 512): LlmOperationResult
    {
        if (! $this->usesNativeGoogleEndpoint()) {
            $provider = new OpenAiProvider;
            $provider->configure($this->config);

            return $provider->completeJson($model, $system, $user, $maxOutputTokens);
        }

        if (! $this->hasApiKey()) {
            return $this->failure('برای تحلیل واقعی تماس، کلید API هوش مصنوعی باید در پنل ادمین تنظیم شود.');
        }

        if ($refused = $this->refuseIfRemoteDisabled()) {
            return $refused;
        }

        $model = $this->resolveAudioAnalysisModel($model);
        $started = microtime(true);
        $response = Http::timeout(60)
            ->withHeaders(['x-goog-api-key' => $this->config->credentials->apiKey])
            ->post($this->generateContentEndpoint($model), [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $system],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $user],
                        ],
                    ],
                ],
                'generationConfig' => $this->jsonCompletionConfig($model, $maxOutputTokens),
            ]);

        if (! $response->successful()) {
            return $this->failure('Gemini JSON completion failed (HTTP '.$response->status().').');
        }

        $body = $response->json();
        $content = (string) ($body['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $parsed = $this->parseJsonResponse($content);

        if (! $parsed) {
            return $this->failure('Failed to parse Gemini JSON completion.');
        }

        [$inputTokens, $outputTokens, $cachedTokens] = $this->usageTokens($body['usageMetadata'] ?? [], $parsed);

        return LlmOperationResult::success(
            data: $parsed,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            durationMs: (int) ((microtime(true) - $started) * 1000),
            model: $model,
            cachedTokens: $cachedTokens,
        );
    }

    /**
     * One audio piece. A truncated reply continues on this piece only.
     * Token counters are updated by reference.
     */
    private function transcribeSegment(
        TranscriptionRequestData $request,
        string $model,
        string $mimeType,
        string $audioBase64,
        TranscriptionPrompt $prompt,
        string $transcriptSoFar,
        int &$inputTokens,
        int &$outputTokens,
        int &$cachedTokens,
    ): string|LlmOperationResult {
        $piece = '';
        $continuations = 0;
        $truncated = false;

        do {
            $response = Http::timeout(300)
                ->withHeaders(['x-goog-api-key' => $this->config->credentials->apiKey])
                ->post($this->generateContentEndpoint($model), [
                    'system_instruction' => [
                        'parts' => [
                            ['text' => $prompt::instructions()],
                        ],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt::userTurn($request->context, trim($transcriptSoFar."\n".$piece))],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $audioBase64,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => $this->transcriptionGenerationConfig($model),
                ]);

            if (! $response->successful()) {
                return $this->failure('Gemini transcription failed (HTTP '.$response->status().'): '.$response->body());
            }

            $body = $response->json();
            $candidate = $body['candidates'][0] ?? [];
            $content = is_array($candidate) ? (string) ($candidate['content']['parts'][0]['text'] ?? '') : '';
            $piece = $prompt::append($piece, $content);
            [$input, $output, $cached] = $this->usageTokens(is_array($body) ? ($body['usageMetadata'] ?? []) : []);
            $inputTokens += $input;
            $outputTokens += $output;
            $cachedTokens += $cached;
            $finishReason = is_array($candidate) ? ($candidate['finishReason'] ?? null) : null;
            $truncated = $prompt::isTruncated(is_string($finishReason) ? $finishReason : null);
            $continuations++;
        } while ($truncated && $continuations <= $prompt::maxContinuations());

        if ($truncated) {
            return $this->failure('رونویسی تماس ناقص ماند و برای تحلیل ارسال نشد.');
        }

        return $piece;
    }

    private function postGenerateContent(
        string $model,
        PromptBuilder $promptBuilder,
        AudioAnalysisRequestData $request,
        ?string $audioBase64,
        string $mimeType,
    ): Response {
        $systemPrompt = $promptBuilder->systemPromptFor($request);

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

        $cache = app(GeminiSystemPromptCache::class);
        $apiKey = (string) $this->config->credentials->apiKey;
        $cacheName = $cache->get($model, $systemPrompt, $apiKey, $this->resolveBaseUrl());
        $response = $this->sendGenerateContent($model, $userParts, $systemPrompt, $cacheName);

        if ($cacheName !== null && $this->cachedPromptRejected($response)) {
            $cache->forget($model, $systemPrompt);
            $response = $this->sendGenerateContent($model, $userParts, $systemPrompt, null);
        }

        return $response;
    }

    /**
     * @param  list<array<string, mixed>>  $userParts
     */
    private function sendGenerateContent(string $model, array $userParts, string $systemPrompt, ?string $cacheName): Response
    {
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $userParts,
                ],
            ],
            'generationConfig' => $this->generationConfig($model),
        ];

        if ($cacheName !== null) {
            $payload['cachedContent'] = $cacheName;
        } else {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ];
        }

        return Http::timeout(300)
            ->withHeaders(['x-goog-api-key' => $this->config->credentials->apiKey])
            ->post($this->generateContentEndpoint($model), $payload);
    }

    private function cachedPromptRejected(Response $response): bool
    {
        if ($response->successful()) {
            return false;
        }

        $body = mb_strtolower($response->body());

        return $response->status() === 404
            || str_contains($body, 'cachedcontent')
            || str_contains($body, 'cached content');
    }

    /**
     * @param  array<string, mixed>  $usage
     * @param  array<string, mixed>  $parsed
     * @return array{0: int, 1: int, 2: int}
     */
    private function usageTokens(array $usage, array $parsed = []): array
    {
        $input = (int) ($usage['promptTokenCount'] ?? $parsed['input_tokens'] ?? 0);
        $cached = (int) ($usage['cachedContentTokenCount'] ?? 0);
        $output = (int) ($usage['candidatesTokenCount'] ?? $parsed['output_tokens'] ?? 0)
            + (int) ($usage['thoughtsTokenCount'] ?? 0);

        return [$input, $output, max(0, min($cached, $input))];
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
            $payload = app(RecordingStorage::class)->base64ForAnalysis(
                $request->storagePath,
                $request->storageDisk,
            );

            $format = $payload['format'];

            return [
                $payload['content'],
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

    private function usesNativeGoogleEndpoint(): bool
    {
        $baseUrl = strtolower((string) ($this->config->credentials->baseUrl ?? ''));

        return $baseUrl === ''
            || str_contains($baseUrl, 'generativelanguage.googleapis.com')
            || str_ends_with(rtrim((string) parse_url($baseUrl, PHP_URL_PATH), '/'), '/v1beta');
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
    private function jsonCompletionConfig(string $model, int $maxOutputTokens): array
    {
        $config = [
            'maxOutputTokens' => max(1, $maxOutputTokens),
            'responseMimeType' => 'application/json',
        ];

        if ($this->isGemini3($model)) {
            $config['thinkingConfig'] = [
                'thinkingLevel' => str_starts_with($model, 'gemini-3.8') ? 'low' : 'minimal',
            ];

            return $config;
        }

        $config['temperature'] = 0;

        return $config;
    }

    /** @return array<string, mixed> */
    private function transcriptionGenerationConfig(string $model): array
    {
        $config = [
            'maxOutputTokens' => $this->config->settings->maxOutputTokens ?? 16384,
            'responseMimeType' => 'text/plain',
        ];

        if ($this->isGemini3($model)) {
            $config['thinkingConfig'] = [
                'thinkingLevel' => str_starts_with($model, 'gemini-3.8') ? 'low' : 'minimal',
            ];

            return $config;
        }

        $config['temperature'] = 0;

        return $config;
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
                'thinkingLevel' => 'low',
            ];

            return $config;
        }

        $config['temperature'] = $this->config->settings->temperature ?? 0;

        return $config;
    }

    private function isGemini3(string $model): bool
    {
        return str_starts_with($model, 'gemini-3');
    }
}
