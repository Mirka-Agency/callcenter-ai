<?php

namespace App\Infrastructure\Llm\Adapters;

use App\Application\Llm\Services\PromptBuilder;
use App\Application\Llm\Services\TranscriptionPrompt;
use App\Application\Llm\Services\TranscriptSanitizer;
use App\Domain\Llm\Contracts\CompletesJsonPrompt;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\TranscriptionRequestData;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use App\Infrastructure\Llm\Clients\OpenAiApiClient;
use App\Services\PersianOutputGuard;
use App\Services\RecordingStorage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiProvider extends AbstractLlmProvider implements CompletesJsonPrompt
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
        $choice = $body['choices'][0] ?? [];
        $content = $this->messageContent($choice);
        $parsed = $this->parseJsonResponse($content);

        if (! $parsed && is_array($choice)) {
            $reasoning = $choice['message']['reasoning_content'] ?? '';
            if (is_string($reasoning) && trim($reasoning) !== '') {
                $parsed = $this->parseJsonResponse($reasoning);
            }
        }

        if (! $parsed) {
            Log::warning('OpenAI audio analysis JSON parse failed', [
                'call_id' => $request->callId,
                'model' => $model,
                'finish_reason' => is_array($choice) ? ($choice['finish_reason'] ?? null) : null,
                'usage' => $body['usage'] ?? null,
                'content_head' => mb_substr($content, 0, 500),
            ]);

            return $this->failure('Failed to parse OpenAI audio analysis response.');
        }

        if ($guard->containsEnglish($parsed)) {
            Log::warning('AI analysis contains English and was stored without a second model request', [
                'call_id' => $request->callId,
            ]);
        }

        [$inputTokens, $outputTokens, $cachedTokens] = $this->usageTokens(is_array($body) ? ($body['usage'] ?? []) : [], $parsed);

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

    public function completeJson(string $model, string $system, string $user, int $maxOutputTokens = 512): LlmOperationResult
    {
        if (! $this->hasApiKey()) {
            return $this->failure('برای تحلیل واقعی تماس، کلید API هوش مصنوعی باید در پنل ادمین تنظیم شود.');
        }

        if ($refused = $this->refuseIfRemoteDisabled()) {
            return $refused;
        }

        $started = microtime(true);
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => 0,
            'max_tokens' => max(1, $maxOutputTokens),
            'response_format' => ['type' => 'json_object'],
        ];

        if ($this->isDeepSeekModel($model)) {
            $payload['thinking'] = ['type' => 'disabled'];
            $payload['reasoning_effort'] = 'none';
        }

        $response = Http::withToken($this->config->credentials->apiKey)
            ->timeout(60)
            ->post(rtrim($this->resolveBaseUrl(), '/').'/chat/completions', $payload);

        if (! $response->successful()) {
            return $this->failure('JSON completion failed (HTTP '.$response->status().').');
        }

        $body = $response->json();
        $choice = is_array($body) ? ($body['choices'][0] ?? []) : [];
        $parsed = $this->parseJsonResponse($this->messageContent($choice));

        if (! $parsed) {
            return $this->failure('Failed to parse JSON completion.');
        }

        [$inputTokens, $outputTokens, $cachedTokens] = $this->usageTokens(is_array($body) ? ($body['usage'] ?? []) : [], $parsed);

        return LlmOperationResult::success(
            data: $parsed,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            durationMs: (int) ((microtime(true) - $started) * 1000),
            model: $model,
            cachedTokens: $cachedTokens,
        );
    }

    public function transcribe(TranscriptionRequestData $request): LlmOperationResult
    {
        if (! $this->hasApiKey()) {
            return $this->failure('برای تبدیل گفتار به متن، کلید API هوش مصنوعی باید در پنل ادمین تنظیم شود.');
        }

        if ($refused = $this->refuseIfRemoteDisabled()) {
            return $refused;
        }

        return $this->transcribeWithSpeechApi($request, $this->speechTranscriptionModel($request));
    }

    /**
     * @param  array<string, mixed>  $usage
     * @param  array<string, mixed>  $parsed
     * @return array{0: int, 1: int, 2: int}
     */
    private function usageTokens(array $usage, array $parsed = []): array
    {
        $input = (int) ($usage['prompt_tokens'] ?? $parsed['input_tokens'] ?? 0);
        $output = (int) ($usage['completion_tokens'] ?? $parsed['output_tokens'] ?? 0);
        $details = $usage['prompt_tokens_details'] ?? [];
        $cached = (int) (is_array($details) ? ($details['cached_tokens'] ?? 0) : 0);

        if ($cached === 0) {
            $cached = (int) ($usage['prompt_cache_hit_tokens'] ?? 0);
        }

        return [$input, $output, max(0, min($cached, $input))];
    }

    /** @return array{0: ?string, 1: string, 2: ?string} */
    private function resolveAudioPayload(AudioAnalysisRequestData $request): array
    {
        if ($request->storagePath) {
            $payload = app(RecordingStorage::class)->base64ForAnalysis(
                $request->storagePath,
                $request->storageDisk,
            );

            $format = $payload['format'];

            return [
                $payload['content'],
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
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $this->config->settings->temperature ?? 0.3,
            'max_tokens' => $this->resolveMaxOutputTokens($model),
            'response_format' => ['type' => 'json_object'],
        ];

        if ($this->isDeepSeekModel($model)) {
            // Thinking is on by default and shares max_tokens with the answer.
            // A long chain of thought leaves the JSON empty or cut off.
            $payload['thinking'] = ['type' => 'disabled'];
            $payload['reasoning_effort'] = 'none';
        }

        return Http::withToken($this->config->credentials->apiKey)
            ->timeout(300)
            ->post(rtrim($this->resolveBaseUrl(), '/').'/chat/completions', $payload);
    }

    private function isDeepSeekModel(string $model): bool
    {
        return str_contains(strtolower($model), 'deepseek');
    }

    private function messageContent(mixed $choice): string
    {
        if (! is_array($choice)) {
            return '';
        }

        $content = $choice['message']['content'] ?? '';

        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return '';
        }

        $parts = [];

        foreach ($content as $part) {
            if (is_string($part)) {
                $parts[] = $part;

                continue;
            }

            if (is_array($part) && is_string($part['text'] ?? null)) {
                $parts[] = $part['text'];
            }
        }

        return implode('', $parts);
    }

    /**
     * Gemini (especially via AvalAI) spends many completion tokens on reasoning.
     * A 2000 cap truncates JSON mid-object and surfaces as parse failures.
     */
    private function resolveMaxOutputTokens(string $model): int
    {
        if ($this->config->settings->maxOutputTokens !== null) {
            return max(1, (int) $this->config->settings->maxOutputTokens);
        }

        $normalized = strtolower($model);

        if (str_contains($normalized, 'gemini-3') || str_contains($normalized, 'gemini/3')) {
            return 16384;
        }

        if (str_contains($normalized, 'deepseek')) {
            return 16384;
        }

        if (str_contains($normalized, 'gemini') || $this->usesIntermediaryEndpoint()) {
            return 8192;
        }

        return 4096;
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

    private function speechTranscriptionModel(TranscriptionRequestData $request): string
    {
        $requested = trim((string) ($request->model ?? ''));

        if ($requested !== '') {
            return $requested;
        }

        $fromSettings = trim((string) ($this->config->settings->transcriptionModel ?? ''));

        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return (string) config('llm.transcription.model', 'whisper-1');
    }

    /**
     * gpt-4o-transcribe and ElevenLabs Scribe reject verbose_json.
     * Whisper copies the prompt into the transcript, so an instruction sentence must not be sent to it.
     * Admin may store scribe-v2; AvalAI's transcription id is scribe_v2.
     *
     * @return array<string, string>
     */
    private function speechTranscriptionFields(TranscriptionRequestData $request, string $model): array
    {
        $fields = [
            'model' => $model,
            'language' => $request->language ?: (string) config('llm.transcription.language', 'fa'),
            'response_format' => $this->speechResponseFormat($model),
        ];

        if ($this->isGptTranscribeModel($model)) {
            $fields['prompt'] = TranscriptionPrompt::speechApiPrompt();
            $fields['include[]'] = 'logprobs';
        }

        return $fields;
    }

    private function speechResponseFormat(string $model): string
    {
        if ($this->isGptTranscribeModel($model) || $this->isScribeModel($model)) {
            return 'json';
        }

        return 'verbose_json';
    }

    /**
     * scribe-v2 is the admin spelling of AvalAI / ElevenLabs scribe_v2.
     */
    private function canonicalSpeechModel(string $model): string
    {
        $canonical = match ($this->speechModelKey($model)) {
            'scribe-v1', 'scribe_v1' => 'scribe_v1',
            'scribe-v2', 'scribe_v2' => 'scribe_v2',
            default => null,
        };

        if ($canonical === null || $canonical === $this->speechModelKey($model)) {
            return $model;
        }

        $separator = strrpos($model, '/');

        if ($separator === false) {
            return $canonical;
        }

        return substr($model, 0, $separator + 1).$canonical;
    }

    private function speechModelKey(string $model): string
    {
        $key = strtolower(trim($model));

        return str_contains($key, '/') ? (string) substr($key, strrpos($key, '/') + 1) : $key;
    }

    private function isGptTranscribeModel(string $model): bool
    {
        return in_array($this->speechModelKey($model), ['gpt-4o-transcribe', 'gpt-4o-mini-transcribe'], true);
    }

    private function isScribeModel(string $model): bool
    {
        return in_array($this->speechModelKey($model), ['scribe-v1', 'scribe_v1', 'scribe-v2', 'scribe_v2'], true);
    }

    private function transcribeWithSpeechApi(TranscriptionRequestData $request, string $requestedModel): LlmOperationResult
    {
        $audio = $this->readRecordingBytes($request);

        if ($audio === null) {
            return $this->failure('فایل صوتی برای تبدیل به متن یافت نشد.');
        }

        [$bytes, $format] = $audio;

        if (strlen($bytes) > 24 * 1024 * 1024) {
            return $this->failure('حجم فایل صوتی برای تبدیل گفتار به متن بیشتر از حد مجاز است.');
        }

        $started = microtime(true);
        $client = new OpenAiApiClient(
            apiKey: $this->config->credentials->apiKey,
            baseUrl: $this->resolveBaseUrl(),
        );

        $model = $this->canonicalSpeechModel($requestedModel);
        $fields = $this->speechTranscriptionFields($request, $model);
        $result = $client->transcribeFile($bytes, 'recording.'.$format, $fields);

        if (! $result->success && isset($fields['include[]']) && $this->providerRejectedLogprobs($result)) {
            unset($fields['include[]']);
            $result = $client->transcribeFile($bytes, 'recording.'.$format, $fields);
        }

        if (! $result->success && $model !== $requestedModel && $this->providerRejectedModel($result)) {
            $fields['model'] = $requestedModel;
            $result = $client->transcribeFile($bytes, 'recording.'.$format, $fields);

            if ($result->success) {
                $model = $requestedModel;
            }
        }

        if (! $result->success || ! is_array($result->data)) {
            return $result;
        }

        $sanitizer = app(TranscriptSanitizer::class);
        $transcript = $this->formatSpeechTranscript($result->data);

        if ($transcript === '') {
            return $this->failure('متن مکالمه از فایل صوتی استخراج نشد.');
        }

        $speakers = array_values(array_filter(
            $sanitizer->reliableSegments($this->speechSegments($result->data)),
            fn (array $segment): bool => $sanitizer->clean((string) ($segment['text'] ?? '')) !== '',
        ));

        return LlmOperationResult::success(
            data: [
                'transcript' => $transcript,
                'language' => $result->data['language'] ?? $request->language,
                'speakers' => $speakers,
            ],
            message: 'Transcription completed.',
            inputTokens: $result->inputTokens,
            outputTokens: $result->outputTokens,
            durationMs: (int) ((microtime(true) - $started) * 1000),
            model: $model,
        );
    }

    /** @param  array<string, mixed>  $body */
    private function formatSpeechTranscript(array $body): string
    {
        $sanitizer = app(TranscriptSanitizer::class);
        $segments = $this->speechSegments($body);
        $lines = [];

        foreach ($sanitizer->reliableSegments($segments) as $segment) {
            $text = trim((string) ($segment['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $speaker = trim((string) ($segment['speaker'] ?? $segment['speaker_label'] ?? ''));
            $lines[] = $speaker !== '' ? $this->labelSpeaker($speaker).': '.$text : $text;
        }

        if ($segments !== []) {
            $transcript = implode("\n", $lines);
        } else {
            $transcript = TranscriptionPrompt::unwrap((string) ($body['text'] ?? ''));
            $logprobs = $body['logprobs'] ?? null;
            if (is_array($logprobs)) {
                $transcript = $sanitizer->dropLowConfidenceSpans($transcript, $logprobs);
            }
        }

        return $sanitizer->clean($transcript);
    }

    private function providerRejectedLogprobs(LlmOperationResult $result): bool
    {
        $error = mb_strtolower((string) $result->error);

        return str_contains($error, 'http 400')
            && (str_contains($error, 'logprob') || str_contains($error, 'include'));
    }

    private function providerRejectedModel(LlmOperationResult $result): bool
    {
        $error = mb_strtolower((string) $result->error);
        $rejected = str_contains($error, 'http 400') || str_contains($error, 'http 404');

        if (! $rejected) {
            return false;
        }

        foreach (['model', 'not found', 'does not exist', 'unsupported', 'unknown'] as $needle) {
            if (str_contains($error, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    private function speechSegments(array $body): array
    {
        $segments = $body['segments'] ?? $body['utterances'] ?? [];

        if (! is_array($segments)) {
            return [];
        }

        return array_values(array_filter($segments, fn (mixed $segment) => is_array($segment)));
    }

    private function labelSpeaker(string $speaker): string
    {
        $normalized = mb_strtolower(trim($speaker));

        return match ($normalized) {
            'agent', 'کارشناس' => 'کارشناس',
            'customer', 'client', 'مشتری' => 'مشتری',
            default => 'گوینده '.$speaker,
        };
    }

    /** @return array{0: string, 1: string, 2: string}|null */
    private function readRecordingBytes(TranscriptionRequestData $request): ?array
    {
        if ($request->storagePath) {
            $payload = app(RecordingStorage::class)->readForAnalysis(
                $request->storagePath,
                $request->storageDisk,
            );

            return [
                $payload['content'],
                $payload['format'],
                $request->mimeType ?? $this->mimeTypeForFormat($payload['format']),
            ];
        }

        $url = $request->playbackUrl ?: $request->recordingUrl;

        if (! $url) {
            return null;
        }

        $response = Http::timeout(120)->get($url);

        if (! $response->successful() || $response->body() === '') {
            return null;
        }

        $format = $this->resolveAudioFormat($request->asAudioRequest());

        return [
            $response->body(),
            $format,
            $request->mimeType ?? $this->mimeTypeForFormat($format),
        ];
    }
}
