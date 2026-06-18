<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\CallRecording;
use App\Models\Organization;
use App\Services\RecordingRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecordingRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_expired_deletes_storage_and_marks_recording_expired(): void
    {
        Storage::fake('local');

        $organization = Organization::factory()->create();
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'provider_code' => 'manual',
            'external_call_id' => (string) Str::uuid(),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
        ]);

        $path = 'recordings/'.$call->id.'/sample.mp3';
        Storage::disk('local')->put($path, 'audio');

        $recording = CallRecording::query()->create([
            'call_id' => $call->id,
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'audio/mpeg',
            'status' => 'completed',
            'uploaded_at' => now()->subDays(11),
            'expires_at' => now()->subDay(),
            'is_expired' => false,
        ]);

        $purged = app(RecordingRetentionService::class)->purgeDue();

        $recording->refresh();

        $this->assertSame(1, $purged);
        $this->assertTrue($recording->is_expired);
        $this->assertNull($recording->storage_path);
        $this->assertSame('expired', $recording->status);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_playback_state_blocks_expired_recording(): void
    {
        $recording = new CallRecording([
            'storage_disk' => 'local',
            'storage_path' => null,
            'uploaded_at' => now()->subDays(11),
            'expires_at' => now()->subDay(),
            'is_expired' => true,
            'status' => 'expired',
        ]);

        $state = app(RecordingRetentionService::class)->playbackState(
            $recording,
            'https://example.com/fallback.mp3',
        );

        $this->assertTrue($state['expired']);
        $this->assertNull($state['url']);
    }

    public function test_signed_playback_url_expires_no_later_than_recording_expiry(): void
    {
        config(['recordings.disk' => 'local', 'recordings.playback_url_ttl_minutes' => 120]);

        $path = 'recordings/test-'.uniqid().'/sample.mp3';
        Storage::disk('local')->put($path, 'audio');

        $expiresAt = Carbon::now()->addHours(2);
        $recording = new CallRecording([
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'audio/mpeg',
            'uploaded_at' => now(),
            'expires_at' => $expiresAt,
            'is_expired' => false,
        ]);

        $url = app(\App\Services\RecordingUrlService::class)->resolve($recording);

        $this->assertNotNull($url);
        $this->assertStringContainsString('signature=', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('expires', $query);
        $this->assertLessThanOrEqual($expiresAt->timestamp, (int) $query['expires']);

        Storage::disk('local')->delete($path);
    }
}
