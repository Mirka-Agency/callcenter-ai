<?php

namespace Tests\Feature;

use App\Application\Voip\Jobs\ResolveSimotelCallOutcomeJob;
use App\Application\Voip\Services\VoipConnectionResolver;
use App\Application\Voip\Services\VoipEventIngestionService;
use App\Domain\Voip\DTOs\VoipConnectionConfig;
use App\Domain\Voip\DTOs\VoipCredentials;
use App\Domain\Voip\DTOs\VoipSettings;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipEventSource;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Events\CallMissed;
use App\Domain\Voip\Events\CallStarted;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Models\Organization;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SimotelIncomingCallOutcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_call_creates_ringing_log_and_schedules_outcome_job(): void
    {
        Queue::fake();
        Event::fake([CallStarted::class]);

        $organization = Organization::factory()->create();
        $connection = $this->createSimotelConnection($organization->id);

        $config = $this->config($organization->id, $connection->id);
        $adapter = app(SimotelVoipAdapter::class);
        $adapter->configure($config);

        $event = $adapter->normalizeWebhook([
            'event_name' => 'IncomingCall',
            'number' => '09112322758',
            'cuid' => '1784985537.26183',
            'entry_point' => '982191093492',
        ])->withSource(VoipEventSource::Webhook, 'simotel');

        app(VoipEventIngestionService::class)->ingest($config, $event);

        $this->assertDatabaseHas('voip_call_logs', [
            'organization_voip_connection_id' => $connection->id,
            'external_call_id' => '1784985537.26183',
            'status' => CallStatus::Ringing->value,
            'source_number' => '09112322758',
        ]);

        Event::assertDispatched(CallStarted::class);
    }

    public function test_resolve_job_marks_missed_from_quick_search_disposition(): void
    {
        Event::fake([CallMissed::class]);

        $organization = Organization::factory()->create();
        $connection = $this->createSimotelConnection($organization->id);

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => 'simotel',
            'external_call_id' => '1784985537.26183',
            'direction' => CallDirection::Inbound,
            'source_number' => '09112322758',
            'destination_number' => '982191093492',
            'status' => CallStatus::Ringing,
            'started_at' => now()->subMinutes(3),
        ]);

        Http::fake([
            'http://simotel.test/api/v4/reports/quick/search' => Http::response([
                'success' => 1,
                'data' => [
                    'data' => [[
                        'cuid' => '1784985537.26183',
                        'src' => '09112322758',
                        'dst' => '101',
                        'type' => 'incoming',
                        'disposition' => 'NO ANSWER',
                        'duration' => 12,
                    ]],
                ],
            ], 200),
        ]);

        (new ResolveSimotelCallOutcomeJob(
            $organization->id,
            $connection->id,
            '1784985537.26183',
        ))->handle(
            app(VoipConnectionResolver::class),
            app(VoipEventIngestionService::class),
        );

        $this->assertDatabaseHas('voip_call_logs', [
            'external_call_id' => '1784985537.26183',
            'status' => CallStatus::Missed->value,
        ]);

        Event::assertDispatched(CallMissed::class);
    }

    public function test_resolve_job_marks_completed_when_simotel_says_answered(): void
    {
        Event::fake();

        $organization = Organization::factory()->create();
        $connection = $this->createSimotelConnection($organization->id);

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => 'simotel',
            'external_call_id' => 'answered-1',
            'direction' => CallDirection::Inbound,
            'source_number' => '09123186934',
            'destination_number' => '982191093492',
            'status' => CallStatus::Ringing,
            'started_at' => now()->subMinutes(3),
        ]);

        Http::fake([
            'http://simotel.test/api/v4/reports/quick/*' => Http::response([
                'success' => 1,
                'data' => [
                    'data' => [[
                        'cuid' => 'answered-1',
                        'src' => '09123186934',
                        'dst' => '09982220025',
                        'type' => 'incoming',
                        'disposition' => 'ANSWERED',
                        'billsec' => 115,
                        'duration' => 155,
                        'record' => '20260726_answered-1.mp3',
                    ]],
                ],
            ], 200),
        ]);

        (new ResolveSimotelCallOutcomeJob(
            $organization->id,
            $connection->id,
            'answered-1',
        ))->handle(
            app(VoipConnectionResolver::class),
            app(VoipEventIngestionService::class),
        );

        $this->assertDatabaseHas('voip_call_logs', [
            'external_call_id' => 'answered-1',
            'status' => CallStatus::Completed->value,
            'recording_url' => 'simotel://20260726_answered-1.mp3',
        ]);
    }

    public function test_resolve_job_leaves_ringing_when_api_access_denied(): void
    {
        $organization = Organization::factory()->create();
        $connection = $this->createSimotelConnection($organization->id);

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => 'simotel',
            'external_call_id' => 'denied-1',
            'direction' => CallDirection::Inbound,
            'source_number' => '09120000000',
            'destination_number' => '982191093492',
            'status' => CallStatus::Ringing,
            'started_at' => now()->subMinutes(3),
        ]);

        Http::fake([
            'http://simotel.test/api/v4/reports/quick/*' => Http::response([
                'success' => -2,
                'message' => 'Access denied: /reports/quick/search',
                'data' => '',
            ], 403),
        ]);

        (new ResolveSimotelCallOutcomeJob(
            $organization->id,
            $connection->id,
            'denied-1',
        ))->handle(
            app(VoipConnectionResolver::class),
            app(VoipEventIngestionService::class),
        );

        $log = VoipCallLog::query()->where('external_call_id', 'denied-1')->first();
        $this->assertSame(CallStatus::Ringing, $log?->status);
        $this->assertSame('simotel_api_access_denied', data_get($log?->raw_payload, 'outcome_error'));
    }

    public function test_resolve_job_does_not_false_miss_when_api_empty_on_first_attempt(): void
    {
        $organization = Organization::factory()->create();
        $connection = $this->createSimotelConnection($organization->id);

        VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => 'simotel',
            'external_call_id' => 'retry-1',
            'direction' => CallDirection::Inbound,
            'source_number' => '09120000000',
            'destination_number' => '982191093492',
            'status' => CallStatus::Ringing,
            'started_at' => now()->subMinutes(2),
        ]);

        Http::fake([
            'http://simotel.test/api/v4/reports/quick/*' => Http::response([
                'success' => 1,
                'data' => ['data' => []],
            ], 200),
        ]);

        $job = new ResolveSimotelCallOutcomeJob(
            $organization->id,
            $connection->id,
            'retry-1',
        );

        // Simulate first attempt: release() throws when not on a real queue worker.
        try {
            $job->handle(
                app(VoipConnectionResolver::class),
                app(VoipEventIngestionService::class),
            );
        } catch (\Throwable) {
            // InteractsWithQueue::release may throw outside a worker; status must stay ringing.
        }

        $this->assertDatabaseHas('voip_call_logs', [
            'external_call_id' => 'retry-1',
            'status' => CallStatus::Ringing->value,
        ]);
    }

    private function createSimotelConnection(int $organizationId): OrganizationVoipConnection
    {
        $provider = VoipProvider::query()->create([
            'name' => 'Simotel',
            'code' => VoipProviderCode::Simotel->value,
            'adapter_class' => SimotelVoipAdapter::class,
            'supports_webhook' => true,
            'supports_polling' => false,
            'polling_interval_seconds' => 30,
            'is_active' => true,
        ]);

        return OrganizationVoipConnection::query()->create([
            'organization_id' => $organizationId,
            'voip_provider_id' => $provider->id,
            'name' => 'astel',
            'credentials' => [
                'api_url' => 'http://simotel.test/api/v4',
                'api_key' => 'secret',
            ],
            'is_default' => true,
            'is_active' => true,
            'ingestion_mode' => 'webhook',
        ]);
    }

    private function config(int $organizationId, int $connectionId): VoipConnectionConfig
    {
        return new VoipConnectionConfig(
            connectionId: $connectionId,
            organizationId: $organizationId,
            providerCode: VoipProviderCode::Simotel,
            name: 'astel',
            credentials: new VoipCredentials(apiUrl: 'http://simotel.test/api/v4', apiKey: 'secret'),
            settings: new VoipSettings,
            isActive: true,
            adapterClass: SimotelVoipAdapter::class,
        );
    }
}
