<?php

namespace Tests\Feature;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Application\Llm\Services\AudioAnalyzer;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Domain\Llm\Exceptions\LlmTransientException;
use App\Domain\Processing\Enums\ProcessingJobStage;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Domain\Recording\Exceptions\RecordingNotFoundException;
use App\Models\Call;
use App\Models\CallProcessingJob;
use App\Models\CallRecording;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\PlatformAiSettings;
use App\Models\User;
use App\Services\RecordingStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyzeAudioJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_failure_is_recorded_once_without_retry(): void
    {
        Storage::fake('local');
        [$call, $processingJob] = $this->seedCallWithRecording();
        $this->seedPlatformLlm();

        $this->mock(AudioAnalyzer::class, function ($mock) {
            $mock->shouldReceive('analyze')
                ->once()
                ->andThrow(new \RuntimeException('Failed to parse OpenAI audio analysis response.'));
        });

        $job = (new AnalyzeAudioJob($call->id))->withFakeQueueInteractions();
        $this->app->call([$job, 'handle']);

        $job->assertFailed();
        $call->refresh();
        $processingJob->refresh();

        $this->assertSame(CallProcessingStatus::Failed, $call->processing_status);
        $this->assertSame('Failed to parse OpenAI audio analysis response.', $call->processing_error);
        $this->assertSame(ProcessingJobStatus::Failed, $processingJob->status);
        $this->assertDatabaseMissing('conversation_analyses', ['call_id' => $call->id]);
    }

    public function test_transient_llm_error_is_recorded_once_without_retry(): void
    {
        Storage::fake('local');
        [$call, $processingJob] = $this->seedCallWithRecording();
        $this->seedPlatformLlm();

        $this->mock(AudioAnalyzer::class, function ($mock) {
            $mock->shouldReceive('analyze')
                ->once()
                ->andThrow(LlmTransientException::fromProviderError('OpenAI API error (HTTP 429): rate_limit_exceeded'));
        });

        $job = (new AnalyzeAudioJob($call->id))->withFakeQueueInteractions();
        $this->app->call([$job, 'handle']);

        $job->assertFailed();
        $call->refresh();
        $processingJob->refresh();

        $this->assertSame(CallProcessingStatus::Failed, $call->processing_status);
        $this->assertSame(ProcessingJobStatus::Failed, $processingJob->status);
        $this->assertStringContainsString('429', (string) $call->processing_error);
    }

    public function test_missing_recording_is_recorded_once_without_retry(): void
    {
        Storage::fake('local');
        [$call, $processingJob] = $this->seedCallWithRecording();

        $this->mock(RecordingStorage::class, function ($mock) {
            $mock->shouldReceive('assertExists')
                ->once()
                ->andThrow(RecordingNotFoundException::forPath('recordings/test.mp3'));
        });

        $job = (new AnalyzeAudioJob($call->id))->withFakeQueueInteractions();
        $this->app->call([$job, 'handle']);

        $job->assertFailed();
        $call->refresh();
        $processingJob->refresh();

        $this->assertSame(CallProcessingStatus::Failed, $call->processing_status);
        $this->assertSame(ProcessingJobStatus::Failed, $processingJob->status);
        $this->assertDatabaseMissing('conversation_analyses', ['call_id' => $call->id]);
    }

    public function test_skips_remote_llm_and_does_not_call_avalai_when_disabled(): void
    {
        Storage::fake('local');
        Http::fake();
        config(['llm.remote_enabled' => false]);

        [$call, $processingJob] = $this->seedCallWithRecording();

        $this->mock(AudioAnalyzer::class, function ($mock) {
            $mock->shouldNotReceive('analyze');
        });

        $job = (new AnalyzeAudioJob($call->id))->withFakeQueueInteractions();
        $this->app->call([$job, 'handle']);

        $job->assertNotFailed();
        $call->refresh();
        $processingJob->refresh();

        $this->assertSame(CallProcessingStatus::Failed, $call->processing_status);
        $this->assertStringContainsString('AvalAI', (string) $call->processing_error);
        $this->assertSame(ProcessingJobStatus::Failed, $processingJob->status);
        $this->assertDatabaseMissing('conversation_analyses', ['call_id' => $call->id]);
        Http::assertNothingSent();
    }

    public function test_skips_short_voip_calls_before_download_or_analysis(): void
    {
        Storage::fake('local');
        Http::fake();

        [$call, $processingJob] = $this->seedCallWithRecording([
            'source' => ConversationSource::Voip,
            'duration_seconds' => 2,
        ]);

        $this->mock(AudioAnalyzer::class, function ($mock) {
            $mock->shouldNotReceive('analyze');
        });

        $job = (new AnalyzeAudioJob($call->id))->withFakeQueueInteractions();
        $this->app->call([$job, 'handle']);

        $job->assertNotFailed();
        $call->refresh();
        $processingJob->refresh();

        $this->assertSame(CallProcessingStatus::Skipped, $call->processing_status);
        $this->assertSame(ProcessingJobStatus::Failed, $processingJob->status);
        $this->assertDatabaseMissing('conversation_analyses', ['call_id' => $call->id]);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Call, 1: CallProcessingJob}
     */
    private function seedCallWithRecording(array $overrides = []): array
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

        $call = Call::query()->create(array_merge([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::ManualUpload,
            'provider_code' => 'manual',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Pending,
            'duration_seconds' => 120,
            'started_at' => now(),
        ], $overrides));

        Storage::disk('local')->put('recordings/test.mp3', 'audio');

        CallRecording::query()->create([
            'call_id' => $call->id,
            'storage_disk' => 'local',
            'storage_path' => 'recordings/test.mp3',
            'mime_type' => 'audio/mpeg',
            'status' => 'completed',
        ]);

        $processingJob = CallProcessingJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'call_id' => $call->id,
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'uploader_id' => $user->id,
            'file_name' => 'test.mp3',
            'status' => ProcessingJobStatus::Queued,
            'stage' => ProcessingJobStage::Queued,
            'progress_percentage' => 25,
            'upload_started_at' => now()->subMinutes(5),
            'upload_completed_at' => now()->subMinutes(4),
            'queued_at' => now()->subMinutes(4),
        ]);

        return [$call, $processingJob];
    }

    private function seedPlatformLlm(): void
    {
        $provider = LlmProvider::query()->create([
            'name' => 'OpenAI',
            'code' => LlmProviderCode::OpenAi->value,
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        $model = LlmModel::query()->create([
            'provider_id' => $provider->id,
            'name' => 'gpt-4o',
            'model_key' => 'gpt-4o',
            'input_price_per_million_tokens' => 1,
            'output_price_per_million_tokens' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        PlatformAiSettings::current()->update([
            'default_llm_provider_id' => $provider->id,
            'default_llm_model_id' => $model->id,
            'allow_negative_balance' => true,
        ]);
    }
}
