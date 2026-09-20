<?php

namespace Tests\Feature;

use App\Domain\Crm\Enums\CrmProviderCode;
use App\Livewire\Employer\Crm\Connections\Create as CrmConnectionCreate;
use App\Models\CrmProvider;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\CrmProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DynamicsCrmProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_didar_and_dynamics_providers(): void
    {
        $this->seed(CrmProviderSeeder::class);
        $this->seed(CrmProviderSeeder::class);

        $this->assertSame(2, CrmProvider::query()->count());
        $this->assertDatabaseHas('crm_providers', [
            'code' => CrmProviderCode::Didar->value,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('crm_providers', [
            'code' => CrmProviderCode::Dynamics->value,
            'name' => 'Microsoft Dynamics 365',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('integration_meta_definitions', [
            'key' => 'crm_user_id',
            'provider_type' => CrmProvider::class,
            'provider_id' => CrmProvider::query()->where('code', CrmProviderCode::Dynamics->value)->value('id'),
        ]);
    }

    public function test_employer_can_create_dynamics_connection(): void
    {
        $this->seed(CrmProviderSeeder::class);

        $employer = User::factory()->employer()->create();
        $organization = Organization::factory()->withIntegrationSelfService()->create([
            'user_id' => $employer->id,
        ]);

        $dynamicsId = (int) CrmProvider::query()->where('code', CrmProviderCode::Dynamics->value)->value('id');

        $this->actingAs($employer);

        Livewire::test(CrmConnectionCreate::class)
            ->set('crm_provider_id', $dynamicsId)
            ->set('name', 'Dynamics Sales')
            ->set('api_url', 'https://contoso.crm4.dynamics.com')
            ->set('tenant_id', 'tenant-guid')
            ->set('api_key', 'client-id')
            ->set('api_token', 'client-secret')
            ->call('save')
            ->assertRedirect(route('employer.crm.connections.index'));

        $this->assertDatabaseHas('organization_crm_connections', [
            'organization_id' => $organization->id,
            'crm_provider_id' => $dynamicsId,
            'name' => 'Dynamics Sales',
        ]);

        $credentials = $organization->crmConnections()->first()?->credentials ?? [];
        $this->assertSame('https://contoso.crm4.dynamics.com', $credentials['api_url']);
        $this->assertSame('tenant-guid', $credentials['tenant_id']);
        $this->assertSame('client-id', $credentials['api_key']);
        $this->assertSame('client-secret', $credentials['api_token']);
    }
}
