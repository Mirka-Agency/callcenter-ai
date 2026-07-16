<?php

namespace App\Infrastructure\Voip\Clients;

use App\Domain\Voip\DTOs\VoipCredentials;
use App\Domain\Voip\DTOs\VoipSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SimotelApiClient
{
    public function __construct(
        private VoipCredentials $credentials,
        private VoipSettings $settings,
    ) {}

    public function post(string $endpoint, array $payload = []): Response
    {
        return $this->request()->post($this->buildUrl($endpoint), $payload);
    }

    public function downloadAudio(string $file): Response
    {
        return $this->request()
            ->withHeaders(['Accept' => '*/*'])
            ->post($this->buildUrl('reports/audio/download'), [
                'file' => $file,
            ]);
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout($this->settings->timeout)
            ->asJson();

        if ($apiKey = $this->credentials->apiKey ?? $this->credentials->apiToken) {
            $request = $request->withHeaders(['X-APIKEY' => $apiKey]);
        }

        if ($this->credentials->username && $this->credentials->password) {
            $request = $request->withBasicAuth(
                $this->credentials->username,
                $this->credentials->password,
            );
        }

        return $request;
    }

    private function buildUrl(string $endpoint): string
    {
        $baseUrl = rtrim($this->credentials->apiUrl, '/');
        $endpoint = ltrim($endpoint, '/');

        return "{$baseUrl}/{$endpoint}";
    }
}
