<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employee\Calls\Index as EmployeeCallsIndex;
use App\Livewire\Employee\Calls\Show as EmployeeCallsShow;
use App\Livewire\Employer\Intelligence\PerformanceShow;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PersonalCallsListTest extends TestCase
{
    use RefreshDatabase;

    public function test_expert_page_lists_personal_calls_apart_from_work_calls(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $agentUser = User::factory()->create(['role' => UserRole::Employee]);
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $agentUser->id,
            'first_name' => 'سارا',
            'last_name' => 'احمدی',
            'is_active' => true,
        ]);

        $business = $this->analysis($organization, $employee, [
            'summary' => 'مشتری قیمت اشتراک را پرسید.',
            'is_personal' => false,
            'score' => 82,
            'is_evaluable' => true,
        ]);
        $personal = $this->analysis($organization, $employee, [
            'summary' => 'گفتگو درباره قرار خانوادگی بود.',
            'is_personal' => true,
            'personal_reason' => 'هماهنگی قرار خانوادگی',
            'score' => 0,
            'is_evaluable' => false,
        ]);

        $this->actingAs($agentUser);

        $employeePage = Livewire::test(EmployeeCallsIndex::class);
        $employeePage->assertSee('تماس‌های شخصی');
        $employeePage->assertSee('هماهنگی قرار خانوادگی');
        $this->assertTrue($employeePage->viewData('analyses')->contains('id', $business->id));
        $this->assertFalse($employeePage->viewData('analyses')->contains('id', $personal->id));
        $this->assertTrue($employeePage->viewData('personalCalls')->contains('id', $personal->id));
        $this->assertSame(1, $employeePage->viewData('personalCallTotal'));

        Livewire::test(EmployeeCallsShow::class, ['analysis' => $personal])
            ->assertSee('این تماس شخصی است')
            ->assertSee('هماهنگی قرار خانوادگی')
            ->assertDontSee('نقاط قوت');

        $this->actingAs($employer);

        $managerPage = Livewire::test(PerformanceShow::class, ['employee' => $employee]);
        $managerPage->assertSee('تماس‌های شخصی');
        $managerPage->assertSee('هماهنگی قرار خانوادگی');
        $this->assertTrue($managerPage->viewData('personalCalls')->contains('id', $personal->id));
        $this->assertFalse(collect($managerPage->viewData('profile')['recent_calls'])->contains(
            fn (array $call) => $call['analysis_id'] === $personal->id,
        ));
    }

    /** @param  array<string, mixed>  $overrides */
    private function analysis(Organization $organization, OrganizationUser $employee, array $overrides): ConversationAnalysis
    {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'outbound',
            'caller_number' => '100',
            'receiver_number' => '09120000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 90,
            'started_at' => now(),
        ]);

        return ConversationAnalysis::query()->create(array_merge([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 0,
            'summary' => 'خلاصه',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'analyzed_at' => now(),
        ], $overrides));
    }
}
