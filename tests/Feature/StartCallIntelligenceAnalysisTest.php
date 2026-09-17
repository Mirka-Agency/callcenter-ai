<?php

namespace Tests\Feature;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Application\Intelligence\Jobs\SyncCrmJob;
use App\Application\Intelligence\Jobs\UpdateEmployeeMetricsJob;
use App\Application\Intelligence\Listeners\StartCallIntelligenceAnalysis;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipOperation;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Domain\Voip\Events\CallEnded;
use App\Domain\Voip\Events\RecordingCreated;
use App\Enums\UserRole;
use App\Infrastructure\Voip\Adapters\NullVoipAdapter;
use App\Models\Call;
use App\Models\EmployeeIntegrationMeta;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\PlatformAiSettings;
use App\Models\User;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class StartCallIntelligenceAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_unassigned_voip_calls_are_ingested_but_not_analyzed(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        [$organization, $connection] = $this->setupOrganization();

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => 'unassigned-1',
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '101',
            'status' => 'completed',
            'started_at' => now(),
            'recording_url' => 'https://example.test/unassigned-1.wav',
            'raw_payload' => ['resolved_extension' => '101'],
        ]);

        app(StartCallIntelligenceAnalysis::class)->handleVoipEvent(new CallEnded(
            organizationId: $organization->id,
            connectionId: $connection->id,
            event: new NormalizedWebhookEvent(
                type: VoipWebhookEventType::CallEnded,
                callId: 'unassigned-1',
                direction: CallDirection::Inbound,
                sourceNumber: '09120000000',
                destinationNumber: '101',
                status: CallStatus::Completed,
                recordingUrl: 'https://example.test/unassigned-1.wav',
                extension: '101',
            ),
        ));

        $this->assertDatabaseHas('calls', [
            'external_call_id' => 'unassigned-1',
            'organization_user_id' => null,
        ]);
        Bus::assertNotDispatched(AnalyzeAudioJob::class);
    }

    public function test_assigned_voip_calls_are_queued_for_analysis(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        [$organization, $connection, $employee] = $this->setupOrganization(withEmployee: true);

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
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => 'assigned-1',
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '101',
            'status' => 'completed',
            'started_at' => now(),
            'recording_url' => 'https://example.test/assigned-1.wav',
            'raw_payload' => ['resolved_extension' => '101'],
        ]);

        app(StartCallIntelligenceAnalysis::class)->handleVoipEvent(new CallEnded(
            organizationId: $organization->id,
            connectionId: $connection->id,
            event: new NormalizedWebhookEvent(
                type: VoipWebhookEventType::CallEnded,
                callId: 'assigned-1',
                direction: CallDirection::Inbound,
                sourceNumber: '09120000000',
                destinationNumber: '101',
                status: CallStatus::Completed,
                recordingUrl: 'https://example.test/assigned-1.wav',
                extension: '101',
            ),
        ));

        $call = Call::query()->where('external_call_id', 'assigned-1')->first();

        $this->assertNotNull($call);
        $this->assertSame($employee->id, $call->organization_user_id);
        Bus::assertChained([
            AnalyzeAudioJob::class,
            UpdateEmployeeMetricsJob::class,
            SyncCrmJob::class,
        ]);
    }

    public function test_recording_created_does_not_queue_a_second_analysis_for_the_same_call(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        [$organization, $connection, $employee] = $this->setupOrganization(withEmployee: true);

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
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => 'assigned-2',
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '101',
            'status' => 'completed',
            'started_at' => now(),
            'recording_url' => 'https://example.test/assigned-2.wav',
            'raw_payload' => ['resolved_extension' => '101'],
        ]);

        $ended = new NormalizedWebhookEvent(
            type: VoipWebhookEventType::CallEnded,
            callId: 'assigned-2',
            direction: CallDirection::Inbound,
            sourceNumber: '09120000000',
            destinationNumber: '101',
            status: CallStatus::Completed,
            recordingUrl: 'https://example.test/assigned-2.wav',
            extension: '101',
        );

        $listener = app(StartCallIntelligenceAnalysis::class);
        $listener->handleVoipEvent(new CallEnded(
            organizationId: $organization->id,
            connectionId: $connection->id,
            event: $ended,
        ));
        $listener->handleVoipEvent(new RecordingCreated(
            organizationId: $organization->id,
            connectionId: $connection->id,
            event: new NormalizedWebhookEvent(
                type: VoipWebhookEventType::RecordingCreated,
                callId: 'assigned-2',
                direction: CallDirection::Inbound,
                sourceNumber: '09120000000',
                destinationNumber: '101',
                status: CallStatus::Completed,
                recordingUrl: 'https://example.test/assigned-2.wav',
                extension: '101',
            ),
        ));

        Bus::assertDispatched(AnalyzeAudioJob::class, 1);
    }

    /**
     * @return array{0: Organization, 1: OrganizationVoipConnection, 2?: OrganizationUser}
     */
    private function setupOrganization(bool $withEmployee = false): array
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->withIntegrationSelfService()->create(['user_id' => $employer->id]);
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
            'webhook_token' => str_repeat('b', 48),
            'is_default' => true,
            'is_active' => true,
            'ingestion_mode' => 'webhook',
        ]);
        $connection->syncLogs()->create([
            'operation' => VoipOperation::TestConnection,
            'status' => VoipLogStatus::Success,
            'message' => 'OK',
        ]);

        if (! $withEmployee) {
            return [$organization, $connection];
        }

        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Ali',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);

        return [$organization, $connection, $employee];
    }
}
