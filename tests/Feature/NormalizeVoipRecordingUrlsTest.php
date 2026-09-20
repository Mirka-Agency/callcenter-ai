<?php

namespace Tests\Feature;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Application\Intelligence\Jobs\SyncCrmJob;
use App\Application\Intelligence\Jobs\UpdateEmployeeMetricsJob;
use App\Application\Voip\Services\NormalizeVoipRecordingUrls;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Enums\UserRole;
use App\Infrastructure\Voip\Adapters\NullVoipAdapter;
use App\Models\Call;
use App\Models\CallRecording;
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

class NormalizeVoipRecordingUrlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewrites_basename_mixmonitor_urls_and_requeues_download_failures(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $organization = Organization::factory()->create();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Ali',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);
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
            'webhook_token' => str_repeat('c', 48),
            'is_active' => true,
            'ingestion_mode' => 'webhook',
        ]);

        $wrong = 'http://192.168.2.16/mirka-call-recordings/exten-116-09309194604-20260920-092247-1789879949.32755.wav';
        $fixed = 'http://192.168.2.16/mirka-call-recordings/2026/09/20/exten-116-09309194604-20260920-092247-1789879949.32755.wav';

        $log = VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => 'call-116',
            'direction' => 'inbound',
            'source_number' => '09309194604',
            'destination_number' => '116',
            'status' => 'completed',
            'recording_url' => $wrong,
        ]);

        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'organization_voip_connection_id' => $connection->id,
            'voip_call_log_id' => $log->id,
            'source' => ConversationSource::Voip,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => 'call-116',
            'direction' => 'inbound',
            'caller_number' => '09309194604',
            'receiver_number' => '116',
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Failed,
            'processing_error' => 'Failed to download recording.',
        ]);

        CallRecording::query()->create([
            'call_id' => $call->id,
            'source_url' => $wrong,
            'status' => 'failed',
        ]);

        $result = app(NormalizeVoipRecordingUrls::class)->run();

        $this->assertSame(1, $result['logs']);
        $this->assertSame(1, $result['recordings']);
        $this->assertSame(1, $result['requeued']);
        $this->assertSame($fixed, $log->fresh()->recording_url);
        $this->assertSame($fixed, CallRecording::query()->where('call_id', $call->id)->value('source_url'));
        Bus::assertChained([
            AnalyzeAudioJob::class,
            UpdateEmployeeMetricsJob::class,
            SyncCrmJob::class,
        ]);
    }

    public function test_artisan_command_supports_dry_run(): void
    {
        $organization = Organization::factory()->create();
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
            'webhook_token' => str_repeat('d', 48),
            'is_active' => true,
            'ingestion_mode' => 'webhook',
        ]);

        $wrong = 'http://192.168.2.16/mirka-call-recordings/exten-101-88530814-20260920-151855-1789901327.33144.wav';

        $log = VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => 'call-101',
            'direction' => 'inbound',
            'source_number' => '88530814',
            'destination_number' => '101',
            'status' => 'completed',
            'recording_url' => $wrong,
        ]);

        $this->artisan('voip:normalize-recording-urls', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame($wrong, $log->fresh()->recording_url);
    }
}
