<?php

namespace Tests\Unit;

use App\Application\Llm\Services\LlmConnectionResolver;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\Organization;
use App\Models\PlatformAiSettings;
use App\Services\LlmModelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LlmModelResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_platform_default_model_from_models_section(): void
    {
        $organization = Organization::factory()->create();
        $primaryOpenAi = $this->createProvider('OpenAI Primary', 'primary-key');
        $backupOpenAi = $this->createProvider('OpenAI Backup', 'backup-key');

        $primaryModel = $this->createModel($primaryOpenAi, 'gpt-4o-mini', isDefault: false);
        $defaultModel = $this->createModel($backupOpenAi, 'gpt-4o', isDefault: true);

        PlatformAiSettings::current()->update([
            'default_llm_provider_id' => $backupOpenAi->id,
            'default_llm_model_id' => $defaultModel->id,
        ]);

        $resolved = app(LlmModelResolver::class)->resolveForOrganization($organization->id);

        $this->assertSame($defaultModel->id, $resolved->id);
        $this->assertSame($backupOpenAi->id, $resolved->provider_id);
        $this->assertNotSame($primaryModel->id, $resolved->id);
    }

    public function test_connection_resolver_uses_api_key_from_default_models_provider(): void
    {
        $organization = Organization::factory()->create();
        $primaryOpenAi = $this->createProvider('OpenAI Primary', 'primary-key');
        $backupOpenAi = $this->createProvider('OpenAI Backup', 'backup-key');
        $defaultModel = $this->createModel($backupOpenAi, 'gpt-4o', isDefault: true);

        PlatformAiSettings::current()->update([
            'default_llm_provider_id' => $backupOpenAi->id,
            'default_llm_model_id' => $defaultModel->id,
        ]);

        [$config] = app(LlmConnectionResolver::class)->resolve($organization->id);

        $this->assertSame('backup-key', $config->credentials->apiKey);
        $this->assertSame('gpt-4o', $config->settings->defaultModel);
        $this->assertNotSame($primaryOpenAi->api_key, $config->credentials->apiKey);
    }

    public function test_skips_inactive_provider_even_when_model_is_flagged_default(): void
    {
        $organization = Organization::factory()->create();
        $inactiveProvider = $this->createProvider('Inactive OpenAI', 'inactive-key', isActive: false);
        $activeProvider = $this->createProvider('Active OpenAI', 'active-key');

        $this->createModel($inactiveProvider, 'gpt-4o', isDefault: true);
        $activeModel = $this->createModel($activeProvider, 'gpt-4o-mini', isDefault: false);

        $resolved = app(LlmModelResolver::class)->resolveForOrganization($organization->id);

        $this->assertSame($activeModel->id, $resolved->id);
        $this->assertSame($activeProvider->id, $resolved->provider_id);
    }

    private function createProvider(string $name, string $apiKey, bool $isActive = true): LlmProvider
    {
        return LlmProvider::query()->create([
            'name' => $name,
            'code' => LlmProviderCode::OpenAi->value,
            'api_key' => $apiKey,
            'is_active' => $isActive,
        ]);
    }

    private function createModel(LlmProvider $provider, string $modelKey, bool $isDefault): LlmModel
    {
        return LlmModel::query()->create([
            'provider_id' => $provider->id,
            'name' => $modelKey,
            'model_key' => $modelKey,
            'input_price_per_million_tokens' => 1,
            'output_price_per_million_tokens' => 2,
            'is_default' => $isDefault,
            'is_active' => true,
        ]);
    }
}
