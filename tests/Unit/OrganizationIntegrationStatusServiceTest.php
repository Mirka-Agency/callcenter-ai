<?php

namespace Tests\Unit;

use App\Domain\Crm\Enums\CrmLogStatus;
use App\Domain\Crm\Enums\CrmOperation;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipOperation;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Enums\IntegrationSetupStatus;
use App\Infrastructure\Voip\Adapters\NullVoipAdapter;
use App\Models\CrmProvider;
use App\Models\Organization;
use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipProvider;
use App\Services\OrganizationIntegrationStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationIntegrationStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationIntegrationStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrganizationIntegrationStatusService::class);
    }

    public function test_readiness_is_incomplete_without_connections(): void
    {
        $organization = Organization::factory()->create();

        $readiness = $this->service->forOrganization($organization);

        $this->assertSame(IntegrationSetupStatus::Incomplete, $readiness->crmStatus);
        $this->assertSame(IntegrationSetupStatus::Incomplete, $readiness->voipStatus);
        $this->assertFalse($readiness->systemReady());
        $this->assertSame([
            'crm_status' => 'incomplete',
            'voip_status' => 'incomplete',
            'system_ready' => false,
        ], $readiness->toArray());
    }

    public function test_crm_is_incomplete_without_successful_connection_test(): void
    {
        $organization = Organization::factory()->create();
        $provider = CrmProvider::query()->create([
            'name' => 'Didar',
            'code' => 'didar',
            'is_active' => true,
        ]);

        OrganizationCrmConnection::query()->create([
            'organization_id' => $organization->id,
            'crm_provider_id' => $provider->id,
            'name' => 'Primary CRM',
            'credentials' => ['api_url' => 'https://crm.example.com', 'api_key' => 'secret'],
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertSame(
            IntegrationSetupStatus::Incomplete,
            $this->service->crmStatus($organization),
        );
    }

    public function test_crm_is_complete_when_verified_and_configured(): void
    {
        $organization = Organization::factory()->create();
        $provider = CrmProvider::query()->create([
            'name' => 'Didar',
            'code' => 'didar',
            'is_active' => true,
        ]);

        $connection = OrganizationCrmConnection::query()->create([
            'organization_id' => $organization->id,
            'crm_provider_id' => $provider->id,
            'name' => 'Primary CRM',
            'credentials' => ['api_url' => 'https://crm.example.com', 'api_key' => 'secret'],
            'is_default' => true,
            'is_active' => true,
        ]);

        $connection->connectionLogs()->create([
            'operation' => CrmOperation::TestConnection,
            'status' => CrmLogStatus::Success,
            'message' => 'OK',
        ]);

        $this->assertSame(
            IntegrationSetupStatus::Complete,
            $this->service->crmStatus($organization),
        );
    }

    public function test_voip_is_incomplete_when_provider_does_not_support_webhook(): void
    {
        $organization = Organization::factory()->create();
        $provider = $this->createVoipProvider([
            'supports_webhook' => false,
        ]);
        $connection = $this->createVoipConnection($organization, $provider);

        $connection->syncLogs()->create([
            'operation' => VoipOperation::TestConnection,
            'status' => VoipLogStatus::Success,
            'message' => 'OK',
        ]);

        $this->assertSame(
            IntegrationSetupStatus::Incomplete,
            $this->service->voipStatus($organization),
        );
    }

    public function test_voip_is_complete_for_verified_webhook_configuration(): void
    {
        $organization = Organization::factory()->create();
        $provider = $this->createVoipProvider();
        $connection = $this->createVoipConnection($organization, $provider);

        $connection->syncLogs()->create([
            'operation' => VoipOperation::TestConnection,
            'status' => VoipLogStatus::Success,
            'message' => 'OK',
        ]);

        $this->assertSame(
            IntegrationSetupStatus::Complete,
            $this->service->voipStatus($organization),
        );
    }

    public function test_system_ready_when_both_integrations_are_complete(): void
    {
        $organization = Organization::factory()->create();

        $crmProvider = CrmProvider::query()->create([
            'name' => 'Didar',
            'code' => 'didar',
            'is_active' => true,
        ]);

        $crmConnection = OrganizationCrmConnection::query()->create([
            'organization_id' => $organization->id,
            'crm_provider_id' => $crmProvider->id,
            'name' => 'Primary CRM',
            'credentials' => ['api_url' => 'https://crm.example.com', 'api_key' => 'secret'],
            'is_default' => true,
            'is_active' => true,
        ]);

        $crmConnection->connectionLogs()->create([
            'operation' => CrmOperation::TestConnection,
            'status' => CrmLogStatus::Success,
            'message' => 'OK',
        ]);

        $voipProvider = $this->createVoipProvider();
        $voipConnection = $this->createVoipConnection($organization, $voipProvider);

        $voipConnection->syncLogs()->create([
            'operation' => VoipOperation::TestConnection,
            'status' => VoipLogStatus::Success,
            'message' => 'OK',
        ]);

        $readiness = $this->service->forOrganization($organization);

        $this->assertTrue($readiness->systemReady());
        $this->assertSame([
            'crm_status' => 'complete',
            'voip_status' => 'complete',
            'system_ready' => true,
        ], $readiness->toArray());
    }

    /** @param array<string, mixed> $overrides */
    private function createVoipProvider(array $overrides = []): VoipProvider
    {
        return VoipProvider::query()->create(array_merge([
            'name' => 'Novatel',
            'code' => VoipProviderCode::Novatel->value,
            'adapter_class' => NullVoipAdapter::class,
            'supports_webhook' => true,
            'supports_polling' => false,
            'polling_interval_seconds' => 30,
            'is_active' => true,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function createVoipConnection(
        Organization $organization,
        VoipProvider $provider,
        array $overrides = [],
    ): OrganizationVoipConnection {
        return OrganizationVoipConnection::query()->create(array_merge([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'Primary VoIP',
            'credentials' => ['api_url' => 'https://voip.example.com', 'api_key' => 'secret'],
            'is_default' => true,
            'is_active' => true,
            'ingestion_mode' => 'webhook',
            'polling_enabled' => false,
        ], $overrides));
    }
}
