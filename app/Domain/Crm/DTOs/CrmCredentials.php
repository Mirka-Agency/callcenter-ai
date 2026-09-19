<?php

namespace App\Domain\Crm\DTOs;

readonly class CrmCredentials
{
    public function __construct(
        public string $apiUrl,
        public ?string $apiKey = null,
        public ?string $apiToken = null,
        public ?string $username = null,
        public ?string $password = null,
        public ?string $tenantId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            apiUrl: $data['api_url'] ?? $data['apiUrl'] ?? '',
            apiKey: $data['api_key'] ?? $data['apiKey'] ?? null,
            apiToken: $data['api_token'] ?? $data['apiToken'] ?? null,
            username: $data['username'] ?? null,
            password: $data['password'] ?? null,
            tenantId: self::nullableString($data['tenant_id'] ?? $data['tenantId'] ?? null),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'api_url' => $this->apiUrl,
            'api_key' => $this->apiKey,
            'api_token' => $this->apiToken,
            'username' => $this->username,
            'password' => $this->password,
            'tenant_id' => $this->tenantId,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function directoryId(): ?string
    {
        return $this->tenantId ?: $this->username;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    public function authKey(): ?string
    {
        return $this->apiKey ?? $this->apiToken;
    }
}
