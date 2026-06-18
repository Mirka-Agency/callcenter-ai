<?php

namespace Tests\Feature;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Processing\Enums\ProcessingJobStage;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Models\Call;
use App\Models\CallProcessingJob;
use App\Models\CallRecording;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\ProcessingQueueJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessingQueueJobServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_requeues_failed_job_and_dispatches_analysis(): void
    {
        Bus::fake();
        Storage::fake('local');

        [$job] = $this->seedFailedJob();

        Storage::disk('local')->put('recordings/test.mp3', 'audio');

        app(ProcessingQueueJobService::class)->retry($job->fresh());

        $job->refresh();

        $this->assertSame(ProcessingJobStatus::Queued, $job->status);
        $this->assertSame(ProcessingJobStage::Queued, $job->stage);
        $this->assertNull($job->error_message);

        Bus::assertDispatched(AnalyzeAudioJob::class, fn (AnalyzeAudioJob $dispatched) => $dispatched->callId === $job->call_id);
    }

    public function test_delete_removes_failed_job_from_queue(): void
    {
        [$job] = $this->seedFailedJob();

        app(ProcessingQueueJobService::class)->delete($job);

        $this->assertDatabaseMissing('call_processing_jobs', ['id' => $job->id]);
    }

    public function test_cannot_retry_completed_job(): void
    {
        [$job] = $this->seedFailedJob();
        $job->update(['status' => ProcessingJobStatus::Completed]);

        $this->expectException(\RuntimeException::class);

        app(ProcessingQueueJobService::class)->retry($job->fresh());
    }

    /** @return array{0: CallProcessingJob} */
    private function seedFailedJob(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);

        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::ManualUpload,
            'provider_code' => 'manual',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'failed',
            'duration_seconds' => 120,
            'started_at' => now(),
        ]);

        CallRecording::query()->create([
            'call_id' => $call->id,
            'storage_disk' => 'local',
            'storage_path' => 'recordings/test.mp3',
            'mime_type' => 'audio/mpeg',
            'status' => 'completed',
        ]);

        $job = CallProcessingJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'call_id' => $call->id,
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'uploader_id' => $user->id,
            'file_name' => 'test.mp3',
            'status' => ProcessingJobStatus::Failed,
            'stage' => ProcessingJobStage::WaitingForAi,
            'progress_percentage' => 50,
            'upload_started_at' => now()->subMinutes(5),
            'upload_completed_at' => now()->subMinutes(4),
            'queued_at' => now()->subMinutes(4),
            'processing_started_at' => now()->subMinutes(3),
            'completed_at' => now(),
            'error_message' => 'Analysis failed',
        ]);

        return [$job];
    }
}
