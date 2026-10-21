<?php

namespace App\Infrastructure\Llm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the static analysis system prompt in a Gemini cached-content entry
 * so repeated calls are billed at the cached input price.
 */
class GeminiSystemPromptCache
{
    public function get(string $model, string $systemPrompt, string $apiKey, string $baseUrl): ?string
    {
        if (! $this->enabled() || $apiKey === '' || trim($systemPrompt) === '') {
            return null;
        }

        $key = $this->key($model, $systemPrompt);
        $stored = Cache::get($key);

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        if ($stored === false) {
            return null;
        }

        $name = $this->create($model, $systemPrompt, $apiKey, $baseUrl);

        if ($name === null) {
            Cache::put($key, false, now()->addMinutes(10));

            return null;
        }

        Cache::put($key, $name, now()->addMinutes(50));

        return $name;
    }

    public function forget(string $model, string $systemPrompt): void
    {
        Cache::forget($this->key($model, $systemPrompt));
    }

    private function create(string $model, string $systemPrompt, string $apiKey, string $baseUrl): ?string
    {
        $modelName = str_starts_with($model, 'models/') ? $model : 'models/'.$model;

        try {
            $response = Http::timeout(30)
                ->withQueryParameters(['key' => $apiKey])
                ->post(rtrim($baseUrl, '/').'/cachedContents', [
                    'model' => $modelName,
                    'systemInstruction' => [
                        'parts' => [
                            ['text' => $systemPrompt],
                        ],
                    ],
                    'ttl' => '3600s',
                ]);
        } catch (\Throwable $e) {
            Log::info('Gemini prompt cache unavailable', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::info('Gemini prompt cache unavailable', ['status' => $response->status()]);

            return null;
        }

        $name = $response->json('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function enabled(): bool
    {
        return (bool) config('llm.prompt_cache', true);
    }

    private function key(string $model, string $systemPrompt): string
    {
        return 'llm-system-prompt:'.hash('sha256', $model."\n".$systemPrompt);
    }
}
