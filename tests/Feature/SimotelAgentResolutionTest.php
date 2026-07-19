<?php

namespace Tests\Feature;

use App\Application\Voip\Jobs\ProcessVoipWebhookJob;
use App\Application\Voip\Services\VoipEventIngestionService;
use App\Domain\Voip\DTOs\VoipConnectionConfig;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Infrastructure\Voip\Services\SimotelAgentExtensionCache;
use App\Models\Organization;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipProvider;
use App\Models\VoipWebhookLog;
use App\Support\WebhookPayloadPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SimotelAgentResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_state_ingestion_logs_agent_state_without_call_log(): void
    {
        $connection = $this->createSimotelConnection();
        $config = VoipConnectionConfig::fromModel($connection->load('provider'));
        $adapter = new SimotelVoipAdapter;
        $adapter->configure($config);

        $payload = [
            'event_name' => 'New State',
            'exten' => '553',
            'state' => 'InUse',
            'participant' => '09120000000',
            'direction' => 'in',
            'cuid' => '1610778625.386',
            'unique_id' => '1610778625.386',
        ];

        $event = $adapter->normalizeWebhook($payload);
        app(VoipEventIngestionService::class)->ingest($config, $event, $payload);

        $this->assertSame('553', app(SimotelAgentExtensionCache::class)->get($connection->id, '1610778625.386'));
        $this->assertDatabaseMissing('voip_call_logs', [
            'external_call_id' => '1610778625.386',
        ]);
        $this->assertDatabaseHas('voip_webhook_logs', [
            'organization_voip_connection_id' => $connection->id,
            'event_type' => VoipWebhookEventType::AgentStateChanged->value,
            'status' => VoipLogStatus::Success->value,
        ]);
    }

    public function test_cdr_after_new_state_stores_resolved_extension(): void
    {
        Http::fake([
            'http://simotel.test/api/v4/reports/quick/*' => Http::response([
                'success' => 1,
                'data' => ['data' => []],
            ], 200),
        ]);

        $connection = $this->createSimotelConnection();
        $config = VoipConnectionConfig::fromModel($connection->load('provider'));
        $adapter = new SimotelVoipAdapter;
        $adapter->configure($config);

        $adapter->normalizeWebhook([
            'event_name' => 'New State',
            'exten' => '553',
            'state' => 'InUse',
            'cuid' => '1784375548.939408',
        ]);

        $event = $adapter->normalizeWebhook([
            'event_name' => 'Cdr',
            'src' => '09198202502',
            'dst' => '982191093492',
            'type' => 'incoming',
            'disposition' => 'ANSWERED',
            'billsec' => 106,
            'cuid' => '1784375548.939408',
            'did' => '982191093492',
        ]);

        app(VoipEventIngestionService::class)->ingest($config, $event);

        $this->assertDatabaseHas('voip_call_logs', [
            'organization_voip_connection_id' => $connection->id,
            'external_call_id' => '1784375548.939408',
            'destination_number' => '982191093492',
        ]);

        $log = $connection->callLogs()->where('external_call_id', '1784375548.939408')->first();
        $this->assertSame('553', $log?->raw_payload['resolved_extension'] ?? null);
    }

    public function test_webhook_payload_presenter_highlights_key_fields(): void
    {
        $highlights = app(WebhookPayloadPresenter::class)->highlights([
            'event_name' => 'Cdr',
            'src' => '09198202502',
            'dst' => '982191093492',
            'did' => '982191093492',
            'cuid' => '1784375548.939408',
            'disposition' => 'ANSWERED',
            'password' => 'secret',
        ]);

        $this->assertSame([
            'event_name' => 'Cdr',
            'src' => '09198202502',
            'dst' => '982191093492',
            'did' => '982191093492',
            'cuid' => '1784375548.939408',
            'disposition' => 'ANSWERED',
        ], $highlights);
    }

    public function test_replaying_stored_webhook_dispatches_process_job(): void
    {
        Bus::fake([ProcessVoipWebhookJob::class]);

        $connection = $this->createSimotelConnection();
        $payload = [
            'event_name' => 'Cdr',
            'src' => '09120000000',
            'dst' => '553',
            'type' => 'incoming',
            'disposition' => 'ANSWERED',
            'unique_id' => 'replay-1',
        ];

        $log = VoipWebhookLog::query()->create([
            'organization_voip_connection_id' => $connection->id,
            'event_type' => VoipWebhookEventType::CallEnded->value,
            'status' => VoipLogStatus::Success,
            'payload' => $payload,
            'message' => 'webhook_received',
        ]);

        ProcessVoipWebhookJob::dispatch($log->organization_voip_connection_id, $log->payload, forceReplay: true);

        Bus::assertDispatched(ProcessVoipWebhookJob::class, function (ProcessVoipWebhookJob $job) use ($connection, $payload): bool {
            return $job->connectionId === $connection->id
                && $job->payload === $payload
                && $job->forceReplay === true;
        });
    }

    public function test_webhook_call_details_service_diagnoses_did_only_cdr(): void
    {
        Http::fake([
            'http://simotel.test/api/v4/reports/quick/search' => Http::response([
                'success' => -2,
                'message' => 'Access denied: /reports/quick/search',
                'data' => '',
            ], 403),
            'http://simotel.test/api/v4/reports/quick/info' => Http::response([
                'success' => -2,
                'message' => 'Access denied: /reports/quick/info',
                'data' => '',
            ], 403),
        ]);

        $connection = $this->createSimotelConnection();
        $payload = [
            'event_name' => 'Cdr',
            'src' => '09198202502',
            'dst' => '982191093492',
            'type' => 'incoming',
            'disposition' => 'ANSWERED',
            'duration' => 213,
            'billsec' => 106,
            'cuid' => '1784375548.939408',
            'unique_id' => '1784375548.939408',
            'did' => '982191093492',
        ];

        $log = VoipWebhookLog::query()->create([
            'organization_voip_connection_id' => $connection->id,
            'event_type' => VoipWebhookEventType::CallEnded->value,
            'status' => VoipLogStatus::Success,
            'payload' => $payload,
            'message' => 'webhook_received',
        ]);

        $details = app(\App\Application\Voip\Services\VoipWebhookCallDetailsService::class)->forWebhookLog($log);

        $this->assertSame('1784375548.939408', $details['call_id']);
        $this->assertFalse($details['api']['success']);
        $this->assertStringContainsString('Access denied', (string) $details['api']['error']);
        $this->assertNotEmpty($details['diagnosis']);
        $this->assertTrue(collect($details['diagnosis'])->contains(
            fn (string $note) => str_contains($note, 'DID') || str_contains($note, 'record') || str_contains($note, 'API'),
        ));
    }

    private function createSimotelConnection(): OrganizationVoipConnection
    {
        $organization = Organization::factory()->create();
        $provider = VoipProvider::query()->create([
            'name' => 'Simotel',
            'code' => VoipProviderCode::Simotel->value,
            'adapter_class' => SimotelVoipAdapter::class,
            'supports_webhook' => true,
            'supports_polling' => false,
            'is_active' => true,
        ]);

        return OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'Simotel Conn',
            'credentials' => [
                'api_url' => 'http://simotel.test/API/v4',
                'api_key' => 'test-api-key',
            ],
            'settings' => [
                'extra' => ['context' => 'c2191093492'],
            ],
            'is_active' => true,
            'ingestion_mode' => 'webhook',
            'polling_enabled' => false,
        ]);
    }
}
