<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Enums\UserRole;
use App\Livewire\Employer\Customers\Calls\Index as CallsIndex;
use App\Livewire\Employer\Customers\Companies\Index as CompaniesIndex;
use App\Livewire\Employer\Customers\Contacts\Index as ContactsIndex;
use App\Livewire\Employer\Customers\Index as CustomersHub;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerDirectoryFilterCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_cards_link_to_the_matching_filter(): void
    {
        $this->actingAsEmployer();

        $companyHref = route('employer.customers.companies.index');
        $contactHref = route('employer.customers.contacts.index');
        $callHref = route('employer.customers.calls.index');

        $hub = Livewire::test(CustomersHub::class)->html();
        $this->assertStringContainsString('href="'.$companyHref.'"', $hub);
        $this->assertStringContainsString('href="'.$contactHref.'"', $hub);
        $this->assertStringContainsString('href="'.$callHref.'"', $hub);
        $this->assertStringContainsString('aria-label="فیلتر شرکت"', $hub);
        $this->assertStringContainsString('aria-label="فیلتر شخص"', $hub);
        $this->assertStringContainsString('aria-label="فیلتر تماس"', $hub);
        $this->assertStringContainsString('>تماس‌ها<', $hub);
        $this->assertStringNotContainsString('crm-directory-stat__label">همه<', $hub);
        $this->assertStringNotContainsString('crm-directory-stat is-active', $hub);
        $this->assertStringNotContainsString('crm-directory-stat--', $hub);

        $companies = Livewire::test(CompaniesIndex::class)->html();
        $this->assertStringNotContainsString('crm-directory-stat is-active', $companies);
        $this->assertStringContainsString('aria-label="فیلتر شرکت"', $companies);

        $contacts = Livewire::test(ContactsIndex::class)->html();
        $this->assertStringNotContainsString('crm-directory-stat is-active', $contacts);
        $this->assertStringContainsString('aria-label="فیلتر شخص"', $contacts);

        $calls = Livewire::test(CallsIndex::class)->html();
        $this->assertStringContainsString('aria-label="فیلتر تماس"', $calls);
        $this->assertStringContainsString('>تماس‌ها<', $calls);
        $this->assertStringContainsString('همه جهت‌ها', $calls);
        $this->assertStringContainsString('جستجو در شخص، شرکت یا شماره تماس...', $calls);
    }

    public function test_call_filter_lists_only_this_organizations_customer_calls(): void
    {
        $organization = $this->actingAsEmployer();
        $company = CustomerCompany::query()->create([
            'organization_id' => $organization->id,
            'name' => 'شرکت همراه',
        ]);
        $customer = Customer::query()->create([
            'organization_id' => $organization->id,
            'customer_company_id' => $company->id,
            'normalized_phone' => '09121110001',
            'phone_number' => '09121110001',
            'name' => 'علی رضایی',
        ]);
        $this->createRecordedCall($organization, $customer, 'inbound', 'call-in');
        $this->createRecordedCall($organization, $customer, 'outbound', 'call-out');

        $foreign = Organization::factory()->create();
        $foreignCustomer = Customer::query()->create([
            'organization_id' => $foreign->id,
            'normalized_phone' => '09129990001',
            'phone_number' => '09129990001',
            'name' => 'مخاطب خارجی',
        ]);
        $this->createRecordedCall($foreign, $foreignCustomer, 'inbound', 'call-foreign');

        $component = Livewire::test(CallsIndex::class)
            ->assertSee('علی رضایی')
            ->assertSee('شرکت همراه')
            ->assertDontSee('مخاطب خارجی');

        $this->assertEqualsCanonicalizing(
            ['inbound', 'outbound'],
            $component->viewData('calls')->pluck('direction')->all(),
        );

        $component
            ->set('direction', 'outbound')
            ->assertSee('علی رضایی');
        $this->assertSame(['outbound'], $component->viewData('calls')->pluck('direction')->all());

        $component
            ->set('search', 'شرکت همراه')
            ->assertSee('علی رضایی')
            ->set('search', 'مخاطب خارجی')
            ->assertDontSee('علی رضایی');
        $this->assertCount(0, $component->viewData('calls'));
    }

    public function test_employee_can_open_the_call_filter(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'first_name' => 'سارا',
            'last_name' => 'کارشناس',
            'is_active' => true,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.customers.index'))
            ->assertOk()
            ->assertSee('فیلتر تماس')
            ->assertSee('تماس‌ها');
    }

    public function test_call_card_counts_linked_calls_and_opens_the_call_filter(): void
    {
        $organization = $this->actingAsEmployer();
        $customer = Customer::query()->create([
            'organization_id' => $organization->id,
            'normalized_phone' => '09121110001',
            'phone_number' => '09121110001',
            'name' => 'علی رضایی',
        ]);
        $this->createRecordedCall($organization, $customer, 'inbound', 'call-card');

        $html = Livewire::test(CustomersHub::class)->html();
        $this->assertMatchesRegularExpression(
            '/aria-label="فیلتر تماس"[^>]*>\s*<span class="crm-directory-stat__label">تماس<\/span>\s*<span class="crm-directory-stat__value">'.preg_quote(\App\Support\JalaliDate::persianDigits('1'), '/').'<\/span>/',
            $html,
        );

        Livewire::test(CallsIndex::class)
            ->assertSee('علی رضایی')
            ->assertSee('ورودی');
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $this->actingAs($employer);

        return $organization;
    }

    private function createRecordedCall(Organization $organization, Customer $customer, string $direction, string $externalId): Call
    {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => $externalId,
            'direction' => $direction,
            'caller_number' => $customer->phone_number,
            'receiver_number' => '02100000000',
            'customer_phone' => $customer->phone_number,
            'customer_name' => $customer->name,
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 90,
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

        return $call;
    }
}
