<?php

namespace Tests\Feature;

use App\Application\Voip\Services\VoipEventIngestionService;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Recording\Contracts\RecordingDownloaderInterface;
use App\Domain\Voip\DTOs\VoipConnectionConfig;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Events\CallEnded;
use App\Infrastructure\Recording\VoipAwareRecordingDownloader;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Models\Call;
use App\Models\Organization;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimotelVoipIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cdr_webhook_ingestion_stores_simotel_recording_url(): void
    {
        Event::fake([CallEnded::class]);

        $organization = Organization::factory()->create();
        $connection = $this->createSimotelConnection($organization);

        $config = VoipConnectionConfig::fromModel($connection->load('provider'));
        $adapter = new SimotelVoipAdapter;
        $adapter->configure($config);

        $event = $adapter->normalizeWebhook([
            'event_name' => 'CDR',
            'starttime' => '2021-01-16 06:30:37.471398',
            'endtime' => '2021-01-16 06:30:46.471398',
            'src' => '09120000000',
            'dst' => '553',
            'type' => 'incoming',
            'disposition' => 'ANSWERED',
            'billsec' => 9,
            'record' => '20210116_1610778618.378.mp3',
            'unique_id' => '1610778618.378',
        ]);

        app(VoipEventIngestionService::class)->ingest($config, $event);

        $this->assertDatabaseHas('voip_call_logs', [
            'organization_voip_connection_id' => $connection->id,
            'external_call_id' => '1610778618.378',
            'recording_url' => 'simotel://20210116_1610778618.378.mp3',
            'provider_code' => 'simotel',
        ]);

        Event::assertDispatched(CallEnded::class);
    }

    public function test_simotel_recording_downloader_posts_to_audio_download_endpoint(): void
    {
        Storage::fake(config('recordings.disk', 'local'));

        Http::fake([
            'http://simotel.test/API/v4/reports/audio/download' => Http::response('fake-mp3-bytes', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $organization = Organization::factory()->create();
        $connection = $this->createSimotelConnection($organization);

        $voipLog = VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Simotel->value,
            'external_call_id' => '1610778618.378',
            'direction' => 'inbound',
            'source_number' => '09120000000',
            'destination_number' => '553',
            'status' => 'completed',
            'recording_url' => 'simotel://20210116_1610778618.378.mp3',
        ]);

        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'voip_call_log_id' => $voipLog->id,
            'provider_code' => VoipProviderCode::Simotel->value,
            'external_call_id' => '1610778618.378',
            'source' => ConversationSource::Voip,
            'direction' => 'inbound',
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Pending,
            'caller_number' => '09120000000',
            'receiver_number' => '553',
        ]);

        /** @var VoipAwareRecordingDownloader $downloader */
        $downloader = app(RecordingDownloaderInterface::class);

        $this->assertInstanceOf(VoipAwareRecordingDownloader::class, $downloader);

        $result = $downloader->download('simotel://20210116_1610778618.378.mp3', $call->id);

        $this->assertTrue($result->success, $result->error ?? 'download failed');
        $this->assertSame('audio/mpeg', $result->mimeType);
        $this->assertSame(strlen('fake-mp3-bytes'), $result->fileSizeBytes);
        Storage::disk(config('recordings.disk', 'local'))->assertExists($result->storagePath);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://simotel.test/API/v4/reports/audio/download'
                && $request->method() === 'POST'
                && $request['file'] === '20210116_1610778618.378.mp3'
                && $request->hasHeader('X-APIKEY', 'test-api-key');
        });
    }

    private function createSimotelConnection(Organization $organization): OrganizationVoipConnection
    {
        $provider = VoipProvider::query()->create([
            'name' => 'Simotel',
            'code' => VoipProviderCode::Simotel->value,
            'adapter_class' => SimotelVoipAdapter::class,
            'supports_webhook' => true,
            'supports_polling' => false,
            'is_active' => true,
            'config' => ['default_api_url' => 'http://your-simotel-host/API/v4'],
        ]);

        return OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'Simotel Conn',
            'credentials' => [
                'api_url' => 'http://simotel.test/API/v4',
                'api_key' => 'test-api-key',
                'username' => 'api',
                'password' => 'secret',
            ],
            'is_active' => true,
            'ingestion_mode' => 'webhook',
            'polling_enabled' => false,
        ]);
    }
}
