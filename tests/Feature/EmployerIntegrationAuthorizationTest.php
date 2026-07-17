<?php

namespace Tests\Feature;

use App\Application\Crm\Services\CrmConnectionLifecycleService;
use App\Application\Voip\Services\VoipConnectionLifecycleService;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Enums\UserRole;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Livewire\Employer\Crm\Connections\Create as CrmConnectionCreate;
use App\Models\CrmProvider;
use App\Models\EmployeeIntegrationMeta;
use App\Models\Organization;
use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\User;
use App\Models\VoipProvider;
use App\Services\EmployeeIntegrationMetaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EmployerIntegrationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_routes_are_forbidden_when_gate_is_disabled(): void
    {
        $employer = $this->employerWithOrganization(selfService: false);

        $this->actingAs($employer)
            ->get(route('employer.crm.connections.index'))
            ->assertForbidden();

        $this->actingAs($employer)
            ->get(route('employer.voip.connections.index'))
            ->assertForbidden();
    }

    public function test_management_routes_are_accessible_when_gate_is_enabled(): void
    {
        $employer = $this->employerWithOrganization(selfService: true);

        $this->actingAs($employer)
            ->get(route('employer.crm.connections.index'))
            ->assertOk();

        $this->actingAs($employer)
            ->get(route('employer.voip.connections.index'))
            ->assertOk();
    }

    public function test_limited_crm_and_voip_pages_remain_accessible_when_gate_is_disabled(): void
    {
        $employer = $this->employerWithOrganization(selfService: false);

        $this->actingAs($employer)
            ->get(route('employer.crm.index'))
            ->assertOk();

        $this->actingAs($employer)
            ->get(route('employer.voip.index'))
            ->assertOk();
    }

    public function test_employer_can_create_crm_connection_when_gate_is_enabled(): void
    {
        $employer = $this->employerWithOrganization(selfService: true);

        CrmProvider::query()->create([
            'name' => 'Didar',
            'code' => 'didar',
            'is_active' => true,
        ]);

        $this->actingAs($employer);

        Livewire::test(CrmConnectionCreate::class)
            ->set('name', 'Sales CRM')
            ->set('crm_provider_id', CrmProvider::query()->value('id'))
            ->set('api_url', 'https://app.didar.me/api')
            ->set('api_key', 'secret-key')
            ->call('save')
            ->assertRedirect(route('employer.crm.connections.index'));

        $this->assertDatabaseHas('organization_crm_connections', [
            'organization_id' => $employer->primaryOrganization()->id,
            'name' => 'Sales CRM',
        ]);
    }

    public function test_employer_cannot_edit_other_organization_crm_connection(): void
    {
        $employer = $this->employerWithOrganization(selfService: true);
        $otherConnection = OrganizationCrmConnection::query()->create([
            'organization_id' => Organization::factory()->withIntegrationSelfService()->create()->id,
            'crm_provider_id' => CrmProvider::query()->create([
                'name' => 'Didar',
                'code' => 'didar',
                'is_active' => true,
            ])->id,
            'name' => 'Foreign',
            'credentials' => ['api_url' => 'https://app.didar.me/api'],
            'is_active' => true,
        ]);

        $this->actingAs($employer)
            ->get(route('employer.crm.connections.edit', $otherConnection))
            ->assertNotFound();
    }

    public function test_crm_lifecycle_preserves_credentials_on_blank_update(): void
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
            'name' => 'Primary',
            'credentials' => ['api_url' => 'https://app.didar.me/api', 'api_key' => 'keep-me'],
            'is_active' => true,
        ]);

        app(CrmConnectionLifecycleService::class)->update($connection, [
            'name' => 'Updated',
            'credentials' => ['api_url' => 'https://app.didar.me/api', 'api_key' => ''],
        ]);

        $this->assertSame('keep-me', $connection->fresh()->credentials['api_key']);
    }

    public function test_deleting_voip_connection_removes_employee_integration_meta(): void
    {
        $organization = Organization::factory()->create();
        $provider = VoipProvider::query()->create([
            'name' => 'Simotel',
            'code' => VoipProviderCode::Simotel->value,
            'adapter_class' => SimotelVoipAdapter::class,
            'is_active' => true,
        ]);

        $connection = OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'Main',
            'credentials' => [],
            'is_active' => true,
        ]);

        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Ali',
            'last_name' => 'Test',
            'is_active' => true,
        ]);

        EmployeeIntegrationMeta::query()->create([
            'organization_user_id' => $employee->id,
            'integratable_type' => OrganizationVoipConnection::class,
            'integratable_id' => $connection->id,
            'key' => 'extension',
            'value' => '101',
        ]);

        app(VoipConnectionLifecycleService::class)->delete($connection);

        $this->assertDatabaseMissing('employee_integration_meta', [
            'organization_user_id' => $employee->id,
            'integratable_id' => $connection->id,
        ]);
    }

    public function test_employee_meta_rejects_foreign_connection_reference(): void
    {
        $organization = Organization::factory()->create();
        $foreignOrganization = Organization::factory()->create();

        $provider = VoipProvider::query()->create([
            'name' => 'Simotel',
            'code' => VoipProviderCode::Simotel->value,
            'adapter_class' => SimotelVoipAdapter::class,
            'is_active' => true,
        ]);

        $foreignConnection = OrganizationVoipConnection::query()->create([
            'organization_id' => $foreignOrganization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'Foreign',
            'credentials' => [],
            'is_active' => true,
        ]);

        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Ali',
            'last_name' => 'Test',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        EmployeeIntegrationMetaService::syncForEmployee($employee, [[
            'connection' => EmployeeIntegrationMetaService::connectionReference($foreignConnection),
            'meta' => ['extension' => '101'],
        ]]);
    }

    private function employerWithOrganization(bool $selfService): User
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);

        $factory = Organization::factory();

        if ($selfService) {
            $factory = $factory->withIntegrationSelfService();
        }

        $factory->create(['user_id' => $employer->id]);

        return $employer;
    }
}
