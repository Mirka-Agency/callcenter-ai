<?php

namespace App\Infrastructure\Crm\Clients;

use App\Domain\Crm\DTOs\CrmCredentials;
use App\Domain\Crm\DTOs\CrmSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DynamicsDataverseClient
{
    private const API_VERSION = 'v9.2';

    private const TOKEN_SKEW_SECONDS = 60;

    public function __construct(
        private CrmCredentials $credentials,
        private CrmSettings $settings,
    ) {}

    public function get(string $path, array $query = []): Response
    {
        return $this->request()->get($this->buildUrl($path), $query);
    }

    public function post(string $path, array $payload = []): Response
    {
        return $this->request()->post($this->buildUrl($path), $payload);
    }

    public function patch(string $path, array $payload = []): Response
    {
        return $this->request()->patch($this->buildUrl($path), $payload);
    }

    public function resourceUrl(): string
    {
        $url = trim($this->credentials->apiUrl);
        $url = preg_replace('#/api/data(?:/v[\d.]+)?/?$#i', '', $url) ?? $url;

        return rtrim($url, '/');
    }

    public function extractRecordId(Response $response, string $idField): ?string
    {
        $bodyId = $response->json($idField);
        if (is_string($bodyId) && $bodyId !== '') {
            return self::unbrace($bodyId);
        }

        $header = (string) ($response->header('OData-EntityId') ?: $response->header('Location') ?: '');
        if (preg_match('/\((\{?[0-9a-fA-F-]{36}\}?)\)$/', $header, $matches) === 1) {
            return self::unbrace($matches[1]);
        }

        return null;
    }

    public function errorMessage(Response $response): string
    {
        $json = $response->json();
        $message = $json['error']['message'] ?? $json['error']['code'] ?? null;

        if (is_string($message) && $message !== '') {
            return $message;
        }

        $body = trim($response->body());

        return $body !== '' ? $body : 'Dynamics 365 request failed (HTTP '.$response->status().').';
    }

    private function request(): PendingRequest
    {
        return Http::timeout($this->settings->timeout)
            ->acceptJson()
            ->asJson()
            ->withToken($this->accessToken())
            ->withHeaders([
                'OData-MaxVersion' => '4.0',
                'OData-Version' => '4.0',
                'Prefer' => 'return=representation',
            ]);
    }

    private function buildUrl(string $path): string
    {
        $path = ltrim($path, '/');

        return $this->resourceUrl().'/api/data/'.self::API_VERSION.'/'.$path;
    }

    private function accessToken(): string
    {
        $tenant = $this->credentials->directoryId();
        $clientId = $this->credentials->apiKey;
        $clientSecret = $this->credentials->apiToken;
        $resource = $this->resourceUrl();

        if (! filled($tenant) || ! filled($clientId) || ! filled($clientSecret) || $resource === '') {
            throw new RuntimeException('Dynamics 365 credentials are incomplete. Environment URL, tenant ID, client ID, and client secret are required.');
        }

        $cacheKey = 'crm:dynamics:token:'.sha1($tenant.'|'.$clientId.'|'.$resource);

        $token = Cache::remember($cacheKey, 50 * 60, function () use ($tenant, $clientId, $clientSecret, $resource, $cacheKey): string {
            $response = Http::timeout($this->settings->timeout)
                ->asForm()
                ->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'client_credentials',
                    'scope' => $resource.'/.default',
                ]);

            if ($response->failed()) {
                $message = $response->json('error_description')
                    ?? $response->json('error')
                    ?? $response->body();

                throw new RuntimeException('Dynamics 365 token request failed: '.$message);
            }

            $accessToken = (string) $response->json('access_token', '');
            if ($accessToken === '') {
                throw new RuntimeException('Dynamics 365 token response did not include an access token.');
            }

            $ttl = max(60, ((int) $response->json('expires_in', 3600)) - self::TOKEN_SKEW_SECONDS);
            Cache::put($cacheKey, $accessToken, $ttl);

            return $accessToken;
        });

        return (string) $token;
    }

    public static function unbrace(string $value): string
    {
        return trim($value, '{}');
    }

    public static function isGuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
            self::unbrace($value),
        );
    }

    public static function odataString(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
