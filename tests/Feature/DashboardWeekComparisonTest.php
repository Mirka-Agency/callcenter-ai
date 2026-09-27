<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employer\Dashboard\Overview;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWeekComparisonTest extends TestCase
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

    public function test_dashboard_metric_cards_say_whether_they_rose_or_fell_versus_last_week(): void
    {
        $organization = $this->actingAsEmployer();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'علی',
            'last_name' => 'احمدی',
            'is_active' => true,
        ]);

        $this->seedAnalysis($organization, $employee, now()->subDays(10), 40, AnalysisSentiment::Negative, 30);
        $this->seedAnalysis($organization, $employee, now()->subDay(), 90, AnalysisSentiment::Positive, 80);

        $html = Livewire::test(Overview::class)
            ->assertSee('خلاصه امروز')
            ->assertSee('کیفیت تیم')
            ->assertSee('/۱۰۰')
            ->assertSee('مشتری ناراضی')
            ->assertSee('کارشناسان نیازمند پیشرفت')
            ->assertSee('فرصت فروش با احتمال بالا')
            ->assertSee('جزئیات')
            ->assertDontSee('تعداد مشتری ناراضی')
            ->assertDontSee('تعداد کارشناسان نیازمند پیشرفت')
            ->assertDontSee('تعداد فرصت فروش با احتمال بالا')
            ->assertSee('میانگین امتیاز تیم')
            ->assertSee('میانگین کیفیت لید')
            ->assertSee('رضایت مشتری')
            ->assertSee('بر اساس 2 تماس از 2 تماس تحلیل شده')
            ->assertSee('50 نسبت به هفته قبل')
            ->assertSee('80٪ نسبت به هفته قبل')
            ->assertDontSee('50٪ نسبت به هفته قبل')
            ->assertDontSee('⬆')
            ->assertDontSee('⬇')
            ->html();

        $summaryAt = mb_strpos($html, 'خلاصه امروز');
        $trendAt = mb_strpos($html, 'روند کیفیت تیم');
        $callsTodayAt = mb_strpos($html, 'تماس‌های امروز');
        $this->assertNotFalse($summaryAt);
        $this->assertNotFalse($trendAt);
        $this->assertNotFalse($callsTodayAt);
        $this->assertLessThan($summaryAt, $callsTodayAt);
        $this->assertLessThan($trendAt, $summaryAt);

        $this->assertSame(2, mb_substr_count($html, '50 نسبت به هفته قبل'));
        $this->assertSame(1, mb_substr_count($html, '80٪ نسبت به هفته قبل'));
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    private function seedAnalysis(
        Organization $organization,
        OrganizationUser $employee,
        Carbon $at,
        int $score,
        AnalysisSentiment $sentiment,
        int $leadScore,
    ): void {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('week-compare-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 180,
            'started_at' => $at,
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => $score,
            'is_evaluable' => true,
            'summary' => 'خلاصه تست',
            'sentiment' => $sentiment,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'lead_quality_json' => ['score' => $leadScore, 'level' => 'high', 'reason' => 'test'],
            'analyzed_at' => $at,
        ]);
    }
}
