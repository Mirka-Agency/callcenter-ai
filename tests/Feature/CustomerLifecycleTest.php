<?php

namespace Tests\Feature;

use App\Application\Customer\Actions\ChangeCustomerLifecycle;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Customer\CustomerLifecycleStage;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employee\Customers\Show as EmployeeCustomerShow;
use App\Livewire\Employer\Customers\Contacts\Index as EmployerContactsIndex;
use App\Livewire\Employer\Customers\Show as EmployerCustomerShow;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerLifecycleEvent;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\CustomerIntelligenceService;
use App\Services\CustomerProfileUpdateService;
use App\Support\CustomerListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_people_start_as_leads_and_old_rows_stay_unknown(): void
    {
        $organization = Organization::factory()->create();
        $created = app(CustomerProfileUpdateService::class)->create($organization->id, [
            'name' => 'نو',
            'phone_number' => '09120001001',
        ]);
        $historical = Customer::query()->create([
            'organization_id' => $organization->id,
            'phone_number' => '09120001002',
            'normalized_phone' => '09120001002',
            'latest_lead_score' => 95,
            'latest_lead_level' => 'high',
        ]);

        $this->assertSame(CustomerLifecycleStage::NewLead, $created->lifecycle_stage);
        $this->assertNull($historical->lifecycle_stage);
        $this->assertSame(0, CustomerLifecycleEvent::query()->count());
        $this->assertFalse(Schema::hasColumn('customer_companies', 'lifecycle_stage'));
    }

    public function test_manual_transitions_are_audited_and_customer_stage_needs_confirmation(): void
    {
        $organization = Organization::factory()->create();
        $customer = app(CustomerProfileUpdateService::class)->create($organization->id, [
            'phone_number' => '09120001003',
        ]);
        $action = app(ChangeCustomerLifecycle::class);
        $actor = $organization->user_id;

        $action->change($customer, CustomerLifecycleStage::NewLead->value, $actor, 'manual', 'profile', CustomerLifecycleStage::NewLead->value, true);
        $this->assertSame(0, CustomerLifecycleEvent::query()->count());

        $contacted = $action->change($customer->fresh(), 'contacted', $actor, 'manual', 'profile', 'new_lead', true);
        $qualified = $action->change($contacted, 'qualified', $actor, 'manual', 'profile', 'contacted', true);
        $inactive = $action->change($qualified, 'inactive', $actor, 'manual', 'profile', 'qualified', true);
        $back = $action->change($inactive, 'qualified', $actor, 'manual', 'profile', 'inactive', true);

        $this->assertSame(CustomerLifecycleStage::Qualified, $back->lifecycle_stage);

        $rejected = false;

        try {
            $action->change($back, 'customer', $actor, 'manual', 'profile', 'qualified', true, false);
        } catch (ValidationException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $this->assertSame(CustomerLifecycleStage::Qualified, $back->fresh()->lifecycle_stage);

        $won = $action->change($back->fresh(), 'customer', $actor, 'manual', 'profile', 'qualified', true, true);
        $this->assertSame(CustomerLifecycleStage::Customer, $won->lifecycle_stage);
        $returned = $action->change($won, 'inactive', $actor, 'manual', 'profile', 'customer', true);
        $this->assertSame(CustomerLifecycleStage::Inactive, $returned->lifecycle_stage);

        $invalid = false;

        try {
            $action->change($returned, 'pipeline_won', $actor, 'manual', 'profile', 'inactive', true);
        } catch (ValidationException) {
            $invalid = true;
        }

        $this->assertTrue($invalid);

        $conflict = false;

        try {
            $action->change($returned->fresh(), 'new_lead', $actor, 'manual', 'profile', 'qualified', true);
        } catch (ValidationException) {
            $conflict = true;
        }

        $this->assertTrue($conflict);
        $this->assertSame(CustomerLifecycleStage::Inactive, $returned->fresh()->lifecycle_stage);

        $event = CustomerLifecycleEvent::query()->where('to_stage', 'customer')->first();
        $this->assertNotNull($event);
        $this->assertSame('qualified', $event->from_stage);
        $this->assertSame('manual', $event->source);
        $this->assertSame($actor, $event->actor_user_id);
        $this->assertNotNull($event->changed_at);
    }

    public function test_analysis_updates_lead_quality_without_moving_lifecycle(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $agent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'نگار',
            'last_name' => 'کریمی',
            'is_active' => true,
        ]);
        $historical = Customer::query()->create([
            'organization_id' => $organization->id,
            'phone_number' => '09120001004',
            'normalized_phone' => '09120001004',
        ]);

        $service = app(CustomerIntelligenceService::class);
        $matched = $service->syncFromAnalysis($this->analysis($organization, $agent, '09120001004', 96, 'high'));

        $this->assertSame($historical->id, $matched?->id);
        $this->assertNull($matched?->lifecycle_stage);
        $this->assertSame('high', $matched?->latest_lead_level);
        $this->assertSame(0, CustomerLifecycleEvent::query()->count());

        $created = $service->syncFromAnalysis($this->analysis($organization, $agent, '09120001005', 99, 'high'));
        $this->assertSame(CustomerLifecycleStage::NewLead, $created?->lifecycle_stage);
        $this->assertSame(99, $created?->latest_lead_score);

        app(ChangeCustomerLifecycle::class)->change($created, 'inactive', null, 'manual', 'profile', 'new_lead', true, false);
        $again = $service->syncFromAnalysis($this->analysis($organization, $agent, '09120001005', 10, 'low'));

        $this->assertSame(CustomerLifecycleStage::Inactive, $again?->lifecycle_stage);
        $this->assertSame('low', $again?->latest_lead_level);
        $this->assertSame(1, CustomerLifecycleEvent::query()->count());
    }

    public function test_stage_filter_combines_with_owner_inside_the_tenant(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $agent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create()->id,
            'first_name' => 'سارا',
            'last_name' => 'راد',
            'is_active' => true,
        ]);
        $mine = app(CustomerProfileUpdateService::class)->create($organization->id, ['phone_number' => '09120001006']);
        $mine->update(['owner_organization_user_id' => $agent->id]);
        $open = Customer::query()->create([
            'organization_id' => $organization->id,
            'phone_number' => '09120001007',
            'normalized_phone' => '09120001007',
            'lifecycle_stage' => CustomerLifecycleStage::Qualified,
        ]);
        $other = Organization::factory()->create();
        Customer::query()->create([
            'organization_id' => $other->id,
            'phone_number' => '09120001008',
            'normalized_phone' => '09120001008',
            'lifecycle_stage' => CustomerLifecycleStage::NewLead,
        ]);

        $ids = CustomerListQuery::contacts(
            $organization->id,
            owner: 'mine',
            viewerMembershipId: $agent->id,
            stage: 'new_lead',
        )->pluck('id')->all();
        $counts = CustomerListQuery::lifecycleCounts($organization->id, owner: 'mine', viewerMembershipId: $agent->id);

        $this->assertSame([$mine->id], $ids);
        $this->assertSame(1, $counts['all']);
        $this->assertSame(1, $counts['new_lead']);
        $this->assertSame(0, $counts['qualified']);
        $this->assertNotContains($open->id, $ids);

        $this->actingAs($employer);
        Livewire::test(EmployerContactsIndex::class)
            ->assertSeeHtml('wire:model.live="stageFilter"')
            ->assertSee('همه مراحل')
            ->assertSee('سرنخ جدید')
            ->assertSee('ارتباط برقرارشده')
            ->assertSee('واجد شرایط')
            ->assertSee('غیرفعال')
            ->assertSee('نامشخص')
            ->set('stageFilter', 'qualified')
            ->assertSee('09120001007')
            ->assertDontSee('09120001006')
            ->assertDontSee('09120001008');
    }

    public function test_manager_can_change_stage_and_employee_only_sees_it(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $agentUser = User::factory()->create(['role' => UserRole::Employee]);
        OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $agentUser->id,
            'first_name' => 'نگار',
            'last_name' => 'کریمی',
            'is_active' => true,
        ]);
        $customer = app(CustomerProfileUpdateService::class)->create($organization->id, [
            'phone_number' => '09120001009',
        ]);
        $customer->update(['latest_lead_level' => 'high', 'latest_lead_score' => 90]);
        $foreign = Customer::query()->create([
            'organization_id' => Organization::factory()->create()->id,
            'phone_number' => '09120001010',
            'normalized_phone' => '09120001010',
        ]);

        $this->actingAs($employer);

        Livewire::test(EmployerCustomerShow::class, ['customer' => $customer])
            ->set('lifecycleStage', 'contacted')
            ->call('saveLifecycle')
            ->assertHasNoErrors()
            ->assertSee('کیفیت لید: بالا');

        $this->assertSame(CustomerLifecycleStage::Contacted, $customer->fresh()->lifecycle_stage);

        Livewire::test(EmployerCustomerShow::class, ['customer' => $customer->fresh()])
            ->set('lifecycleStage', 'customer')
            ->call('saveLifecycle')
            ->assertHasErrors(['lifecycle']);

        Livewire::test(EmployerCustomerShow::class, ['customer' => $foreign])
            ->assertNotFound();

        $this->actingAs($agentUser);

        Livewire::test(EmployeeCustomerShow::class, ['customer' => $customer->fresh()])
            ->assertSee('ارتباط برقرارشده')
            ->assertDontSee('ثبت مرحله');
    }

    private function analysis(
        Organization $organization,
        OrganizationUser $agent,
        string $caller,
        int $score,
        string $level,
    ): ConversationAnalysis {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => 'life-'.$caller.'-'.$score,
            'direction' => 'inbound',
            'caller_number' => $caller,
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'started_at' => now()->subHour(),
        ]);
        CallRecording::query()->create([
            'call_id' => $call->id,
            'source_url' => 'https://pbx.example/'.$call->id.'.wav',
            'storage_disk' => 'local',
            'storage_path' => 'recordings/test-'.$call->id.'.wav',
            'status' => 'completed',
            'is_expired' => false,
        ]);

        return ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 80,
            'summary' => 'خلاصه',
            'sentiment' => AnalysisSentiment::Positive,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'lead_quality_json' => ['score' => $score, 'level' => $level],
            'customer_identity_json' => [],
            'analyzed_at' => now(),
        ]);
    }
}
