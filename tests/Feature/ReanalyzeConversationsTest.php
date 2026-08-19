<?php

namespace Tests\Feature;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Application\Intelligence\Jobs\SyncCrmJob;
use App\Application\Intelligence\Jobs\UpdateEmployeeMetricsJob;
use App\Application\Intelligence\Services\ReanalyzeConversationsService;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Intelligence\Enums\ReanalyzeScope;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employer\Intelligence\Index;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\PlatformAiSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class ReanalyzeConversationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reanalyze_under_20_queues_low_score_calls_only(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        [$organization, $employer, $lowCall, $highCall] = $this->seedCalls();

        $queued = app(ReanalyzeConversationsService::class)->queue($organization, ReanalyzeScope::Under20);

        $this->assertSame(1, $queued);
        Bus::assertChained([
            AnalyzeAudioJob::class,
            UpdateEmployeeMetricsJob::class,
            SyncCrmJob::class,
        ]);
        $this->assertDatabaseHas('call_processing_jobs', [
            'call_id' => $lowCall->id,
        ]);
        $this->assertDatabaseMissing('call_processing_jobs', [
            'call_id' => $highCall->id,
        ]);
    }

    public function test_employer_can_trigger_reanalyze_from_intelligence_index(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        [, $employer] = $this->seedCalls();

        $this->actingAs($employer);

        Livewire::test(Index::class)
            ->call('reanalyzeConversations', 'under_50')
            ->assertHasNoErrors();
    }

    public function test_reanalyze_under_20_respects_date_range(): void
    {
        Bus::fake();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        [$organization, $employer] = $this->seedCalls();
        $employee = OrganizationUser::query()->where('organization_id', $organization->id)->first();
        $oldLow = $this->createAnalyzedCall($organization, $employee, 'low-old', 5, now()->subMonths(2));
        $recentLow = $this->createAnalyzedCall($organization, $employee, 'low-recent', 4, now()->subDay());

        $queued = app(ReanalyzeConversationsService::class)->queue(
            $organization,
            ReanalyzeScope::Under20,
            now()->subDays(3),
            now(),
        );

        $this->assertSame(2, $queued);
        $this->assertDatabaseHas('call_processing_jobs', ['call_id' => $recentLow->id]);
        $this->assertDatabaseMissing('call_processing_jobs', ['call_id' => $oldLow->id]);

        $this->actingAs($employer);

        Livewire::test(Index::class)
            ->set('reanalyzeRangeMode', 'range')
            ->set('reanalyzeFrom', now()->subDays(3)->toDateString())
            ->set('reanalyzeTo', now()->toDateString())
            ->call('reanalyzeConversations', 'under_20')
            ->assertHasNoErrors();
    }

    /**
     * @return array{0: Organization, 1: User, 2: Call, 3: Call}
     */
    private function seedCalls(): array
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Ali',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);

        $lowCall = $this->createAnalyzedCall($organization, $employee, 'low-1', 8, now()->subDay());
        $highCall = $this->createAnalyzedCall($organization, $employee, 'high-1', 88, now()->subDay());

        return [$organization, $employer, $lowCall, $highCall];
    }

    private function createAnalyzedCall(
        Organization $organization,
        OrganizationUser $employee,
        string $externalId,
        int $score,
        ?Carbon $analyzedAt = null,
    ): Call {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'custom',
            'external_call_id' => $externalId,
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '101',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 40,
            'started_at' => now()->subDay(),
        ]);

        CallRecording::query()->create([
            'call_id' => $call->id,
            'source_url' => 'https://example.test/'.$externalId.'.wav',
            'status' => 'completed',
            'storage_path' => 'recordings/'.$externalId.'.wav',
            'storage_disk' => 'local',
            'is_expired' => false,
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => $score,
            'is_evaluable' => $score > 0,
            'summary' => 'test',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'analyzed_at' => $analyzedAt ?? now(),
        ]);

        return $call;
    }
}
