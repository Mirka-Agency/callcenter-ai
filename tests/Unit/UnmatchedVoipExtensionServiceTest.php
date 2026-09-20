<?php

namespace Tests\Unit;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Enums\UserRole;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Models\EmployeeIntegrationMeta;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\User;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnmatchedVoipExtensionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_extension_candidates_is_public_on_resolver(): void
    {
        $log = VoipCallLog::query()->make([
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '982191093492',
            'raw_payload' => [
                'resolved_extension' => '553',
                'exten' => '554',
            ],
        ]);

        $candidates = app(CallEmployeeResolver::class)->extensionCandidates($log);

        $this->assertSame(['553', '554', '982191093492', '09120000000'], $candidates);
    }

    public function test_extension_candidates_include_raw_extension_from_asterisk_payload(): void
    {
        $log = VoipCallLog::query()->make([
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '982191093492',
            'raw_payload' => [
                'extension' => '101',
            ],
        ]);

        $candidates = app(CallEmployeeResolver::class)->extensionCandidates($log);

        $this->assertSame(['101', '982191093492', '09120000000'], $candidates);
    }

    public function test_extension_candidates_include_exten_from_recording_filename(): void
    {
        $log = VoipCallLog::query()->make([
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '41909000',
            'recording_url' => 'http://192.168.2.16/mirka-call-recordings/2026/09/20/exten-116-09120000000.wav',
            'raw_payload' => [],
        ]);

        $candidates = app(CallEmployeeResolver::class)->extensionCandidates($log);

        $this->assertSame(['116', '41909000', '09120000000'], $candidates);
    }

    public function test_extension_candidates_ignore_queue_id_in_recording_filename(): void
    {
        $log = VoipCallLog::query()->make([
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '41909000',
            'recording_url' => 'http://192.168.2.16/mirka-call-recordings/2026/09/20/q-5001-09120000000.wav',
            'raw_payload' => [],
        ]);

        $candidates = app(CallEmployeeResolver::class)->extensionCandidates($log);

        $this->assertSame(['41909000', '09120000000'], $candidates);
        $this->assertNotContains('5001', $candidates);
    }

    public function test_resolver_matches_employee_using_raw_extension_without_resolved_extension(): void
    {
        [$organization, $connection] = $this->createOrganizationWithConnection();
        $employee = $this->createEmployee($organization);

        EmployeeIntegrationMeta::query()->create([
            'organization_user_id' => $employee->id,
            'integratable_type' => OrganizationVoipConnection::class,
            'integratable_id' => $connection->id,
            'key' => 'extension',
            'value' => '101',
        ]);

        $log = VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Simotel->value,
            'external_call_id' => 'asterisk-1',
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '982191093492',
            'status' => 'completed',
            'raw_payload' => [
                'extension' => '101',
            ],
        ]);

        $resolved = app(CallEmployeeResolver::class)->resolveFromCallLog($log);

        $this->assertSame($employee->id, $resolved);
    }

    public function test_list_unmatched_aggregates_recent_logs_without_mapped_employee(): void
    {
        [$organization, $connection] = $this->createOrganizationWithConnection();

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Simotel->value,
            'external_call_id' => 'call-1',
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '982191093492',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'raw_payload' => ['resolved_extension' => '553'],
        ]);

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Simotel->value,
            'external_call_id' => 'call-2',
            'direction' => 'inbound',
            'source_number' => '09121111111',
            'destination_number' => '982191093492',
            'status' => 'completed',
            'started_at' => now()->subHours(2),
            'raw_payload' => ['resolved_extension' => '553'],
        ]);

        $rows = app(UnmatchedVoipExtensionService::class)->listUnmatched($organization);

        $this->assertCount(1, $rows);
        $this->assertSame('553', $rows[0]['extension']);
        $this->assertSame($connection->id, $rows[0]['connection_id']);
        $this->assertSame(2, $rows[0]['call_count']);
        $this->assertSame('09121111111', $rows[0]['last_source_number']);
        $this->assertSame('982191093492', $rows[0]['last_destination_number']);
        $this->assertSame('inbound', $rows[0]['last_direction']);
        $this->assertSame('09121111111', $rows[0]['last_customer_number']);
    }

    public function test_list_unmatched_excludes_mapped_extensions(): void
    {
        [$organization, $connection] = $this->createOrganizationWithConnection();
        $employee = $this->createEmployee($organization);

        EmployeeIntegrationMeta::query()->create([
            'organization_user_id' => $employee->id,
            'integratable_type' => OrganizationVoipConnection::class,
            'integratable_id' => $connection->id,
            'key' => 'extension',
            'value' => '553',
        ]);

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Simotel->value,
            'external_call_id' => 'call-1',
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '982191093492',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'raw_payload' => ['resolved_extension' => '553'],
        ]);

        $rows = app(UnmatchedVoipExtensionService::class)->listUnmatched($organization);

        $this->assertSame([], $rows);
    }

    public function test_list_assigned_returns_manual_extensions_only(): void
    {
        [$organization, $connection] = $this->createOrganizationWithConnection();
        $employee = $this->createEmployee($organization);

        EmployeeIntegrationMeta::query()->create([
            'organization_user_id' => $employee->id,
            'integratable_type' => OrganizationVoipConnection::class,
            'integratable_id' => $connection->id,
            'key' => 'extension',
            'value' => '101',
        ]);

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Simotel->value,
            'external_call_id' => 'call-unlisted',
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '982191093492',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'raw_payload' => ['resolved_extension' => '553'],
        ]);

        $rows = app(UnmatchedVoipExtensionService::class)->listAssigned($organization);

        $this->assertCount(1, $rows);
        $this->assertSame('101', $rows[0]['extension']);
        $this->assertSame($employee->id, $rows[0]['employee_id']);
        $this->assertSame($connection->id, $rows[0]['connection_id']);
    }

    public function test_extension_employee_map_resolves_without_per_call_queries(): void
    {
        [$organization, $connection] = $this->createOrganizationWithConnection();
        $employee = $this->createEmployee($organization);

        EmployeeIntegrationMeta::query()->create([
            'organization_user_id' => $employee->id,
            'integratable_type' => OrganizationVoipConnection::class,
            'integratable_id' => $connection->id,
            'key' => 'extension',
            'value' => '101',
        ]);

        $resolver = app(CallEmployeeResolver::class);
        $map = $resolver->extensionEmployeeMapForOrganization($organization->id);

        $this->assertSame([$connection->id.'|101' => $employee->id], $map);

        $matched = VoipCallLog::query()->make([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '982191093492',
            'raw_payload' => ['extension' => '101'],
        ]);

        $unmatched = VoipCallLog::query()->make([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '982191093492',
            'raw_payload' => ['extension' => '999'],
        ]);

        $this->assertSame($employee->id, $resolver->resolveFromCallLogUsingMap($matched, $map));
        $this->assertNull($resolver->resolveFromCallLogUsingMap($unmatched, $map));
    }

    /** @return array{0: Organization, 1: OrganizationVoipConnection} */
    private function createOrganizationWithConnection(): array
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

        return [$organization, $connection];
    }

    private function createEmployee(Organization $organization): OrganizationUser
    {
        return OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Ali',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);
    }
}
