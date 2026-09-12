<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Enums\UserRole;
use App\Livewire\Employer\Dashboard\Overview;
use App\Livewire\Employer\Intelligence\Performance;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\Performance\EmployeePerformanceAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamWeaknessCallDrilldownTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_drilldown_shows_only_matching_calls(): void
    {
        $organization = $this->actingAsEmployer();
        $this->seedCall($organization, 'weak-close', 'جمع‌بندی ضعیف انتهای تماس', 'مشتری الف', now()->subDay());
        $this->seedCall($organization, 'weak-confirm', 'عدم تأیید نهایی نیاز مشتری', 'مشتری ب', now()->subDay());
        $this->seedCall($organization, 'weak-upsell', 'فرصت فروش مکمل از دست رفت', 'مشتری ج', now()->subDay());

        Livewire::test(Overview::class)
            ->assertSee('ضعف‌های پرتکرار تیم')
            ->assertSee('جمع‌بندی ضعیف انتهای تماس (1)')
            ->call('selectTeamWeakness', 'جمع‌بندی ضعیف انتهای تماس')
            ->assertSet('selectedTeamWeakness', 'جمع‌بندی ضعیف انتهای تماس')
            ->assertSee('مشتری الف')
            ->assertDontSee('مشتری ب')
            ->assertDontSee('مشتری ج')
            ->call('selectTeamWeakness', 'عدم تأیید نهایی نیاز مشتری')
            ->assertSee('مشتری ب')
            ->assertDontSee('مشتری الف')
            ->call('selectTeamWeakness', 'فرصت فروش مکمل از دست رفت')
            ->assertSee('مشتری ج')
            ->assertDontSee('مشتری ب')
            ->call('clearTeamWeakness')
            ->assertSet('selectedTeamWeakness', null)
            ->assertDontSee('مشتری ج');
    }

    public function test_performance_drilldown_respects_date_filter_and_stays_on_page(): void
    {
        $organization = $this->actingAsEmployer();
        $this->seedCall($organization, 'weak-recent', 'جمع‌بندی ضعیف انتهای تماس', 'تماس جدید', now()->subDay());
        $this->seedCall($organization, 'weak-old', 'جمع‌بندی ضعیف انتهای تماس', 'تماس قدیمی', now()->subDays(40));

        Livewire::test(Performance::class)
            ->assertSee('ضعف‌های پرتکرار تیم')
            ->call('selectTeamWeakness', 'جمع‌بندی ضعیف انتهای تماس')
            ->assertSee('تماس جدید')
            ->assertDontSee('تماس قدیمی')
            ->call('applyCustomDateRange', now()->subDays(50)->toDateString(), now()->subDays(30)->toDateString())
            ->assertSet('selectedTeamWeakness', 'جمع‌بندی ضعیف انتهای تماس')
            ->assertSee('تماس قدیمی')
            ->assertDontSee('تماس جدید');
    }

    public function test_drilldown_rejects_foreign_organization_and_unknown_weakness(): void
    {
        $organization = $this->actingAsEmployer();
        $this->seedCall($organization, 'own-weak', 'جمع‌بندی ضعیف انتهای تماس', 'تماس خودی', now()->subDay());

        $other = Organization::factory()->create();
        $this->seedCall($other, 'other-weak', 'جمع‌بندی ضعیف انتهای تماس', 'تماس سازمان دیگر', now()->subDay());

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $calls = app(EmployeePerformanceAnalytics::class)->teamWeaknessCalls($filter, 'جمع‌بندی ضعیف انتهای تماس');

        $this->assertCount(1, $calls);
        $this->assertSame('تماس خودی', $calls[0]['customer']);
        $this->assertSame([], app(EmployeePerformanceAnalytics::class)->teamWeaknessCalls($filter, 'ضعف ساختگی'));

        Livewire::test(Overview::class)
            ->call('selectTeamWeakness', 'ضعف ساختگی')
            ->assertSet('selectedTeamWeakness', null)
            ->assertDontSee('تماس سازمان دیگر')
            ->assertDontSee('تماسی با این ضعف پیدا نشد.');
    }

    public function test_empty_state_copy_is_used_when_no_calls_are_returned(): void
    {
        $html = view('livewire.employer.partials.team-weaknesses-card', [
            'teamWeaknesses' => [['item' => 'ضعف آزمایشی', 'count' => 1]],
            'selectedTeamWeakness' => 'ضعف آزمایشی',
            'teamWeaknessCalls' => [],
        ])->render();

        $this->assertStringContainsString('تماسی با این ضعف پیدا نشد.', $html);
        $this->assertStringContainsString('ضعف آزمایشی', $html);
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    private function seedCall(
        Organization $organization,
        string $externalId,
        string $weakness,
        string $customerName,
        $analyzedAt,
    ): void {
        $employee = OrganizationUser::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'first_name' => 'علی',
                'last_name' => 'احمدی',
            ],
            [
                'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
                'is_active' => true,
            ],
        );

        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => $externalId,
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'customer_name' => $customerName,
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 120,
            'started_at' => $analyzedAt,
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 70,
            'is_evaluable' => true,
            'summary' => 'خلاصه '.$customerName,
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [$weakness],
            'next_actions_json' => [],
            'lead_quality_json' => ['score' => 50, 'level' => 'medium', 'reason' => 'test'],
            'analyzed_at' => $analyzedAt,
        ]);
    }
}
