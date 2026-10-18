<?php

namespace Tests\Feature;

use App\Application\Customer\Actions\AssignCustomerOwner;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Crm\Settings as CrmSettings;
use App\Livewire\Employee\Customers\Contacts\Index as EmployeeContactsIndex;
use App\Livewire\Employee\Customers\Show as EmployeeCustomerShow;
use App\Livewire\Employer\Customers\Contacts\Index as EmployerContactsIndex;
use App\Livewire\Employer\Customers\Show as EmployerCustomerShow;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerOwnerEvent;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\CustomerIntelligenceService;
use App\Services\CustomerProfileUpdateService;
use App\Support\CustomerListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_assign_reassign_and_clear_without_duplicate_events(): void
    {
        [$organization, $first, $second] = $this->organizationWithTwoAgents();
        $customer = $this->customer($organization, '09120000001');
        $action = app(AssignCustomerOwner::class);

        $assigned = $action->assign($customer, $first->id, $organization->user_id, 'manual', 'profile', null, true);
        $this->assertSame($first->id, (int) $assigned->owner_organization_user_id);

        $action->assign($assigned, $first->id, $organization->user_id, 'manual', 'profile', $first->id, true);
        $this->assertSame(1, CustomerOwnerEvent::query()->count());

        $reassigned = $action->assign($assigned->fresh(), $second->id, $organization->user_id, 'manual', 'profile', $first->id, true);
        $this->assertSame($second->id, (int) $reassigned->owner_organization_user_id);

        $cleared = $action->assign($reassigned, null, $organization->user_id, 'manual', 'profile', $second->id, true);
        $this->assertNull($cleared->owner_organization_user_id);

        $events = CustomerOwnerEvent::query()->orderBy('id')->get();
        $this->assertCount(3, $events);
        $this->assertNull($events[0]->previous_owner_organization_user_id);
        $this->assertSame($first->id, (int) $events[0]->new_owner_organization_user_id);
        $this->assertSame($first->id, (int) $events[1]->previous_owner_organization_user_id);
        $this->assertSame($second->id, (int) $events[1]->new_owner_organization_user_id);
        $this->assertNull($events[2]->new_owner_organization_user_id);
    }

    public function test_stale_owner_and_foreign_or_inactive_member_are_rejected(): void
    {
        [$organization, $first, $second] = $this->organizationWithTwoAgents();
        $other = Organization::factory()->create();
        $outsider = $this->agent($other, 'Other', 'Org');
        $customer = $this->customer($organization, '09120000002');
        $action = app(AssignCustomerOwner::class);

        $action->assign($customer, $first->id, null, 'manual', 'profile', null, true);

        $conflict = false;

        try {
            $action->assign($customer->fresh(), $second->id, null, 'manual', 'profile', null, true);
        } catch (ValidationException) {
            $conflict = true;
        }

        $this->assertTrue($conflict);
        $this->assertSame($first->id, (int) $customer->fresh()->owner_organization_user_id);

        $first->update(['is_active' => false]);
        $customer->refresh();
        $this->assertNull($customer->owner_organization_user_id);

        $rejected = false;

        try {
            $action->assign($customer->fresh(), $outsider->id, null, 'manual', 'profile', null, true);
        } catch (ValidationException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $this->assertNull($customer->fresh()->owner_organization_user_id);
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_removing_membership_clears_owner_and_keeps_the_customer(): void
    {
        [$organization, $first] = $this->organizationWithTwoAgents();
        $customer = $this->customer($organization, '09120000003');
        app(AssignCustomerOwner::class)->assign($customer, $first->id, null, 'manual', 'profile');

        $first->delete();

        $customer->refresh();
        $this->assertNull($customer->owner_organization_user_id);
        $this->assertDatabaseHas('customer_owner_events', [
            'customer_id' => $customer->id,
            'source' => 'membership_ended',
            'reason' => 'عضویت حذف شد',
        ]);
        $this->assertNotNull(Customer::query()->find($customer->id));
    }

    public function test_analysis_does_not_overwrite_an_owner_and_auto_assign_is_opt_in(): void
    {
        [$organization, $agent, $otherAgent] = $this->organizationWithTwoAgents();
        $existing = $this->customer($organization, '09120000004', $agent->id);
        $analysis = $this->inboundAnalysis($organization, $otherAgent, '09120000004');

        $synced = app(CustomerIntelligenceService::class)->syncFromAnalysis($analysis);

        $this->assertSame($existing->id, $synced?->id);
        $this->assertSame($agent->id, (int) $synced?->owner_organization_user_id);
        $this->assertSame(0, CustomerOwnerEvent::query()->where('source', 'system')->count());

        $organization->update(['assign_customer_owner_from_call' => false]);
        $created = app(CustomerIntelligenceService::class)->syncFromAnalysis(
            $this->inboundAnalysis($organization, $agent, '09120000005'),
        );
        $this->assertNotNull($created);
        $this->assertNull($created->owner_organization_user_id);

        $organization->update(['assign_customer_owner_from_call' => true]);
        $inactive = $this->agent($organization, 'Off', 'Duty', false);
        $unknown = app(CustomerIntelligenceService::class)->syncFromAnalysis(
            $this->inboundAnalysis($organization, $inactive, '09120000006'),
        );
        $this->assertNull($unknown?->owner_organization_user_id);

        $fresh = app(CustomerIntelligenceService::class)->syncFromAnalysis(
            $this->inboundAnalysis($organization, $agent, '09120000007'),
        );
        $this->assertSame($agent->id, (int) $fresh?->owner_organization_user_id);

        $again = app(CustomerIntelligenceService::class)->syncFromAnalysis(
            $this->inboundAnalysis($organization, $otherAgent, '09120000007'),
        );
        $this->assertSame($agent->id, (int) $again?->owner_organization_user_id);
        $this->assertSame(1, CustomerOwnerEvent::query()->where('source', 'system')->count());
    }

    public function test_owner_filters_stay_inside_the_organization(): void
    {
        [$organization, $agent] = $this->organizationWithTwoAgents();
        $mine = $this->customer($organization, '09120000008', $agent->id);
        $open = $this->customer($organization, '09120000009');
        $otherOrg = Organization::factory()->create();
        $otherAgent = $this->agent($otherOrg, 'Foreign', 'Agent');
        $foreign = $this->customer($otherOrg, '09120000010', $otherAgent->id);

        $unassigned = CustomerListQuery::contacts($organization->id, owner: 'unassigned')->pluck('id')->all();
        $owned = CustomerListQuery::contacts($organization->id, owner: 'mine', viewerMembershipId: $agent->id)->pluck('id')->all();
        $specificForeign = CustomerListQuery::contacts($organization->id, owner: 'specific', specificOwnerId: $otherAgent->id)->pluck('id')->all();

        $this->assertSame([$open->id], $unassigned);
        $this->assertSame([$mine->id], $owned);
        $this->assertSame([], $specificForeign);
        $this->assertNotContains($foreign->id, CustomerListQuery::contacts($organization->id)->pluck('id')->all());
    }

    public function test_manager_screen_assigns_owner_and_employee_screen_only_shows_it(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $agentUser = User::factory()->create(['role' => UserRole::Employee]);
        $agent = $this->agent($organization, 'نگار', 'کریمی', true, $agentUser);
        $customer = $this->customer($organization, '09120000011');
        $open = $this->customer($organization, '09120000012');

        $this->actingAs($employer);

        Livewire::test(EmployerContactsIndex::class)
            ->assertDontSee('او مسئول پیش‌فرض شود');

        Livewire::test(CrmSettings::class)
            ->assertSee('مسئول پیش‌فرض شخص')
            ->set('assignFromCall', true)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertTrue($organization->fresh()->assign_customer_owner_from_call);

        Livewire::test(EmployerContactsIndex::class)
            ->set('ownerFilter', 'unassigned')
            ->assertSee('09120000011')
            ->assertSee('09120000012');

        Livewire::test(EmployerCustomerShow::class, ['customer' => $customer])
            ->set('ownerOrganizationUserId', (string) $agent->id)
            ->call('saveOwner')
            ->assertHasNoErrors();

        $this->assertSame($agent->id, (int) $customer->fresh()->owner_organization_user_id);

        Livewire::test(EmployerContactsIndex::class)
            ->set('ownerFilter', 'unassigned')
            ->assertDontSee('09120000011')
            ->assertSee('09120000012');

        $this->actingAs($agentUser);

        Livewire::test(EmployeeCustomerShow::class, ['customer' => $customer->fresh()])
            ->assertSee('نگار کریمی')
            ->assertDontSee('ثبت مسئول');

        Livewire::test(EmployeeContactsIndex::class)
            ->set('ownerFilter', 'mine')
            ->assertSee('09120000011')
            ->assertDontSee('09120000012');

        $this->assertNull($open->fresh()->owner_organization_user_id);
    }

    /**
     * @return array{0: Organization, 1: OrganizationUser, 2: OrganizationUser}
     */
    private function organizationWithTwoAgents(): array
    {
        $owner = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $owner->id]);

        return [
            $organization,
            $this->agent($organization, 'نگار', 'کریمی'),
            $this->agent($organization, 'سارا', 'راد'),
        ];
    }

    private function agent(
        Organization $organization,
        string $firstName,
        string $lastName,
        bool $active = true,
        ?User $user = null,
    ): OrganizationUser {
        return OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => ($user ?? User::factory()->create())->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_active' => $active,
        ]);
    }

    private function customer(Organization $organization, string $phone, ?int $ownerId = null): Customer
    {
        $customer = app(CustomerProfileUpdateService::class)->create($organization->id, [
            'phone_number' => $phone,
            'name' => 'شخص '.$phone,
        ]);

        if ($ownerId !== null) {
            $customer->update(['owner_organization_user_id' => $ownerId]);
        }

        return $customer->fresh() ?? $customer;
    }

    private function inboundAnalysis(Organization $organization, OrganizationUser $employee, string $caller): ConversationAnalysis
    {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => 'owner-'.$caller.'-'.$employee->id,
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
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 70,
            'summary' => 'خلاصه',
            'sentiment' => AnalysisSentiment::Positive,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'customer_identity_json' => [],
            'analyzed_at' => now(),
        ]);
    }
}
