<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\Organization;
use App\Services\RecordingRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecordingRetentionStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_recordings_are_not_purged_while_call_is_still_analyzing(): void
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
            'processing_status' => CallProcessingStatus::Analyzing,
        ]);

        $path = 'recordings/'.$call->id.'/sample.mp3';
        Storage::disk('local')->put($path, 'audio');

        CallRecording::query()->create([
            'call_id' => $call->id,
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'audio/mpeg',
            'status' => 'completed',
            'uploaded_at' => now()->subDays(11),
            'expires_at' => now()->subDay(),
            'is_expired' => false,
        ]);

        $this->assertSame(0, app(RecordingRetentionService::class)->purgeDue());
        Storage::disk('local')->assertExists($path);
    }

    public function test_retention_starts_after_analysis_is_scheduled(): void
    {
        config(['recordings.retention_days' => 10]);

        $organization = Organization::factory()->create();
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'provider_code' => 'manual',
            'external_call_id' => (string) Str::uuid(),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Analyzed,
        ]);

        $recording = CallRecording::query()->create([
            'call_id' => $call->id,
            'storage_disk' => 'local',
            'storage_path' => 'recordings/'.$call->id.'/sample.mp3',
            'mime_type' => 'audio/mpeg',
            'status' => 'completed',
            'uploaded_at' => now(),
            'expires_at' => null,
            'is_expired' => false,
        ]);

        app(RecordingRetentionService::class)->scheduleExpiryAfterAnalysis($recording);

        $recording->refresh();

        $this->assertNotNull($recording->expires_at);
        $this->assertTrue($recording->expires_at->greaterThan(now()->addDays(9)));
    }

    public function test_uploaded_recordings_without_expiry_are_not_purged(): void
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
            'processing_status' => CallProcessingStatus::Analyzing,
        ]);

        $path = 'recordings/'.$call->id.'/waiting.mp3';
        Storage::disk('local')->put($path, 'audio');

        CallRecording::query()->create([
            'call_id' => $call->id,
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'audio/mpeg',
            'status' => 'completed',
            'uploaded_at' => now()->subDays(30),
            'expires_at' => null,
            'is_expired' => false,
        ]);

        $this->assertSame(0, app(RecordingRetentionService::class)->purgeDue());
        Storage::disk('local')->assertExists($path);
    }
}
