<?php

namespace Tests\Feature;

use App\Application\Voip\Services\VoipConnectionLifecycleService;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipOperation;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Enums\IntegrationSetupStatus;
use App\Infrastructure\Voip\Adapters\CustomVoipAdapter;
use App\Models\Organization;
use App\Models\VoipProvider;
use App\Services\OrganizationIntegrationStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomVoipConnectionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_custom_connection_auto_verifies_and_is_ready_without_api(): void
    {
        $organization = Organization::factory()->create();
        $provider = VoipProvider::query()->create([
            'name' => 'سفارشی / Asterisk',
            'code' => VoipProviderCode::Custom->value,
            'adapter_class' => CustomVoipAdapter::class,
            'supports_webhook' => true,
            'supports_polling' => false,
            'polling_interval_seconds' => 30,
            'is_active' => true,
        ]);

        $connection = app(VoipConnectionLifecycleService::class)->create($organization->id, [
            'voip_provider_id' => $provider->id,
            'name' => 'Asterisk PBX',
            'webhook_token' => null,
            'credentials' => [],
            'settings' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertNotBlank($connection->webhook_token);
        $this->assertTrue(
            $connection->syncLogs()
                ->where('operation', VoipOperation::TestConnection)
                ->where('status', VoipLogStatus::Success)
                ->exists()
        );

        $this->assertSame(
            IntegrationSetupStatus::Complete,
            app(OrganizationIntegrationStatusService::class)->voipStatus($organization),
        );
    }

    private function assertNotBlank(mixed $value): void
    {
        $this->assertTrue(filled($value));
    }
}
