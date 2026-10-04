<?php

namespace Tests\Feature;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Application\Intelligence\Jobs\SyncCrmJob;
use App\Application\Intelligence\Jobs\UpdateEmployeeMetricsJob;
use App\Application\Intelligence\Services\CallAnalysisQueueService;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Enums\UserRole;
use App\Infrastructure\Voip\Adapters\NullVoipAdapter;
use App\Livewire\Employer\Organization\Profile;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\EmployeeIntegrationMeta;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\PlatformAiSettings;
use App\Models\User;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use App\Services\CallIntake\Filters\InternalAgentCallsFilter;
use App\Services\CallIntake\Filters\UnassignedAgentCallsFilter;
use App\Services\Reports\OrganizationCallMetrics;
use Database\Seeders\PlatformFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class CallIntakeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_profile_saves_intake_filters(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create([
            'user_id' => $employer->id,
            'title' => 'آوا',
        ]);

        $this->actingAs($employer);

        Livewire::test(Profile::class)
            ->assertSee('فیلتر تماس‌های ورودی')
            ->assertSee('تحلیل تماس بین دو کارشناس داخلی')
            ->assertSee('تحلیل تماس‌هایی که کارشناس برایشان تعریف نشده')
            ->set('intakeFilters.'.InternalAgentCallsFilter::KEY, false)
            ->set('intakeFilters.'.UnassignedAgentCallsFilter::KEY, true)
            ->call('saveIntakeFilters')
            ->assertHasNoErrors();

        $stored = $organization->fresh()->call_intake_filters;

        $this->assertFalse($stored[InternalAgentCallsFilter::KEY]);
        $this->assertTrue($stored[UnassignedAgentCallsFilter::KEY]);
    }

    public function test_disabled_internal_filter_skips_analysis_and_call_counts(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);
        $this->seed(PlatformFoundationSeeder::class);

        [$organization, $connection, $agent] = $this->organizationWithExtensions();
        $internal = $this->callBetween($organization, $connection, $agent, '101', '102', 'internal-1');
        $external = $this->callBetween($organization, $connection, $agent, '09120000000', '101', 'external-1');

        $this->assertTrue($internal->is_internal_agent_call);
        $this->assertFalse($external->is_internal_agent_call);
        $this->assertSame(2, app(OrganizationCallMetrics::class)->countToday($organization->id));
        $this->assertTrue(app(CallAnalysisQueueService::class)->dispatchForCall($internal));

        $organization->update([
            'call_intake_filters' => [
                InternalAgentCallsFilter::KEY => false,
                UnassignedAgentCallsFilter::KEY => false,
            ],
        ]);

        $this->assertSame(1, app(OrganizationCallMetrics::class)->countToday($organization->id));
        $this->assertFalse(app(CallAnalysisQueueService::class)->dispatchForCall($internal->fresh(), forceReanalyze: true));
        $this->assertSame(CallProcessingStatus::Skipped, $internal->fresh()->processing_status);
    }

    public function test_enabled_unassigned_filter_sends_calls_without_an_agent_to_analysis_and_counts(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $organization = Organization::factory()->create();
        $agent = $this->employee($organization);
        $assigned = $this->recordedCall($organization, [
            'organization_user_id' => $agent->id,
            'external_call_id' => 'assigned',
            'caller_number' => '09120000001',
            'receiver_number' => '101',
        ]);
        $unassigned = $this->recordedCall($organization, [
            'organization_user_id' => null,
            'external_call_id' => 'unassigned',
            'caller_number' => '09120000002',
            'receiver_number' => '900',
        ]);

        $this->assertSame(1, app(OrganizationCallMetrics::class)->countToday($organization->id));
        $this->assertFalse(app(CallAnalysisQueueService::class)->dispatchForCall($unassigned));
        Bus::assertNotDispatched(AnalyzeAudioJob::class);

        $organization->update([
            'call_intake_filters' => [
                InternalAgentCallsFilter::KEY => true,
                UnassignedAgentCallsFilter::KEY => true,
            ],
        ]);

        $this->assertSame(2, app(OrganizationCallMetrics::class)->countToday($organization->id));
        Bus::assertChained([
            AnalyzeAudioJob::class,
            UpdateEmployeeMetricsJob::class,
            SyncCrmJob::class,
        ]);
        $this->assertTrue(app(CallAnalysisQueueService::class)->dispatchForCall($assigned->fresh()));
    }

    /**
     * @return array{0: Organization, 1: OrganizationVoipConnection, 2: OrganizationUser}
     */
    private function organizationWithExtensions(): array
    {
        $organization = Organization::factory()->create();
        $agent = $this->employee($organization);
        $peer = $this->employee($organization);
        $provider = VoipProvider::query()->where('code', VoipProviderCode::Custom->value)->first()
            ?? VoipProvider::query()->create([
                'name' => 'Custom',
                'code' => VoipProviderCode::Custom->value,
                'adapter_class' => NullVoipAdapter::class,
                'is_active' => true,
            ]);
        $connection = OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'خط',
            'credentials' => [],
            'webhook_token' => str_repeat('b', 48),
            'is_default' => true,
            'is_active' => true,
            'ingestion_mode' => 'webhook',
        ]);

        foreach (['101' => $agent, '102' => $peer] as $extension => $employee) {
            EmployeeIntegrationMeta::query()->create([
                'organization_user_id' => $employee->id,
                'integratable_type' => OrganizationVoipConnection::class,
                'integratable_id' => $connection->id,
                'key' => 'extension',
                'value' => $extension,
            ]);
        }

        return [$organization, $connection, $agent];
    }

    private function callBetween(
        Organization $organization,
        OrganizationVoipConnection $connection,
        OrganizationUser $agent,
        string $source,
        string $destination,
        string $externalId,
    ): Call {
        $log = VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => $externalId,
            'direction' => 'inbound',
            'source_number' => $source,
            'destination_number' => $destination,
            'status' => 'completed',
            'started_at' => now('Asia/Tehran')->startOfDay()->addHours(10)->utc(),
            'recording_url' => 'https://pbx.example/'.$externalId.'.wav',
            'raw_payload' => [],
        ]);

        return $this->recordedCall($organization, [
            'organization_user_id' => $agent->id,
            'organization_voip_connection_id' => $connection->id,
            'voip_call_log_id' => $log->id,
            'external_call_id' => $externalId,
            'caller_number' => $source,
            'receiver_number' => $destination,
            'started_at' => $log->started_at,
        ]);
    }

    private function employee(Organization $organization): OrganizationUser
    {
        return OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create()->id,
            'first_name' => 'کارشناس',
            'last_name' => 'تست',
            'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function recordedCall(Organization $organization, array $overrides): Call
    {
        $startedAt = $overrides['started_at'] ?? now('Asia/Tehran')->startOfDay()->addHours(10)->utc();

        $call = Call::query()->create(array_merge([
            'organization_id' => $organization->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'custom',
            'direction' => 'inbound',
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Pending,
            'duration_seconds' => 40,
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addSeconds(40),
        ], $overrides));

        CallRecording::query()->create([
            'call_id' => $call->id,
            'source_url' => 'https://pbx.example/'.$call->id.'.wav',
            'storage_disk' => 'local',
            'storage_path' => 'recordings/'.$call->id.'.wav',
            'status' => 'completed',
            'is_expired' => false,
        ]);

        return $call->fresh();
    }
}
