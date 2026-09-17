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
