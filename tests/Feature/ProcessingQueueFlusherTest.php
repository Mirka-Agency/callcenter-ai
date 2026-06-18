<?php

namespace Tests\Feature;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Domain\Processing\Enums\ProcessingJobStage;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Models\Call;
use App\Models\CallProcessingJob;
use App\Models\Organization;
use App\Services\ProcessingQueueFlusher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessingQueueFlusherTest extends TestCase
{
    use RefreshDatabase;

    public function test_processing_queue_flush_cancels_active_ui_jobs_and_clears_laravel_jobs(): void
    {
        $organization = Organization::factory()->create();
        $call = $this->createCall($organization->id);

        $job = CallProcessingJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'call_id' => $call->id,
            'organization_id' => $organization->id,
            'file_name' => 'sample.wav',
            'status' => ProcessingJobStatus::Queued,
            'stage' => ProcessingJobStage::Queued,
            'progress_percentage' => ProcessingJobStage::Queued->progress(),
            'queued_at' => now(),
        ]);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => (string) Str::uuid(),
                'displayName' => AnalyzeAudioJob::class,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => [
                    'commandName' => AnalyzeAudioJob::class,
                    'command' => serialize(new AnalyzeAudioJob($call->id)),
                ],
            ]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->artisan('processing-queue:flush')
            ->assertSuccessful();

        $job->refresh();

        $this->assertSame(ProcessingJobStatus::Cancelled, $job->status);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_queue_flush_command_syncs_stale_ui_jobs(): void
    {
        $organization = Organization::factory()->create();
        $call = $this->createCall($organization->id);

        $job = CallProcessingJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'call_id' => $call->id,
            'organization_id' => $organization->id,
            'file_name' => 'stale.wav',
            'status' => ProcessingJobStatus::Queued,
            'stage' => ProcessingJobStage::Queued,
            'progress_percentage' => ProcessingJobStage::Queued->progress(),
            'queued_at' => now(),
        ]);

        $this->artisan('queue:flush')
            ->assertSuccessful();

        $job->refresh();

        $this->assertSame(ProcessingJobStatus::Cancelled, $job->status);
    }

    public function test_sync_orphans_keeps_queued_jobs_dispatched_via_analyze_audio_chain(): void
    {
        config(['queue.default' => 'database']);
        DB::table('jobs')->delete();

        $organization = Organization::factory()->create();
        $call = $this->createCall($organization->id);

        $job = CallProcessingJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'call_id' => $call->id,
            'organization_id' => $organization->id,
            'file_name' => 'queued.wav',
            'status' => ProcessingJobStatus::Queued,
            'stage' => ProcessingJobStage::Queued,
            'progress_percentage' => ProcessingJobStage::Queued->progress(),
            'queued_at' => now(),
        ]);

        AnalyzeAudioJob::dispatchChain($call->id);

        $reconciled = app(ProcessingQueueFlusher::class)->syncOrphans($organization->id);

        $job->refresh();

        $this->assertSame(0, $reconciled);
        $this->assertSame(ProcessingJobStatus::Queued, $job->status);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_sync_orphans_cancels_queued_jobs_without_laravel_backing(): void
    {
        $organization = Organization::factory()->create();
        $call = $this->createCall($organization->id);

        $job = CallProcessingJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'call_id' => $call->id,
            'organization_id' => $organization->id,
            'file_name' => 'orphan.wav',
            'status' => ProcessingJobStatus::Queued,
            'stage' => ProcessingJobStage::Queued,
            'progress_percentage' => ProcessingJobStage::Queued->progress(),
            'queued_at' => now()->subMinutes(5),
        ]);

        $reconciled = app(ProcessingQueueFlusher::class)->syncOrphans($organization->id);

        $job->refresh();

        $this->assertSame(1, $reconciled);
        $this->assertSame(ProcessingJobStatus::Cancelled, $job->status);
    }

    private function createCall(int $organizationId): Call
    {
        return Call::query()->create([
            'organization_id' => $organizationId,
            'provider_code' => 'manual',
            'external_call_id' => (string) Str::uuid(),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => 'completed',
        ]);
    }
}
