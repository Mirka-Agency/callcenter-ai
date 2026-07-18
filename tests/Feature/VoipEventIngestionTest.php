<?php

namespace Tests\Feature;

use App\Application\Voip\Jobs\ProcessVoipIngestionJob;
use App\Application\Voip\Services\VoipEventIngestionService;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\DTOs\VoipConnectionConfig;
use App\Domain\Voip\DTOs\VoipCredentials;
use App\Domain\Voip\DTOs\VoipSettings;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipEventSource;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Domain\Voip\Events\CallEnded;
use App\Models\Organization;
use App\Models\VoipCallLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VoipEventIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingestion_upserts_call_log_before_dispatching_domain_events(): void
    {
        Event::fake([CallEnded::class]);

        $organization = Organization::factory()->create();
        $connection = $this->createConnection($organization->id);

        $config = new VoipConnectionConfig(
            connectionId: $connection->id,
            organizationId: $organization->id,
            providerCode: VoipProviderCode::Novatel,
            name: 'Primary',
            credentials: new VoipCredentials(apiUrl: 'https://example.com', apiKey: 'test'),
            settings: new VoipSettings,
            isActive: true,
        );

        $event = new NormalizedWebhookEvent(
            type: VoipWebhookEventType::CallEnded,
            callId: 'call-123',
            direction: CallDirection::Inbound,
            sourceNumber: '09120000000',
            destinationNumber: '02100000000',
            status: CallStatus::Completed,
            recordingUrl: 'https://example.com/rec.mp3',
            source: VoipEventSource::Webhook,
            provider: 'novatel',
        );

        app(VoipEventIngestionService::class)->ingest($config, $event);

        $this->assertDatabaseHas('voip_call_logs', [
            'organization_voip_connection_id' => $connection->id,
            'external_call_id' => 'call-123',
            'recording_url' => 'https://example.com/rec.mp3',
        ]);

        Event::assertDispatched(CallEnded::class);
    }

    public function test_duplicate_events_are_filtered(): void
    {
        Event::fake([CallEnded::class]);

        $organization = Organization::factory()->create();
        $connection = $this->createConnection($organization->id);

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => 'novatel',
            'external_call_id' => 'call-dup',
            'direction' => CallDirection::Inbound,
            'source_number' => '09120000000',
            'destination_number' => '02100000000',
            'status' => CallStatus::Completed,
            'ended_at' => now(),
            'recording_url' => 'https://example.com/rec.mp3',
        ]);

        $config = new VoipConnectionConfig(
            connectionId: $connection->id,
            organizationId: $organization->id,
            providerCode: VoipProviderCode::Novatel,
            name: 'Primary',
            credentials: new VoipCredentials(apiUrl: 'https://example.com', apiKey: 'test'),
            settings: new VoipSettings,
            isActive: true,
        );

        $event = new NormalizedWebhookEvent(
            type: VoipWebhookEventType::CallEnded,
            callId: 'call-dup',
            status: CallStatus::Completed,
            recordingUrl: 'https://example.com/rec.mp3',
            source: VoipEventSource::Webhook,
        );

        $result = app(VoipEventIngestionService::class)->ingest($config, $event);

        $this->assertTrue($result->data['duplicate'] ?? false);
        Event::assertNotDispatched(CallEnded::class);
    }

    public function test_force_replay_bypasses_duplicate_filter(): void
    {
        Event::fake([CallEnded::class]);

        $organization = Organization::factory()->create();
        $connection = $this->createConnection($organization->id);

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => 'novatel',
            'external_call_id' => 'call-replay',
            'direction' => CallDirection::Inbound,
            'source_number' => '09120000000',
            'destination_number' => '02100000000',
            'status' => CallStatus::Completed,
            'ended_at' => now(),
            'recording_url' => 'https://example.com/rec.mp3',
        ]);

        $config = new VoipConnectionConfig(
            connectionId: $connection->id,
            organizationId: $organization->id,
            providerCode: VoipProviderCode::Novatel,
            name: 'Primary',
            credentials: new VoipCredentials(apiUrl: 'https://example.com', apiKey: 'test'),
            settings: new VoipSettings,
            isActive: true,
        );

        $event = new NormalizedWebhookEvent(
            type: VoipWebhookEventType::CallEnded,
            callId: 'call-replay',
            direction: CallDirection::Inbound,
            sourceNumber: '09120000000',
            destinationNumber: '02100000000',
            status: CallStatus::Completed,
            recordingUrl: 'https://example.com/rec.mp3',
            source: VoipEventSource::Webhook,
        );

        $result = app(VoipEventIngestionService::class)->ingest($config, $event, forceReplay: true);

        $this->assertFalse($result->data['duplicate'] ?? false);
        Event::assertDispatched(CallEnded::class);
    }

    public function test_webhook_job_queues_unified_ingestion_job(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        $connection = $this->createConnection($organization->id);

        (new \App\Application\Voip\Jobs\ProcessVoipWebhookJob(
            $connection->id,
            ['event' => 'call.ended', 'call_id' => 'abc'],
        ))->handle(app(\App\Application\Voip\Services\VoipConnectionResolver::class));

        Queue::assertPushed(ProcessVoipIngestionJob::class, function (ProcessVoipIngestionJob $job) use ($connection) {
            return $job->connectionId === $connection->id
                && ($job->normalizedEvent['source'] ?? null) === 'webhook';
        });
    }

    private function createConnection(int $organizationId): \App\Models\OrganizationVoipConnection
    {
        $provider = \App\Models\VoipProvider::query()->firstOrCreate(
            ['code' => VoipProviderCode::Novatel->value],
            [
                'name' => 'Test',
                'adapter_class' => \App\Infrastructure\Voip\Adapters\NullVoipAdapter::class,
                'supports_webhook' => true,
                'supports_polling' => false,
                'polling_interval_seconds' => 30,
                'is_active' => true,
            ],
        );

        return \App\Models\OrganizationVoipConnection::query()->create([
            'organization_id' => $organizationId,
            'voip_provider_id' => $provider->id,
            'name' => 'Conn',
            'credentials' => ['api_url' => 'https://example.com', 'api_key' => 'x'],
            'is_active' => true,
            'ingestion_mode' => 'webhook',
            'polling_enabled' => false,
        ]);
    }
}
