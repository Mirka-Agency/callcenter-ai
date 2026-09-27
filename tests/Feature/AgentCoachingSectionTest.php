<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Contracts\ConversationAnalysisRepositoryInterface;
use App\Domain\Llm\DTOs\AnalysisResultData;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employer\Intelligence\PerformanceShow;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\Coaching\AgentCoachingAggregator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class AgentCoachingSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-16 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_profile_shows_empty_insufficient_and_full_coaching_states(): void
    {
        [$employer, $employee] = $this->employerAndAgent();
        $this->actingAs($employer);

        Livewire::test(PerformanceShow::class, ['employee' => $employee])
            ->assertSee('ارزیابی مهارت')
            ->assertSee('هنوز ارزیابی مهارتی برای این کارشناس ثبت نشده است.')
            ->assertSeeHtml('data-coaching-section')
            ->assertSeeHtml('wire:loading.delay.300ms.flex');

        $this->seedCoachingCalls($employee, 3, 54);
        Cache::flush();

        Livewire::test(PerformanceShow::class, ['employee' => $employee])
            ->assertSee('اطلاعات کافی برای ارزیابی مهارت این کارشناس وجود ندارد.')
            ->assertSee('3 از حداقل 5 تماس مورد نیاز تحلیل شده است.')
            ->assertDontSee('پیش از معرفی طرح، درباره بودجه بپرسد.');

        $this->seedCoachingCalls($employee, 2, 92, skill: 'call_opening', feedback: 'معرفی خودش روشن بود.', recommendation: 'همین شروع را در تماس‌های بعدی حفظ کند.');
        Cache::flush();

        Livewire::test(PerformanceShow::class, ['employee' => $employee])
            ->assertSee('امتیاز مهارت')
            ->assertSee('کشف نیاز')
            ->assertSee('نیاز به بهبود')
            ->assertSee('شروع تماس')
            ->assertSee('قوت')
            ->assertSee('پرسش بودجه پرسیده نشد.')
            ->assertSee('پیش از معرفی طرح، درباره بودجه بپرسد.')
            ->assertSee('03:14 تا 03:42')
            ->assertSee('مشاهده تماس')
            ->assertSeeHtml('agent-coaching-trend')
            ->assertSeeHtml('/app/intelligence/');
    }

    public function test_error_payload_renders_without_skill_conclusions(): void
    {
        $html = view('livewire.employer.intelligence.partials.agent-coaching', [
            'agentCoaching' => app(AgentCoachingAggregator::class)->errorPayload(),
        ])->render();

        $this->assertStringContainsString('محاسبه ارزیابی مهارت این کارشناس ممکن نشد.', $html);
        $this->assertStringNotContainsString('امتیاز مهارت', $html);
    }

    public function test_employer_cannot_open_another_organizations_agent(): void
    {
        [$employer] = $this->employerAndAgent();
        [, $otherEmployee] = $this->employerAndAgent();

        $this->actingAs($employer)
            ->get(route('employer.intelligence.performance.show', $otherEmployee))
            ->assertNotFound();
    }

    public function test_repository_persists_coaching_and_keeps_older_rows_without_it(): void
    {
        $organization = Organization::factory()->create();
        $repository = app(ConversationAnalysisRepositoryInterface::class);

        $without = $repository->store($this->analysisResult($organization->id, null));
        $with = $repository->store($this->analysisResult($organization->id, [
            'skills' => [[
                'skill_key' => 'empathy',
                'score' => 91,
                'status' => 'strength',
                'confidence' => 0.6,
                'evidence' => [],
                'feedback' => 'لحن آرام بود.',
                'recommendation' => '',
            ]],
            'strengths' => [],
            'weaknesses' => [],
            'coaching_recommendations' => [],
        ]));

        $this->assertNull(ConversationAnalysis::query()->find($without)->coaching_analysis_json);
        $this->assertSame('empathy', ConversationAnalysis::query()->find($with)->coaching_analysis_json['skills'][0]['skill_key']);
        $this->assertSame(81, ConversationAnalysis::query()->find($without)->score);
    }

    /** @return array{0: User, 1: OrganizationUser} */
    private function employerAndAgent(): array
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create([
            'user_id' => $employer->id,
            'holiday_weekdays' => [],
        ]);
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'سارا',
            'last_name' => 'کریمی',
            'position' => 'کارشناس فروش',
            'is_active' => true,
        ]);

        return [$employer, $employee];
    }

    private function seedCoachingCalls(
        OrganizationUser $employee,
        int $count,
        float $score,
        string $skill = 'need_discovery',
        string $feedback = 'پرسش بودجه پرسیده نشد.',
        string $recommendation = 'پیش از معرفی طرح، درباره بودجه بپرسد.',
    ): void {
        for ($index = 0; $index < $count; $index++) {
            $at = Carbon::parse('2026-09-07 09:00:00')->addDays($index);

            $call = Call::query()->create([
                'organization_id' => $employee->organization_id,
                'organization_user_id' => $employee->id,
                'source' => ConversationSource::Voip,
                'provider_code' => 'novatel',
                'external_call_id' => uniqid('coach-', true),
                'direction' => 'inbound',
                'caller_number' => '09120000000',
                'receiver_number' => '02100000000',
                'status' => 'completed',
                'processing_status' => 'analyzed',
                'duration_seconds' => 180,
                'started_at' => $at,
            ]);

            ConversationAnalysis::query()->create([
                'organization_id' => $employee->organization_id,
                'organization_user_id' => $employee->id,
                'call_id' => $call->id,
                'source' => ConversationSource::Voip,
                'llm_provider' => 'openai',
                'model_name' => 'gpt-4o-mini',
                'score' => 80,
                'is_evaluable' => true,
                'summary' => 'خلاصه تماس ارزیابی مهارت',
                'sentiment' => AnalysisSentiment::Neutral,
                'strengths_json' => [],
                'weaknesses_json' => [],
                'next_actions_json' => [],
                'coaching_analysis_json' => [
                    'skills' => [[
                        'skill_key' => $skill,
                        'score' => $score,
                        'status' => $score >= 85 ? 'strength' : 'needs_improvement',
                        'confidence' => 0.8,
                        'feedback' => $feedback,
                        'recommendation' => $recommendation,
                        'evidence' => [[
                            'description' => 'ارائه پیش از کشف نیاز شروع شد',
                            'quote_or_summary' => 'طرح را همان ابتدا توضیح داد',
                            'start_time' => 194,
                            'end_time' => 222,
                        ]],
                    ]],
                ],
                'analyzed_at' => $at,
            ]);
        }
    }

    /** @param  array<string, mixed>|null  $coaching */
    private function analysisResult(int $organizationId, ?array $coaching): AnalysisResultData
    {
        return AnalysisResultData::fromProviderResponse(
            response: [
                'score' => 81,
                'summary' => 'خلاصه ذخیره‌شده',
                'sentiment' => 'positive',
                'strengths' => ['شروع خوب'],
                'weaknesses' => [],
                'coaching_analysis' => $coaching,
            ],
            organizationId: $organizationId,
            organizationUserId: null,
            voipCallLogId: null,
            organizationLlmConnectionId: null,
            llmProvider: 'openai',
            modelName: 'gpt-4o-mini',
            inputTokens: 10,
            outputTokens: 20,
            cost: 0.1,
            processingDurationMs: 30,
        );
    }
}
