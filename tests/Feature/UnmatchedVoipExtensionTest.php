<?php

namespace Tests\Feature;

use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipOperation;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Enums\UserRole;
use App\Infrastructure\Voip\Adapters\NullVoipAdapter;
use App\Livewire\Employer\Voip\Index;
use App\Models\Call;
use App\Models\EmployeeIntegrationMeta;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\User;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class UnmatchedVoipExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_extension_writes_meta_and_backfills_calls(): void
    {
        [$organization, $connection, $employer, $employee] = $this->setupOrganization();

        $log = VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => 'call-1',
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '101',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'raw_payload' => ['extension' => '101', 'resolved_extension' => '101'],
        ]);

        Call::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'voip_call_log_id' => $log->id,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => 'call-1',
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '101',
            'status' => 'completed',
            'organization_user_id' => null,
        ]);

        $backfilled = app(UnmatchedVoipExtensionService::class)->assignExtensionToEmployee(
            organization: $organization,
            extension: '101',
            connectionId: $connection->id,
            organizationUserId: $employee->id,
        );

        $this->assertSame(1, $backfilled);
        $this->assertDatabaseHas('employee_integration_meta', [
            'organization_user_id' => $employee->id,
            'integratable_type' => OrganizationVoipConnection::class,
            'integratable_id' => $connection->id,
            'key' => 'extension',
            'value' => '101',
        ]);
        $this->assertDatabaseHas('calls', [
            'voip_call_log_id' => $log->id,
            'organization_user_id' => $employee->id,
        ]);
    }

    public function test_assign_extension_rejects_conflict_with_other_employee(): void
    {
        [$organization, $connection, , $employee] = $this->setupOrganization();
        $otherEmployee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Sara',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);

        EmployeeIntegrationMeta::query()->create([
            'organization_user_id' => $otherEmployee->id,
            'integratable_type' => OrganizationVoipConnection::class,
            'integratable_id' => $connection->id,
            'key' => 'extension',
            'value' => '101',
        ]);

        $this->expectException(ValidationException::class);

        app(UnmatchedVoipExtensionService::class)->assignExtensionToEmployee(
            organization: $organization,
            extension: '101',
            connectionId: $connection->id,
            organizationUserId: $employee->id,
        );
    }

    public function test_livewire_assign_unmatched_extension_requires_gate(): void
    {
        [$organization, $connection, $employer, $employee] = $this->setupOrganization(selfService: false);

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => 'call-1',
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '101',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'raw_payload' => ['resolved_extension' => '101'],
        ]);

        $this->actingAs($employer);

        Livewire::test(Index::class)
            ->set('unmatchedSelections.101__'.$connection->id, $employee->id)
            ->call('assignUnmatchedExtension', '101', $connection->id)
            ->assertForbidden();
    }

    public function test_livewire_assign_unmatched_extension_backfills_when_gate_enabled(): void
    {
        [$organization, $connection, $employer, $employee] = $this->setupOrganization(selfService: true);

        $log = VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => 'call-1',
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '101',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'raw_payload' => ['resolved_extension' => '101'],
        ]);

        Call::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'voip_call_log_id' => $log->id,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => 'call-1',
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '101',
            'status' => 'completed',
            'organization_user_id' => null,
        ]);

        $this->actingAs($employer);

        Livewire::test(Index::class)
            ->set('unmatchedSelections.101__'.$connection->id, $employee->id)
            ->call('assignUnmatchedExtension', '101', $connection->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calls', [
            'voip_call_log_id' => $log->id,
            'organization_user_id' => $employee->id,
        ]);
    }

    /**
     * @return array{0: Organization, 1: OrganizationVoipConnection, 2: User, 3: OrganizationUser}
     */
    private function setupOrganization(bool $selfService = true): array
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $factory = Organization::factory();

        if ($selfService) {
            $factory = $factory->withIntegrationSelfService();
        }

        $organization = $factory->create(['user_id' => $employer->id]);
        $provider = VoipProvider::query()->create([
            'name' => 'Custom',
            'code' => VoipProviderCode::Custom->value,
            'adapter_class' => NullVoipAdapter::class,
            'is_active' => true,
        ]);
        $connection = OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'Asterisk',
            'credentials' => [],
            'webhook_token' => str_repeat('a', 48),
            'is_default' => true,
            'is_active' => true,
            'ingestion_mode' => 'webhook',
        ]);
        $connection->syncLogs()->create([
            'operation' => VoipOperation::TestConnection,
            'status' => VoipLogStatus::Success,
            'message' => 'OK',
        ]);
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Ali',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);

        return [$organization, $connection, $employer, $employee];
    }
}
