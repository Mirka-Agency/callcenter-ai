<?php

namespace Tests\Unit;

use App\Application\Llm\Services\LlmConnectionResolver;
use App\Domain\Llm\Enums\LlmProviderCode;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnvironmentLlmConfigurationTest extends TestCase
{
    public function test_configuration_does_not_read_the_database(): void
    {
        config(['llm.provider' => 'gemini', 'llm.model' => 'gemini-3.8-flash', 'llm.api_key' => 'environment-key', 'llm.base_url' => 'https://api.avalai.ir/v1beta']);
        DB::shouldReceive('connection')->never();

        [$configuration] = app(LlmConnectionResolver::class)->resolve(42);

        $this->assertSame(42, $configuration->organizationId);
        $this->assertNull($configuration->connectionId);
        $this->assertSame(LlmProviderCode::Gemini, $configuration->providerCode);
        $this->assertSame('environment-key', $configuration->credentials->apiKey);
        $this->assertSame('gemini-3.8-flash', $configuration->settings->defaultModel);
    }

    public function test_missing_key_is_an_explicit_configuration_error(): void
    {
        config(['llm.api_key' => '']);
        $this->expectExceptionMessage('Missing LLM_API_KEY');
        app(LlmConnectionResolver::class)->resolve(42);
    }

    public function test_database_connection_overrides_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(LlmConnectionResolver::class)->resolve(42, 10);
    }

    public function test_model_management_routes_are_not_registered(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('llm-models', $route->uri());
            $this->assertStringNotContainsString('llm-providers', $route->uri());
        }
    }
}
