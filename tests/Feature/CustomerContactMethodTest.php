<?php

namespace Tests\Feature;

use App\Application\Customer\Actions\AddCustomerContactMethod;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Customers\ContactMethods;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerContactMethod;
use App\Models\CustomerIdentityReview;
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

class CustomerContactMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_person_can_keep_several_phones_and_emails_with_one_primary(): void
    {
        $organization = Organization::factory()->create();
        $customer = app(CustomerProfileUpdateService::class)->create($organization->id, [
            'name' => 'علی',
            'phone_number' => '09120001111',
            'email' => 'ali@example.com',
        ]);

        $action = app(AddCustomerContactMethod::class);
        $action->handle($customer, '02188776655', 'دفتر');
        $action->handle($customer, 'other@example.com');

        $this->assertSame(4, $customer->contactMethods()->active()->count());
        $this->assertSame('09120001111', $customer->fresh()->normalized_phone);
        $this->assertSame(1, $customer->contactMethods()->where('is_primary', true)->whereIn('type', ['mobile', 'phone'])->count());

        $rejected = false;

        try {
            $action->handle($customer->fresh(), '09120001111');
        } catch (ValidationException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $this->assertSame(4, $customer->contactMethods()->active()->count());
    }

    public function test_duplicate_add_is_rejected_and_shared_verified_phone_is_not_auto_linked(): void
    {
        $organization = Organization::factory()->create();
        $profiles = app(CustomerProfileUpdateService::class);
        $first = $profiles->create($organization->id, ['phone_number' => '09121111111']);
        $second = $profiles->create($organization->id, ['phone_number' => '09122222222']);

        $rejected = false;

        try {
            app(AddCustomerContactMethod::class)->handle($first, '09121111111');
        } catch (ValidationException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        app(AddCustomerContactMethod::class)->handle($second, '09121111111', 'مشترک');

        $analysis = $this->inboundAnalysis($organization, '09121111111');
        $linked = app(CustomerIntelligenceService::class)->syncFromAnalysis($analysis);

        $this->assertNull($linked);
        $this->assertNull($analysis->call->fresh()->customer_id);
        $this->assertDatabaseHas('customer_identity_reviews', [
            'kind' => 'duplicate_phone',
            'status' => 'pending',
        ]);
    }

    public function test_spoken_number_becomes_a_review_instead_of_a_saved_method(): void
    {
        $organization = Organization::factory()->create();
        $analysis = $this->inboundAnalysis($organization, '09123334455', [
            'phone_number' => '09129998877',
            'confidence' => 0.99,
            'evidence' => 'مشتری گفت: شماره دیگرم ۰۹۱۲۹۹۹۸۸۷۷ است',
        ]);

        $customer = app(CustomerIntelligenceService::class)->syncFromAnalysis($analysis);

        $this->assertNotNull($customer);
        $this->assertSame(1, CustomerContactMethod::query()->where('customer_id', $customer->id)->count());
        $this->assertTrue(CustomerIdentityReview::query()->where('kind', 'phone_candidate')->exists());
        $this->assertSame('unverified', $customer->contactMethods()->first()->verification_status);
    }

    public function test_backfill_is_idempotent_and_dry_run_writes_nothing(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::query()->create([
            'organization_id' => $organization->id,
            'phone_number' => '09125556677',
            'normalized_phone' => '09125556677',
            'email' => 'keep@example.com',
        ]);

        $this->artisan('customers:backfill-contact-methods', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, CustomerContactMethod::query()->count());

        $this->artisan('customers:backfill-contact-methods')->assertSuccessful();
        $this->artisan('customers:backfill-contact-methods')->assertSuccessful();

        $this->assertSame(2, $customer->contactMethods()->count());
    }

    public function test_search_finds_a_secondary_number_and_personal_calls_stay_excluded(): void
    {
        $organization = Organization::factory()->create();
        $customer = app(CustomerProfileUpdateService::class)->create($organization->id, [
            'name' => 'جستجو',
            'phone_number' => '09120000001',
        ]);
        app(AddCustomerContactMethod::class)->handle($customer, '09120000099');

        $found = CustomerListQuery::contacts($organization->id, '09120000099')->pluck('id');
        $this->assertTrue($found->contains($customer->id));

        $analysis = $this->inboundAnalysis($organization, '09127776655');
        $analysis->update(['is_personal' => true]);
        $this->assertNull(app(CustomerIntelligenceService::class)->syncFromAnalysis($analysis->fresh()));
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_employer_can_add_a_method_from_the_profile_component(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $customer = app(CustomerProfileUpdateService::class)->create($organization->id, [
            'phone_number' => '09124445566',
        ]);

        $this->actingAs($employer);

        Livewire::test(ContactMethods::class, ['customerId' => $customer->id])
            ->set('value', 'other@example.com')
            ->call('add')
            ->assertHasNoErrors()
            ->assertSee('other@example.com');
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function inboundAnalysis(Organization $organization, string $caller, array $identity = []): ConversationAnalysis
    {
        $user = User::factory()->create();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'Sara',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => 'method-'.$caller,
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
            'customer_identity_json' => $identity,
            'analyzed_at' => now(),
        ]);
    }
}
