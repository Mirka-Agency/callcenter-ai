<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Enums\UserRole;
use App\Livewire\Employer\Organization\Profile;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\Performance\EmployeePerformanceAnalytics;
use App\Support\CompanyWorkCalendar;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployerCompanyHolidaysTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_employer_sees_thursday_and_friday_closed_until_they_choose_otherwise(): void
    {
        $this->actingAsEmployer();

        $html = Livewire::test(Profile::class)
            ->assertSee('پروفایل سازمان')
            ->assertSee('تعطیلات شرکت')
            ->assertSee('پنجشنبه')
            ->assertSee('جمعه')
            ->assertSet('holidayWeekdays', ['4', '5'])
            ->assertSee('تعطیل: پنجشنبه، جمعه')
            ->html();

        $this->assertMatchesRegularExpression('/value="4"[^>]*\bchecked\b|\bchecked\b[^>]*value="4"/', $html);
        $this->assertMatchesRegularExpression('/value="5"[^>]*\bchecked\b|\bchecked\b[^>]*value="5"/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="6"[^>]*\bchecked\b|\bchecked\b[^>]*value="6"/', $html);
    }

    public function test_employer_can_keep_thursday_as_a_workday(): void
    {
        $organization = $this->actingAsEmployer();

        Livewire::test(Profile::class)
            ->set('holidayWeekdays', [(string) Carbon::FRIDAY])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('تعطیل: جمعه')
            ->assertSee('روز کاری: شنبه، یکشنبه، دوشنبه، سه‌شنبه، چهارشنبه، پنجشنبه');

        $organization->refresh();

        $this->assertSame([Carbon::FRIDAY], $organization->holidayWeekdays());
    }

    public function test_thursday_calls_return_to_the_quality_trend_when_the_company_works_that_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-26 12:00:00', CompanyWorkCalendar::TIMEZONE));

        $organization = $this->actingAsEmployer();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'سارا',
            'last_name' => 'کریمی',
            'is_active' => true,
        ]);

        $thursday = Carbon::parse('2026-09-17 11:00:00', CompanyWorkCalendar::TIMEZONE);
        $friday = Carbon::parse('2026-09-18 11:00:00', CompanyWorkCalendar::TIMEZONE);
        $this->seedAnalysis($organization, $employee, $thursday);
        $this->seedAnalysis($organization, $employee, $friday);

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $closedPeriods = collect(app(EmployeePerformanceAnalytics::class)->teamDashboard($filter)['quality_trend'])
            ->pluck('period')
            ->all();

        $this->assertNotContains('2026-09-17', $closedPeriods);
        $this->assertNotContains('2026-09-18', $closedPeriods);

        $organization->update(['holiday_weekdays' => [Carbon::FRIDAY]]);

        $openThursdayPeriods = collect(app(EmployeePerformanceAnalytics::class)->teamDashboard($filter)['quality_trend'])
            ->pluck('period')
            ->all();

        $this->assertContains('2026-09-17', $openThursdayPeriods);
        $this->assertNotContains('2026-09-18', $openThursdayPeriods);
    }

    public function test_employee_cannot_open_company_holidays(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'first_name' => 'رضا',
            'last_name' => 'نوری',
            'is_active' => true,
        ]);

        $this->actingAs($employee)
            ->get(route('employer.organization.profile'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('employer.holidays.index'))
            ->assertForbidden();
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    private function seedAnalysis(Organization $organization, OrganizationUser $employee, Carbon $at): void
    {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('holiday-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 90,
            'started_at' => $at->copy()->utc(),
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 80,
            'is_evaluable' => true,
            'summary' => 'خلاصه',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'lead_quality_json' => ['score' => 60, 'level' => 'medium', 'reason' => 'test'],
            'analyzed_at' => $at->copy()->utc(),
        ]);
    }
}
