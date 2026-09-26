<?php

namespace Tests\Feature;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Application\Intelligence\Jobs\SyncCrmJob;
use App\Application\Intelligence\Jobs\UpdateEmployeeMetricsJob;
use App\Application\Intelligence\Services\CallAnalysisQueueService;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\PlatformAiSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CallAnalysisQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_queue_a_second_analysis_when_one_already_exists(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $call = $this->seedCall();
        $this->createAnalysis($call);

        $queued = app(CallAnalysisQueueService::class)->dispatchForCall($call);

        $this->assertFalse($queued);
        Bus::assertNothingDispatched();
    }

    public function test_does_not_automatically_retry_a_failed_analysis(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $call = $this->seedCall(['processing_status' => CallProcessingStatus::Failed]);

        $queued = app(CallAnalysisQueueService::class)->dispatchForCall($call);

        $this->assertFalse($queued);
        Bus::assertNothingDispatched();
    }

    public function test_force_reanalyze_queues_a_failed_call_again(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $call = $this->seedCall(['processing_status' => CallProcessingStatus::Failed]);

        $queued = app(CallAnalysisQueueService::class)->dispatchForCall($call, forceReanalyze: true);

        $this->assertTrue($queued);
        Bus::assertChained([
            AnalyzeAudioJob::class,
            UpdateEmployeeMetricsJob::class,
            SyncCrmJob::class,
        ]);
    }

    public function test_does_not_queue_short_calls_without_conversation(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $call = $this->seedCall([
            'duration_seconds' => 4,
            'status' => 'completed',
        ]);

        $queued = app(CallAnalysisQueueService::class)->dispatchForCall($call);

        $this->assertFalse($queued);
        Bus::assertNothingDispatched();
        $this->assertSame(CallProcessingStatus::Skipped, $call->fresh()->processing_status);
        $this->assertNotNull($call->fresh()->processing_error);
    }

    public function test_does_not_queue_missed_calls_even_with_recording(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $call = $this->seedCall([
            'duration_seconds' => 25,
            'status' => 'missed',
        ]);

        $queued = app(CallAnalysisQueueService::class)->dispatchForCall($call);

        $this->assertFalse($queued);
        Bus::assertNothingDispatched();
        $this->assertSame(CallProcessingStatus::Skipped, $call->fresh()->processing_status);
    }

    public function test_does_not_skip_short_manual_uploads(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $call = $this->seedCall([
            'source' => ConversationSource::ManualUpload,
            'duration_seconds' => 3,
            'status' => 'completed',
        ]);

        $queued = app(CallAnalysisQueueService::class)->dispatchForCall($call);

        $this->assertTrue($queued);
        Bus::assertChained([
            AnalyzeAudioJob::class,
            UpdateEmployeeMetricsJob::class,
            SyncCrmJob::class,
        ]);
    }

    public function test_queues_calls_at_or_above_minimum_duration(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $call = $this->seedCall([
            'duration_seconds' => 10,
            'status' => 'completed',
        ]);

        $queued = app(CallAnalysisQueueService::class)->dispatchForCall($call);

        $this->assertTrue($queued);
        Bus::assertChained([
            AnalyzeAudioJob::class,
            UpdateEmployeeMetricsJob::class,
            SyncCrmJob::class,
        ]);
    }

    public function test_does_not_queue_unanswered_calls_when_only_ring_time_was_recorded(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $call = $this->seedCall([
            'duration_seconds' => 42,
            'status' => 'completed',
            'metadata' => [
                'disposition' => 'ANSWERED',
                'duration' => 42,
                'billsec' => 0,
            ],
        ]);

        $queued = app(CallAnalysisQueueService::class)->dispatchForCall($call);

        $this->assertFalse($queued);
        Bus::assertNothingDispatched();
        $call->refresh();
        $this->assertSame(CallProcessingStatus::Skipped, $call->processing_status);
        $this->assertStringContainsString('بدون مکالمه', (string) $call->processing_error);
    }

    public function test_does_not_queue_calls_whose_disposition_is_no_answer(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $call = $this->seedCall([
            'duration_seconds' => 36,
            'status' => 'completed',
            'metadata' => [
                'disposition' => 'NO ANSWER',
                'duration' => 36,
            ],
        ]);

        $queued = app(CallAnalysisQueueService::class)->dispatchForCall($call);

        $this->assertFalse($queued);
        Bus::assertNothingDispatched();
        $this->assertSame(CallProcessingStatus::Skipped, $call->fresh()->processing_status);
    }

    public function test_queues_a_connected_call_with_talk_time(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $call = $this->seedCall([
            'duration_seconds' => 48,
            'status' => 'completed',
            'metadata' => [
                'disposition' => 'ANSWERED',
                'duration' => 48,
                'billsec' => 31,
            ],
        ]);

        $this->assertTrue(app(CallAnalysisQueueService::class)->dispatchForCall($call));
    }

    public function test_force_reanalyze_does_not_bypass_short_call_skip(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $call = $this->seedCall([
            'duration_seconds' => 3,
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Skipped,
        ]);

        $queued = app(CallAnalysisQueueService::class)->dispatchForCall($call, forceReanalyze: true);

        $this->assertFalse($queued);
        Bus::assertNothingDispatched();
        $this->assertSame(CallProcessingStatus::Skipped, $call->fresh()->processing_status);
    }

    /** @param  array<string, mixed>  $overrides */
    private function seedCall(array $overrides = []): Call
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
            'source' => ConversationSource::Voip,
            'provider_code' => 'custom',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '101',
            'status' => 'completed',
            'duration_seconds' => 120,
            'processing_status' => CallProcessingStatus::Pending,
            'started_at' => now(),
        ], $overrides));

        CallRecording::query()->create([
            'call_id' => $call->id,
            'source_url' => 'https://example.test/recording.wav',
            'status' => 'completed',
            'storage_path' => 'recordings/test.wav',
            'storage_disk' => 'local',
            'is_expired' => false,
        ]);

        return $call->fresh();
    }

    private function createAnalysis(Call $call): void
    {
        ConversationAnalysis::query()->create([
            'organization_id' => $call->organization_id,
            'organization_user_id' => $call->organization_user_id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 80,
            'is_evaluable' => true,
            'summary' => 'test',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'analyzed_at' => now(),
        ]);
    }
}
