<?php

namespace Tests\Unit;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\Organization;
use App\Support\CustomerListSort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerListSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_sort_falls_back_to_last_contact(): void
    {
        $this->assertSame(CustomerListSort::LastContact, CustomerListSort::fromInput('drop table'));
        $this->assertSame(CustomerListSort::LastContact, CustomerListSort::fromInput(null));
        $this->assertSame(CustomerListSort::Satisfaction, CustomerListSort::fromInput('satisfaction'));
        $this->assertSame(CustomerListSort::Dissatisfaction, CustomerListSort::fromInput('dissatisfaction'));
    }

    public function test_sort_labels_match_person_and_company_lists(): void
    {
        $this->assertSame([
            'آخرین تماس',
            'راضی‌ترین مشتری',
            'ناراضی‌ترین مشتری',
            'جدیدترین مشتری',
            'قدیمی‌ترین مشتری',
        ], array_map(fn (CustomerListSort $sort) => $sort->label(), CustomerListSort::cases()));
    }

    public function test_contacts_sort_by_newest_and_oldest_created_at(): void
    {
        $organization = Organization::factory()->create();
        $older = $this->createContact($organization, 'مخاطب قدیمی', '09120000001');
        $newer = $this->createContact($organization, 'مخاطب جدید', '09120000002');

        Customer::query()->whereKey($older->id)->update(['created_at' => now()->subDays(10)]);
        Customer::query()->whereKey($newer->id)->update(['created_at' => now()->subDay()]);

        $newest = CustomerListSort::apply(Customer::query()->forOrganization($organization->id), 'newest', 'contact', $organization->id)
            ->pluck('name')
            ->all();
        $oldest = CustomerListSort::apply(Customer::query()->forOrganization($organization->id), 'oldest', 'contact', $organization->id)
            ->pluck('name')
            ->all();

        $this->assertSame(['مخاطب جدید', 'مخاطب قدیمی'], $newest);
        $this->assertSame(['مخاطب قدیمی', 'مخاطب جدید'], $oldest);
    }

    public function test_contacts_sort_by_satisfaction_with_nulls_last_and_equal_score_tie_break(): void
    {
        $organization = Organization::factory()->create();
        $happyRecent = $this->createContact($organization, 'راضی تازه', '09120000011', now()->subDay());
        $happyOlder = $this->createContact($organization, 'راضی قدیمی', '09120000012', now()->subDays(5));
        $unhappy = $this->createContact($organization, 'ناراضی', '09120000013', now());
        $this->createContact($organization, 'بدون تحلیل', '09120000014', now()->subHours(1));

        $this->attachSentiment($organization, $happyRecent, AnalysisSentiment::Positive);
        $this->attachSentiment($organization, $happyOlder, AnalysisSentiment::Positive);
        $this->attachSentiment($organization, $unhappy, AnalysisSentiment::Negative);

        $happiest = CustomerListSort::apply(Customer::query()->forOrganization($organization->id), 'satisfaction', 'contact', $organization->id)
            ->pluck('name')
            ->all();
        $unhappiest = CustomerListSort::apply(Customer::query()->forOrganization($organization->id), 'dissatisfaction', 'contact', $organization->id)
            ->pluck('name')
            ->all();

        $this->assertSame(['راضی تازه', 'راضی قدیمی', 'ناراضی', 'بدون تحلیل'], $happiest);
        $this->assertSame(['ناراضی', 'راضی تازه', 'راضی قدیمی', 'بدون تحلیل'], $unhappiest);
    }

    public function test_companies_sort_by_satisfaction_across_contacts(): void
    {
        $organization = Organization::factory()->create();
        $happyCompany = $this->createCompany($organization, 'سازمان راضی', now()->subDays(2));
        $unhappyCompany = $this->createCompany($organization, 'سازمان ناراضی', now()->subDay());
        $this->createCompany($organization, 'سازمان بدون داده', now());

        $happyContact = $this->createContact($organization, 'مخاطب راضی', '09120000021', now()->subDay(), $happyCompany);
        $unhappyContact = $this->createContact($organization, 'مخاطب ناراضی', '09120000022', now(), $unhappyCompany);

        $this->attachSentiment($organization, $happyContact, AnalysisSentiment::Positive);
        $this->attachSentiment($organization, $unhappyContact, AnalysisSentiment::Negative);

        $happiest = CustomerListSort::apply(CustomerCompany::query()->forOrganization($organization->id), 'satisfaction', 'company', $organization->id)
            ->pluck('name')
            ->all();
        $unhappiest = CustomerListSort::apply(CustomerCompany::query()->forOrganization($organization->id), 'dissatisfaction', 'company', $organization->id)
            ->pluck('name')
            ->all();

        $this->assertSame(['سازمان راضی', 'سازمان ناراضی', 'سازمان بدون داده'], $happiest);
        $this->assertSame(['سازمان ناراضی', 'سازمان راضی', 'سازمان بدون داده'], $unhappiest);
    }

    public function test_satisfaction_sort_does_not_include_other_organization_records(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $this->createContact($orgA, 'مخاطب الف', '09120000031');
        $foreign = $this->createContact($orgB, 'مخاطب سازمان دیگر', '09120000032');
        $this->attachSentiment($orgB, $foreign, AnalysisSentiment::Positive);

        $names = CustomerListSort::apply(Customer::query()->forOrganization($orgA->id), 'satisfaction', 'contact', $orgA->id)
            ->pluck('name')
            ->all();

        $this->assertSame(['مخاطب الف'], $names);
        $this->assertNotContains('مخاطب سازمان دیگر', $names);
    }

    public function test_default_sort_uses_last_contact_then_company_name(): void
    {
        $organization = Organization::factory()->create();
        $this->createCompany($organization, 'بتا', now()->subDays(3));
        $this->createCompany($organization, 'آلفا تازه', now()->subDay());
        $this->createCompany($organization, 'گاما هم‌زمان', now()->subDay());

        $names = CustomerListSort::apply(
            CustomerCompany::query()->forOrganization($organization->id),
            'last_contact',
            'company',
            $organization->id,
        )->pluck('name')->all();

        $this->assertSame(['آلفا تازه', 'گاما هم‌زمان', 'بتا'], $names);
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
