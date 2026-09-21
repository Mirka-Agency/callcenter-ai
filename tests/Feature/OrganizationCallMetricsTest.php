<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Infrastructure\Voip\Adapters\NullVoipAdapter;
use App\Models\Call;
use App\Models\EmployeeIntegrationMeta;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\User;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use App\Services\EmployerDashboardAnalytics;
use App\Services\Reports\OrganizationCallMetrics;
use Database\Seeders\PlatformFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationCallMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_todays_calls_from_call_records_not_only_voip_logs(): void
    {
        $this->seed(PlatformFoundationSeeder::class);

        $organization = $this->organization();
        $employee = $this->employee($organization);

        $this->createCall($organization, [
            'organization_user_id' => $employee->id,
            'external_call_id' => 'metrics-test-today',
            'started_at' => now()->startOfDay()->addHours(10),
            'ended_at' => now()->startOfDay()->addHours(10)->addMinutes(5),
        ]);

        $metrics = app(OrganizationCallMetrics::class);

        $this->assertSame(1, $metrics->countToday($organization->id));
        $this->assertSame(1, EmployerDashboardAnalytics::forOrganization($organization->id)->cockpit()['calls_today']);
    }

    public function test_ignores_calls_outside_today_window(): void
    {
        $this->seed(PlatformFoundationSeeder::class);

        $organization = $this->organization();
        $employee = $this->employee($organization);

        $this->createCall($organization, [
            'organization_user_id' => $employee->id,
            'external_call_id' => 'metrics-test-yesterday',
            'started_at' => now()->subDay()->setTime(15, 30),
            'ended_at' => now()->subDay()->setTime(15, 35),
        ]);

        $this->assertSame(0, app(OrganizationCallMetrics::class)->countToday($organization->id));
    }

    public function test_counts_todays_calls_when_voip_webhook_omitted_started_at(): void
    {
        $this->seed(PlatformFoundationSeeder::class);

        $organization = $this->organization();
        $employee = $this->employee($organization);

        $this->createCall($organization, [
            'organization_user_id' => $employee->id,
            'external_call_id' => 'metrics-test-null-start',
            'provider_code' => 'custom',
            'source' => ConversationSource::Voip,
            'receiver_number' => '41909000',
            'processing_status' => CallProcessingStatus::Pending,
            'started_at' => null,
            'ended_at' => null,
            'duration_seconds' => 180,
        ]);

        $this->assertSame(1, app(OrganizationCallMetrics::class)->countToday($organization->id));
        $this->assertSame(
            1,
            EmployerDashboardAnalytics::forOrganization($organization->id)->cockpit()['calls_today'],
        );
    }

    public function test_excludes_unassigned_calls_from_today_count(): void
    {
        $this->seed(PlatformFoundationSeeder::class);

        $organization = $this->organization();
        $employee = $this->employee($organization);

        $this->createCall($organization, [
            'organization_user_id' => $employee->id,
            'external_call_id' => 'assigned-today',
        ]);
        $this->createCall($organization, [
            'organization_user_id' => null,
            'external_call_id' => 'queue-unassigned',
            'receiver_number' => '41909000',
        ]);

        $this->assertSame(1, app(OrganizationCallMetrics::class)->countToday($organization->id));
    }

    public function test_when_extensions_are_defined_counts_only_those_employees(): void
    {
        $this->seed(PlatformFoundationSeeder::class);

        $organization = $this->organization();
        $definedEmployee = $this->employee($organization, 'Ali', 'Agent');
        $undefinedEmployee = $this->employee($organization, 'Sara', 'Queue');
        $connection = $this->voipConnection($organization);

        EmployeeIntegrationMeta::query()->create([
            'organization_user_id' => $definedEmployee->id,
            'integratable_type' => OrganizationVoipConnection::class,
            'integratable_id' => $connection->id,
            'key' => 'extension',
            'value' => '101',
        ]);

        $this->createCall($organization, [
            'organization_user_id' => $definedEmployee->id,
            'organization_voip_connection_id' => $connection->id,
            'external_call_id' => 'defined-ext-101',
            'receiver_number' => '101',
        ]);
        $this->createCall($organization, [
            'organization_user_id' => $undefinedEmployee->id,
            'organization_voip_connection_id' => $connection->id,
            'external_call_id' => 'employee-without-extension',
            'receiver_number' => '5001',
        ]);
        $this->createCall($organization, [
            'organization_user_id' => null,
            'organization_voip_connection_id' => $connection->id,
            'external_call_id' => 'unmatched-queue',
            'receiver_number' => '41909000',
        ]);

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => 'orphan-unmatched',
            'direction' => 'inbound',
            'source_number' => '09120000002',
            'destination_number' => '41909000',
            'status' => 'completed',
            'started_at' => now()->startOfDay()->addHours(11),
            'raw_payload' => ['resolved_extension' => '5001'],
        ]);

        $this->assertSame(1, app(OrganizationCallMetrics::class)->countToday($organization->id));
        $this->assertSame(
            1,
            EmployerDashboardAnalytics::forOrganization($organization->id)->cockpit()['calls_today'],
        );
    }

    private function organization(): Organization
    {
        $employer = User::factory()->employer()->create();

        return Organization::factory()->create(['user_id' => $employer->id]);
    }

    private function employee(Organization $organization, string $firstName = 'Ali', string $lastName = 'Agent'): OrganizationUser
    {
        return OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->employee()->create()->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_active' => true,
        ]);
    }

    private function voipConnection(Organization $organization): OrganizationVoipConnection
    {
        $provider = VoipProvider::query()->where('code', VoipProviderCode::Custom->value)->first()
            ?? VoipProvider::query()->create([
                'name' => 'Custom',
                'code' => VoipProviderCode::Custom->value,
                'adapter_class' => NullVoipAdapter::class,
                'is_active' => true,
            ]);

        return OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'Asterisk',
            'credentials' => [],
            'webhook_token' => str_repeat('a', 48),
            'is_default' => true,
            'is_active' => true,
            'ingestion_mode' => 'webhook',
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function createCall(Organization $organization, array $overrides): Call
    {
        $startedAt = $overrides['started_at'] ?? now()->startOfDay()->addHours(10);

        return Call::query()->create(array_merge([
            'organization_id' => $organization->id,
            'external_call_id' => 'metrics-call',
            'provider_code' => 'demo',
            'source' => ConversationSource::Imported,
            'direction' => 'inbound',
            'caller_number' => '09121234567',
            'receiver_number' => '02112345678',
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Analyzed,
            'started_at' => $startedAt,
            'ended_at' => $startedAt?->copy()->addMinutes(5),
            'duration_seconds' => 300,
        ], $overrides));
    }
}
