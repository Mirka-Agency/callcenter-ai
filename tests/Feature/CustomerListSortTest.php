<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employer\Customers\Companies\Index as CompaniesIndex;
use App\Livewire\Employer\Customers\Contacts\Index as ContactsIndex;
use App\Livewire\Employer\Customers\Index as CustomersHub;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerListSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizations_default_to_last_contact_and_can_sort(): void
    {
        $organization = $this->actingAsEmployer();

        $older = $this->createCompany($organization, 'سازمان قدیمی‌تر', now()->subDays(8));
        $recent = $this->createCompany($organization, 'سازمان تازه‌تماس', now()->subDay());
        CustomerCompany::query()->whereKey($older->id)->update(['created_at' => now()->subDays(2)]);
        CustomerCompany::query()->whereKey($recent->id)->update(['created_at' => now()->subDays(20)]);

        $happyContact = $this->createContact($organization, 'مخاطب راضی', '09121110001', now()->subDay(), $older);
        $this->attachSentiment($organization, $happyContact, AnalysisSentiment::Positive);
        $unhappyContact = $this->createContact($organization, 'مخاطب ناراضی', '09121110002', now()->subDay(), $recent);
        $this->attachSentiment($organization, $unhappyContact, AnalysisSentiment::Negative);

        $component = Livewire::test(CompaniesIndex::class)
            ->assertSet('sort', 'last_contact')
            ->assertSee('مرتب‌سازی')
            ->assertSee('آخرین تماس')
            ->assertSee('راضی‌ترین مشتری')
            ->assertSee('ناراضی‌ترین مشتری')
            ->assertSee('جدیدترین مشتری')
            ->assertSee('قدیمی‌ترین مشتری');

        $this->assertSame(['سازمان تازه‌تماس', 'سازمان قدیمی‌تر'], $this->listedNames($component, 'companies'));

        $this->assertSame(
            ['سازمان قدیمی‌تر', 'سازمان تازه‌تماس'],
            $this->listedNames($component->set('sort', 'newest'), 'companies'),
        );
        $this->assertSame(
            ['سازمان تازه‌تماس', 'سازمان قدیمی‌تر'],
            $this->listedNames($component->set('sort', 'oldest'), 'companies'),
        );
        $this->assertSame(
            ['سازمان قدیمی‌تر', 'سازمان تازه‌تماس'],
            $this->listedNames($component->set('sort', 'satisfaction'), 'companies'),
        );
        $this->assertSame(
            ['سازمان تازه‌تماس', 'سازمان قدیمی‌تر'],
            $this->listedNames($component->set('sort', 'dissatisfaction'), 'companies'),
        );
    }

    public function test_contacts_default_to_last_contact_and_can_sort(): void
    {
        $organization = $this->actingAsEmployer();

        $older = $this->createContact($organization, 'مخاطب قدیمی‌تر', '09121120001', now()->subDays(8));
        $recent = $this->createContact($organization, 'مخاطب تازه‌تماس', '09121120002', now()->subDay());
        Customer::query()->whereKey($older->id)->update(['created_at' => now()->subDays(2)]);
        Customer::query()->whereKey($recent->id)->update(['created_at' => now()->subDays(20)]);

        $this->attachSentiment($organization, $older, AnalysisSentiment::Positive);
        $this->attachSentiment($organization, $recent, AnalysisSentiment::Negative);

        $component = Livewire::test(ContactsIndex::class)
            ->assertSet('sort', 'last_contact')
            ->assertSee('مرتب‌سازی')
            ->assertSee('آخرین تماس')
            ->assertSee('راضی‌ترین مشتری')
            ->assertSee('ناراضی‌ترین مشتری')
            ->assertSee('جدیدترین مشتری')
            ->assertSee('قدیمی‌ترین مشتری');

        $this->assertSame(['مخاطب تازه‌تماس', 'مخاطب قدیمی‌تر'], $this->listedNames($component, 'contacts'));

        $this->assertSame(
            ['مخاطب قدیمی‌تر', 'مخاطب تازه‌تماس'],
            $this->listedNames($component->set('sort', 'newest'), 'contacts'),
        );
        $this->assertSame(
            ['مخاطب تازه‌تماس', 'مخاطب قدیمی‌تر'],
            $this->listedNames($component->set('sort', 'oldest'), 'contacts'),
        );
        $this->assertSame(
            ['مخاطب قدیمی‌تر', 'مخاطب تازه‌تماس'],
            $this->listedNames($component->set('sort', 'satisfaction'), 'contacts'),
        );
        $this->assertSame(
            ['مخاطب تازه‌تماس', 'مخاطب قدیمی‌تر'],
            $this->listedNames($component->set('sort', 'dissatisfaction'), 'contacts'),
        );
    }

    public function test_search_and_sort_work_together_on_organizations(): void
    {
        $organization = $this->actingAsEmployer();

        $matchingHappy = $this->createCompany($organization, 'آلفا راضی', now()->subDays(4));
        $matchingUnhappy = $this->createCompany($organization, 'آلفا ناراضی', now()->subDay());
        $this->createCompany($organization, 'بتا جدا', now());

        $this->attachSentiment(
            $organization,
            $this->createContact($organization, 'الف', '09121130001', now(), $matchingHappy),
            AnalysisSentiment::Positive,
        );
        $this->attachSentiment(
            $organization,
            $this->createContact($organization, 'ب', '09121130002', now(), $matchingUnhappy),
            AnalysisSentiment::Negative,
        );

        $component = Livewire::test(CompaniesIndex::class)
            ->set('search', 'آلفا')
            ->set('sort', 'satisfaction')
            ->assertSee('آلفا راضی')
            ->assertSee('آلفا ناراضی')
            ->assertDontSee('بتا جدا');

        $this->assertSame(['آلفا راضی', 'آلفا ناراضی'], $this->listedNames($component, 'companies'));
    }

    public function test_search_and_sort_work_together_on_contacts(): void
    {
        $organization = $this->actingAsEmployer();

        $matchingHappy = $this->createContact($organization, 'سارا راضی', '09121140001', now()->subDays(3));
        $matchingUnhappy = $this->createContact($organization, 'سارا ناراضی', '09121140002', now()->subDay());
        $this->createContact($organization, 'علی جدا', '09121140003', now());

        $this->attachSentiment($organization, $matchingHappy, AnalysisSentiment::Positive);
        $this->attachSentiment($organization, $matchingUnhappy, AnalysisSentiment::Negative);

        $component = Livewire::test(ContactsIndex::class)
            ->set('search', 'سارا')
            ->set('sort', 'satisfaction')
            ->assertSee('سارا راضی')
            ->assertSee('سارا ناراضی')
            ->assertDontSee('علی جدا');

        $this->assertSame(['سارا راضی', 'سارا ناراضی'], $this->listedNames($component, 'contacts'));
    }

    public function test_changing_sort_resets_organization_pagination(): void
    {
        $organization = $this->actingAsEmployer();

        for ($i = 1; $i <= 25; $i++) {
            $this->createCompany($organization, sprintf('سازمان صفحه %02d', $i), now()->subDays($i));
        }

        Livewire::test(CompaniesIndex::class)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('sort', 'newest')
            ->assertSet('paginators.page', 1)
            ->assertSet('sort', 'newest');
    }

    public function test_changing_sort_resets_contact_pagination(): void
    {
        $organization = $this->actingAsEmployer();

        for ($i = 1; $i <= 25; $i++) {
            $this->createContact($organization, sprintf('مخاطب صفحه %02d', $i), sprintf('0912115%04d', $i), now()->subDays($i));
        }

        Livewire::test(ContactsIndex::class)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('sort', 'oldest')
            ->assertSet('paginators.page', 1)
            ->assertSet('sort', 'oldest');
    }

    public function test_invalid_sort_falls_back_and_url_hydrates_known_value(): void
    {
        $this->actingAsEmployer();

        Livewire::test(CompaniesIndex::class)
            ->set('sort', 'drop-table')
            ->assertSet('sort', 'last_contact');

        Livewire::withQueryParams(['sort' => 'newest'])
            ->test(ContactsIndex::class)
            ->assertSet('sort', 'newest');

        Livewire::withQueryParams(['sort' => 'dissatisfaction'])
            ->test(CompaniesIndex::class)
            ->assertSet('sort', 'dissatisfaction');
    }

    public function test_customers_hub_renders_directory_table_without_stat_cards(): void
    {
        $organization = $this->actingAsEmployer();
        $this->createCompany($organization, 'سازمان تست', now());
        $this->createContact($organization, 'مخاطب بدون شرکت', '09121170001', now());

        $component = Livewire::test(CustomersHub::class);
        $html = $component->html();

        $this->assertStringContainsString('data-tour="customers-directory"', $html);
        $this->assertStringContainsString('data-tour="customers-directory-tabs"', $html);
        $this->assertStringContainsString('data-tour="customers-contacts-table"', $html);
        $this->assertStringNotContainsString('data-tour="customers-hub-stats"', $html);
        $this->assertStringNotContainsString('data-tour="customers-hub-cards"', $html);
        $this->assertStringNotContainsString('data-tour="customers-hub-hero"', $html);
        $this->assertStringNotContainsString('نمای کلی', $html);
        $component
            ->assertSee('اشخاص و شرکت‌ها')
            ->assertSee('اشخاص')
            ->assertSee('شرکت‌ها')
            ->assertSee('تماس‌ها')
            ->assertSee('مخاطب بدون شرکت')
            ->assertSee('افزودن')
            ->assertDontSee('شرکت جدید')
            ->assertDontSee('شخص جدید')
            ->assertDontSee('نیازمند بررسی');
        $this->assertStringContainsString('data-row-href', $html);
    }

    public function test_employer_cannot_see_other_organization_customers_when_sorting(): void
    {
        $organization = $this->actingAsEmployer();
        $foreign = Organization::factory()->create();

        $this->createCompany($organization, 'سازمان خودی', now()->subDay());
        $foreignCompany = $this->createCompany($foreign, 'سازمان خارجی محرمانه', now());
        $foreignContact = $this->createContact($foreign, 'مخاطب خارجی محرمانه', '09121160001', now(), $foreignCompany);
        $this->attachSentiment($foreign, $foreignContact, AnalysisSentiment::Positive);

        Livewire::test(CompaniesIndex::class)
            ->set('sort', 'satisfaction')
            ->assertSee('سازمان خودی')
            ->assertDontSee('سازمان خارجی محرمانه');

        Livewire::test(ContactsIndex::class)
            ->set('sort', 'satisfaction')
            ->assertDontSee('مخاطب خارجی محرمانه');
    }

    public function test_own_organization_is_hidden_from_company_list(): void
    {
        $organization = $this->actingAsEmployer('میرکو');
        $this->createCompany($organization, 'شرکت میرکو', now()->subDay());
        $this->createCompany($organization, 'آلفا', now());

        $component = Livewire::test(CompaniesIndex::class);
        $this->assertSame(['آلفا'], $this->listedNames($component, 'companies'));
        $component->assertSee('آلفا')->assertDontSee('شرکت میرکو');
    }

    public function test_company_directory_rows_link_to_company_detail(): void
    {
        $organization = $this->actingAsEmployer();
        $company = $this->createCompany($organization, 'شرکت همراه', now());
        $company->update(['contacts_count' => 4]);
        $this->createContact($organization, 'علی رضایی', '09121180001', now(), $company);

        $html = Livewire::test(CompaniesIndex::class)->html();

        $this->assertStringContainsString('شرکت همراه', $html);
        $this->assertStringContainsString('data-tour="customers-companies-table"', $html);
        $this->assertStringContainsString('data-row-href="'.route('employer.customers.companies.show', $company).'"', $html);
        $this->assertStringContainsString('saas-avatar', $html);
    }

    private function listedNames(Testable $component, string $key): array
    {
        return $component->viewData($key)->pluck('name')->all();
    }

    private function actingAsEmployer(?string $organizationTitle = null): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create([
            'user_id' => $employer->id,
            ...($organizationTitle ? ['title' => $organizationTitle] : []),
        ]);

        $this->actingAs($employer);

        return $organization;
    }

    private function createCompany(Organization $organization, string $name, ?\DateTimeInterface $lastContactAt = null): CustomerCompany
    {
        return CustomerCompany::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'last_contact_at' => $lastContactAt,
        ]);
    }

    private function createContact(
        Organization $organization,
        string $name,
        string $phone,
        ?\DateTimeInterface $lastContactAt = null,
        ?CustomerCompany $company = null,
    ): Customer {
        return Customer::query()->create([
            'organization_id' => $organization->id,
            'customer_company_id' => $company?->id,
            'normalized_phone' => $phone,
            'phone_number' => $phone,
            'name' => $name,
            'last_contact_at' => $lastContactAt,
        ]);
    }

    private function attachSentiment(Organization $organization, Customer $customer, AnalysisSentiment $sentiment): void
    {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => $customer->phone_number,
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'started_at' => now(),
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 80,
            'summary' => 'خلاصه تست',
            'sentiment' => $sentiment,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'analyzed_at' => now(),
        ]);
    }
}
