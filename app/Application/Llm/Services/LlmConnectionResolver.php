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
use App\Models\LlmModel;
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

    /** @return array{0: LlmConnectionConfig, 1: LlmProviderInterface} */
    public function resolveForModel(int $organizationId, LlmModel $model, ?string $transcriptionModel = null): array
    {
        $config = $this->configFromModel($organizationId, $model, $transcriptionModel);

        if (! $config) {
            throw LlmConnectionNotFoundException::forOrganization($organizationId);
        }

        $provider = $this->registry->resolve($config->providerCode);
        $provider->configure($config);

        return [$config, $provider];
    }

    private function configFromProviderDefaults(int $organizationId): ?LlmConnectionConfig
    {
        $model = $this->modelResolver->resolveForOrganization($organizationId);

        return $this->configFromModel($organizationId, $model);
    }

    private function configFromModel(int $organizationId, LlmModel $model, ?string $transcriptionModel = null): ?LlmConnectionConfig
    {
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
                transcriptionModel: $transcriptionModel,
            ),
            isDefault: true,
            isActive: true,
        );
    }
}
