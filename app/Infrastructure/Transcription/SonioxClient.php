<?php

namespace App\Infrastructure\Transcription;

use App\Application\Llm\Prompts\SonioxContext;
use App\Domain\Llm\Exceptions\LlmTransientException;
use App\Domain\Transcription\Contracts\TranscriptionClient;
use App\Domain\Transcription\DTOs\TranscriptionContextData;
use App\Domain\Transcription\Exceptions\TranscriptionRejected;
use App\Infrastructure\Llm\LlmOutboundGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SonioxClient implements TranscriptionClient
{
    public function validateConfiguration(): void
    {
        app(LlmOutboundGuard::class)->assertRemoteAnalysisEnabled();
        if ((int) config('soniox.deadline_seconds') <= 0) {
            throw new \RuntimeException('SONIOX_DEADLINE_SECONDS must be greater than zero.');
        }
        foreach (['api_key', 'base_url', 'model'] as $key) {
            if (! is_string(config("soniox.$key")) || trim(config("soniox.$key")) === '') {
                throw new \RuntimeException('Missing SONIOX_'.strtoupper($key).' environment configuration.');
            }
        }
    }

    public function upload($audio, string $filename): string
    {
        // Uploads and submissions are deliberately not automatically retried.
        return $this->identifier($this->checkSubmission($this->request()->attach('file', $audio, $filename)->post('/files')));
    }

    public function submit(string $fileId, string $model, string $reference, ?TranscriptionContextData $context = null): string
    {
        return $this->identifier($this->checkSubmission($this->request()->post('/transcriptions', [
            'file_id' => $fileId,
            'model' => $model,
            'language_hints' => ['fa'],
            'enable_speaker_diarization' => true,
            'enable_language_identification' => true,
            'context' => SonioxContext::build($context),
            'client_reference_id' => $reference,
        ])));
    }

    public function status(string $transcriptionId): array
    {
        return $this->read('/transcriptions/'.rawurlencode($transcriptionId));
    }

    public function tokens(string $transcriptionId): array
    {
        $data = $this->read('/transcriptions/'.rawurlencode($transcriptionId).'/transcript');
        if (! isset($data['tokens']) || ! is_array($data['tokens'])) {
            throw new \RuntimeException('Soniox returned an invalid transcript response.');
        }

        return $data['tokens'];
    }

    public function deleteTranscription(string $transcriptionId): void
    {
        $this->delete('/transcriptions/'.rawurlencode($transcriptionId));
    }

    public function deleteFile(string $fileId): void
    {
        $this->delete('/files/'.rawurlencode($fileId));
    }

    private function request(): PendingRequest
    {
        $this->validateConfiguration();

        return Http::baseUrl(rtrim(config('soniox.base_url'), '/'))
            ->withToken(config('soniox.api_key'))->acceptJson()->connectTimeout(15)->timeout(120);
    }

    /** @return array<string, mixed> */
    private function read(string $path): array
    {
        try {
            $data = $this->check($this->request()->get($path))->json();
            if (! is_array($data)) {
                throw new \RuntimeException('Soniox returned an invalid JSON response.');
            }

            return $data;
        } catch (ConnectionException $e) {
            throw new LlmTransientException('Soniox connection failed while retrieving transcription.', previous: $e);
        }
    }

    private function delete(string $path): void
    {
        $response = $this->request()->delete($path);
        if ($response->status() !== 404) {
            $this->check($response);
        }
    }

    private function checkSubmission(Response $response): Response
    {
        if ($response->serverError()) {
            Log::error('Soniox submission acceptance is unknown.', ['status' => $response->status(), 'request_id' => $response->json('request_id')]);
            throw new \RuntimeException('Soniox returned a server error; acceptance is unknown. Inspect remote usage before retrying.');
        }

        return $this->check($response);
    }

    private function identifier(Response $response): string
    {
        $id = $response->json('id');
        if (! is_string($id) || $id === '') {
            throw new \RuntimeException('Soniox response omitted the remote identifier; acceptance is unknown.');
        }

        return $id;
    }

    private function check(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $type = (string) $response->json('error_type', 'http_error');
        $context = ['status' => $response->status(), 'error_type' => $type, 'request_id' => $response->json('request_id')];
        Log::warning('Soniox request failed', $context);
        $message = "Soniox request failed (HTTP {$response->status()}, {$type}). Check provider configuration, balance, and limits.";

        if ($response->status() === 429 || $response->serverError()) {
            throw new LlmTransientException($message);
        }

        throw new TranscriptionRejected($message);
    }
}
