<?php

namespace App\Application\Llm\Services;

use App\Domain\Llm\DTOs\LlmConnectionConfig;
use App\Domain\Llm\DTOs\LlmCredentials;
use App\Domain\Llm\DTOs\LlmSettings;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Domain\Llm\Exceptions\LlmConnectionNotFoundException;
use App\Domain\Llm\Contracts\LlmConnectionRepositoryInterface;
use App\Domain\Llm\Contracts\LlmProviderInterface;
use App\Infrastructure\Llm\LlmAdapterRegistry;
use App\Services\LlmModelResolver;

class LlmConnectionResolver
{
    public function __construct(
        private LlmConnectionRepositoryInterface $connections,
        private LlmAdapterRegistry $registry,
        private LlmModelResolver $modelResolver,
    ) {}

    /** @return array{0: LlmConnectionConfig, 1: LlmProviderInterface} */
    public function resolve(int $organizationId, ?int $connectionId = null): array
    {
        $config = $connectionId
            ? $this->connections->findById($connectionId)
            : $this->connections->findDefaultForOrganization($organizationId);

        if (! $config) {
            $config = $this->configFromProviderDefaults($organizationId);
        }

        if (! $config || $config->organizationId !== $organizationId) {
            throw LlmConnectionNotFoundException::forOrganization($organizationId);
        }

        $provider = $this->registry->resolve($config->providerCode);
        $provider->configure($config);

        return [$config, $provider];
    }

    private function configFromProviderDefaults(int $organizationId): ?LlmConnectionConfig
    {
        $model = $this->modelResolver->resolveForOrganization($organizationId);
        $llmProvider = $model->provider;

        if (! $llmProvider) {
            return null;
        }

        return new LlmConnectionConfig(
            connectionId: null,
            organizationId: $organizationId,
            providerCode: LlmProviderCode::from($llmProvider->code),
            name: $llmProvider->name.' (platform)',
            credentials: new LlmCredentials(
                apiKey: $llmProvider->api_key,
                baseUrl: $llmProvider->base_url ?? ($llmProvider->config['default_api_url'] ?? null),
            ),
            settings: new LlmSettings(
                defaultModel: $model->model_key,
            ),
            isDefault: true,
            isActive: true,
        );
    }
}
