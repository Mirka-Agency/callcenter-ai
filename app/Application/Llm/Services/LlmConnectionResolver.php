<?php

namespace App\Application\Llm\Services;

use App\Domain\Llm\Contracts\LlmProviderInterface;
use App\Domain\Llm\DTOs\LlmConnectionConfig;
use App\Domain\Llm\DTOs\LlmCredentials;
use App\Domain\Llm\DTOs\LlmSettings;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Infrastructure\Llm\LlmAdapterRegistry;

class LlmConnectionResolver
{
    public function __construct(private LlmAdapterRegistry $registry) {}

    /** @return array{0: LlmConnectionConfig, 1: LlmProviderInterface} */
    public function resolve(int $organizationId, ?int $connectionId = null): array
    {
        if ($connectionId !== null) {
            throw new \InvalidArgumentException('Database LLM connections are no longer supported. Configure LLM_* environment variables.');
        }

        foreach (['provider', 'model', 'api_key', 'base_url'] as $key) {
            if (! is_string(config("llm.$key")) || trim(config("llm.$key")) === '') {
                throw new \RuntimeException('Missing LLM_'.strtoupper($key).' environment configuration.');
            }
        }

        $providerCode = LlmProviderCode::tryFrom(config('llm.provider'))
            ?? throw new \RuntimeException('Invalid LLM_PROVIDER environment configuration.');

        $config = new LlmConnectionConfig(
            connectionId: null,
            organizationId: $organizationId,
            providerCode: $providerCode,
            name: 'Environment configuration',
            credentials: new LlmCredentials(apiKey: config('llm.api_key'), baseUrl: config('llm.base_url')),
            settings: new LlmSettings(defaultModel: config('llm.model')),
            isDefault: true,
            isActive: true,
        );
        $provider = $this->registry->resolve($providerCode);
        $provider->configure($config);

        return [$config, $provider];
    }
}
