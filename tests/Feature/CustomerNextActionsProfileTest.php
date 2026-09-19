<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employer\Customers\Companies\Show as CompanyShow;
use App\Livewire\Employer\Customers\Show as ContactShow;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerNextActionsProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_profile_shows_at_most_five_priority_next_actions(): void
    {
        $organization = $this->actingAsEmployer();
        $customer = $this->createContact($organization, 'علی رضایی', '09121110001');

        $this->attachActions($organization, $customer, [
            'ارسال کاتالوگ محصول',
            'هماهنگی جلسه معرفی',
            'ارسال لینک آموزش',
            'یادآوری تمدید اشتراک',
            'ثبت یادداشت در سی‌آر‌ام',
            'ارسال نمونه قرارداد کم‌اولویت',
        ]);
        $this->attachActions($organization, $customer, ['ثبت تیکت فوری برای قطع سرویس'], [
            'needs_attention' => true,
            'sentiment' => AnalysisSentiment::Negative,
            'customer_insights_json' => ['urgency_level' => 'critical', 'risk_level' => 'high'],
            'analyzed_at' => now()->subDay(),
        ]);

        $component = Livewire::test(ContactShow::class, ['customer' => $customer])
            ->assertSee('اقدامات بعدی')
            ->assertSee('ثبت تیکت فوری برای قطع سرویس');

        $this->assertSame('ثبت تیکت فوری برای قطع سرویس', $component->viewData('nextActions')[0] ?? null);
        $this->assertCount(5, $component->viewData('nextActions'));
        $this->assertNotContains('ارسال نمونه قرارداد کم‌اولویت', $component->viewData('nextActions'));
    }

    public function test_company_profile_shows_at_most_five_priority_next_actions(): void
    {
        $organization = $this->actingAsEmployer();
        $company = CustomerCompany::query()->create([
            'organization_id' => $organization->id,
            'name' => 'شرکت آلفا',
        ]);
        $customer = $this->createContact($organization, 'مخاطب شرکت', '09121110002', $company);

        $this->attachActions($organization, $customer, [
            'ارسال کاتالوگ محصول',
            'هماهنگی جلسه معرفی',
            'ارسال لینک آموزش',
            'یادآوری تمدید اشتراک',
            'ثبت یادداشت در سی‌آر‌ام',
            'ارسال نمونه قرارداد کم‌اولویت',
        ]);
        $this->attachActions($organization, $customer, ['ثبت تیکت فوری برای قطع سرویس'], [
            'needs_attention' => true,
            'sentiment' => AnalysisSentiment::Negative,
            'customer_insights_json' => ['urgency_level' => 'high', 'risk_level' => 'high'],
            'analyzed_at' => now()->subDay(),
        ]);

        $component = Livewire::test(CompanyShow::class, ['customerCompany' => $company->fresh()])
            ->assertSee('اقدامات بعدی')
            ->assertSee('ثبت تیکت فوری برای قطع سرویس')
            ->assertDontSee('ارسال نمونه قرارداد کم‌اولویت');

        $this->assertCount(5, $component->viewData('nextActions'));
        $this->assertNotContains('ارسال نمونه قرارداد کم‌اولویت', $component->viewData('nextActions'));
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    private function createContact(
        Organization $organization,
        string $name,
        string $phone,
        ?CustomerCompany $company = null,
    ): Customer {
        return Customer::query()->create([
            'organization_id' => $organization->id,
            'customer_company_id' => $company?->id,
            'normalized_phone' => $phone,
            'phone_number' => $phone,
            'name' => $name,
        ]);
    }

    /**
     * @param  list<string>  $actions
     * @param  array<string, mixed>  $overrides
     */
    private function attachActions(Organization $organization, Customer $customer, array $actions, array $overrides = []): void
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
            'started_at' => $overrides['analyzed_at'] ?? now(),
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 80,
            'summary' => 'خلاصه تست',
            'sentiment' => $overrides['sentiment'] ?? AnalysisSentiment::Positive,
            'needs_attention' => $overrides['needs_attention'] ?? false,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => $actions,
            'customer_insights_json' => $overrides['customer_insights_json'] ?? [],
            'analyzed_at' => $overrides['analyzed_at'] ?? now(),
        ]);
    }
}
