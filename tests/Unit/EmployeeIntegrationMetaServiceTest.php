<?php

namespace Tests\Unit;

use App\Domain\Voip\Enums\VoipProviderCode;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Models\CrmProvider;
use App\Models\Organization;
use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipProvider;
use App\Services\EmployeeIntegrationMetaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeIntegrationMetaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_connection_options_merge_crm_and_voip_without_eloquent_get_key_error(): void
    {
        $organization = Organization::factory()->create();

        $crmProvider = CrmProvider::query()->create([
            'name' => 'Didar',
            'code' => 'didar',
            'is_active' => true,
        ]);

        $crm = OrganizationCrmConnection::query()->create([
            'organization_id' => $organization->id,
            'crm_provider_id' => $crmProvider->id,
            'name' => 'Primary CRM',
            'credentials' => [],
            'is_active' => true,
        ]);

        $voipProvider = VoipProvider::query()->create([
            'name' => 'Simotel',
            'code' => VoipProviderCode::Simotel->value,
            'adapter_class' => SimotelVoipAdapter::class,
            'is_active' => true,
        ]);

        $voip = OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $voipProvider->id,
            'name' => 'Astel',
            'credentials' => [],
            'is_active' => true,
        ]);

        $options = EmployeeIntegrationMetaService::connectionOptionsForOrganization($organization->id);

        $crmKey = EmployeeIntegrationMetaService::connectionReference($crm);
        $voipKey = EmployeeIntegrationMetaService::connectionReference($voip);

        $this->assertArrayHasKey($crmKey, $options);
        $this->assertArrayHasKey($voipKey, $options);
        $this->assertStringContainsString('CRM: Didar', $options[$crmKey]);
        $this->assertStringContainsString('VoIP: Simotel', $options[$voipKey]);

        $this->assertDatabaseHas('integration_meta_definitions', [
            'provider_type' => VoipProvider::class,
            'provider_id' => $voipProvider->id,
            'key' => 'extension',
            'is_required' => true,
        ]);

        $this->assertDatabaseHas('integration_meta_definitions', [
            'provider_type' => CrmProvider::class,
            'provider_id' => $crmProvider->id,
            'key' => 'crm_user_id',
            'is_required' => true,
        ]);
    }
}
