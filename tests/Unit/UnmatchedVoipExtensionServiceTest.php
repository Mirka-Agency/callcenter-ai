<?php

namespace Tests\Unit;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Models\EmployeeIntegrationMeta;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\User;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use App\Enums\UserRole;
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
