<?php

namespace App\Infrastructure\Llm\Clients;

use App\Domain\Llm\ValueObjects\LlmOperationResult;
use Illuminate\Support\Facades\Http;

class OpenAiApiClient
{
    public function __construct(
        private string $apiKey,
        private ?string $baseUrl = null,
    ) {}

    public function testConnection(): LlmOperationResult
    {
        $response = Http::withToken($this->apiKey)
            ->get(($this->baseUrl ?? 'https://api.openai.com/v1').'/models');

        return $response->successful()
            ? LlmOperationResult::success(message: 'OpenAI connection successful.')
            : LlmOperationResult::failure('OpenAI connection failed: '.$response->body());
    }

    /**
     * @param  array<string, scalar|null>  $fields
     */
    public function transcribeFile(string $bytes, string $filename, array $fields): LlmOperationResult
    {
        $payload = array_filter(
            $fields,
            fn (mixed $value) => $value !== null && $value !== '',
        );

        $response = Http::withToken($this->apiKey)
            ->timeout(300)
            ->attach('file', $bytes, $filename)
            ->post(rtrim($this->baseUrl ?? 'https://api.openai.com/v1', '/').'/audio/transcriptions', $payload);

        if (! $response->successful()) {
            return LlmOperationResult::failure(
                'OpenAI transcription failed (HTTP '.$response->status().'): '.$response->body(),
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            $body = ['text' => trim($response->body())];
        }

        return LlmOperationResult::success(
            data: $body,
            message: 'Transcription completed.',
            inputTokens: (int) ($body['usage']['input_tokens'] ?? $body['usage']['prompt_tokens'] ?? 0),
            outputTokens: (int) ($body['usage']['output_tokens'] ?? $body['usage']['completion_tokens'] ?? 0),
        );
    }
}
