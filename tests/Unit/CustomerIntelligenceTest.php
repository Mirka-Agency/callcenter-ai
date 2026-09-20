<?php

namespace Tests\Unit;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\CustomerCompanyIntelligenceService;
use App\Services\CustomerIntelligenceService;
use App\Services\CustomerPhoneResolver;
use App\Support\CustomerAnalysisVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_resolver_normalizes_digits(): void
    {
        $resolver = app(CustomerPhoneResolver::class);

        $this->assertSame('989121234567', $resolver->normalize('+98 912-123-4567'));
    }

    public function test_sync_creates_customer_grouped_by_phone(): void
    {
        $organization = Organization::factory()->create();
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
            'external_call_id' => 'test-call-1',
            'direction' => 'inbound',
            'caller_number' => '09121234567',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'started_at' => now()->subDay(),
        ]);

        $analysis = ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 85,
            'summary' => 'خلاصه',
            'sentiment' => AnalysisSentiment::Positive,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => ['پیگیری هفته آینده'],
            'lead_quality_json' => ['score' => 80, 'level' => 'high', 'reason' => 'test'],
            'customer_identity_json' => [
                'person_name' => 'علی رضایی',
                'company_name' => 'آلفا',
                'email' => 'ali@example.com',
                'confidence' => 0.9,
            ],
            'analyzed_at' => now(),
        ]);

        $customer = app(CustomerIntelligenceService::class)->syncFromAnalysis($analysis);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame('علی رضایی', $customer->name);
        $this->assertSame('09121234567', $customer->phone_number);
        $this->assertSame('09121234567', $customer->normalized_phone);
        $this->assertNotNull($customer->customer_company_id);
        $this->assertSame('آلفا', $customer->company?->name);
        $this->assertSame(1, $customer->total_calls);
        $this->assertSame($customer->id, $call->fresh()->customer_id);
    }

    public function test_sync_does_not_create_own_organization_as_customer_company(): void
    {
        $organization = Organization::factory()->create(['title' => 'میرکو']);
        $analysis = $this->makeIdentityAnalysis($organization, [
            'company_name' => 'شرکت میرکو',
            'person_name' => 'علی رضایی',
            'confidence' => 0.95,
        ], direction: 'outbound');

        $customer = app(CustomerIntelligenceService::class)->syncFromAnalysis($analysis);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertNull($customer->customer_company_id);
        $this->assertNull($customer->company_name);
        $this->assertSame(0, CustomerCompany::query()->where('organization_id', $organization->id)->count());
    }

    public function test_sync_corrects_company_spelling_and_merges_prefixed_duplicates(): void
    {
        $organization = Organization::factory()->create(['title' => 'میرکو']);
        $first = $this->makeIdentityAnalysis($organization, [
            'company_name' => 'شركت آلفا',
            'person_name' => 'علی رضایی',
            'confidence' => 0.7,
        ], callerNumber: '09121234567');

        $customer = app(CustomerIntelligenceService::class)->syncFromAnalysis($first);
        $this->assertSame('شرکت آلفا', $customer->company?->name);

        $second = $this->makeIdentityAnalysis($organization, [
            'company_name' => 'آلفا',
            'person_name' => 'علی رضایی',
            'confidence' => 0.7,
        ], callerNumber: '09121234567', externalCallId: 'test-call-2');

        $customer = app(CustomerIntelligenceService::class)->syncFromAnalysis($second);

        $this->assertSame('آلفا', $customer->fresh()->company?->name);
        $this->assertSame(1, CustomerCompany::query()->where('organization_id', $organization->id)->count());
    }

    public function test_employee_cannot_view_other_employee_performance(): void
    {
        $organization = Organization::factory()->create();
        $analysis = ConversationAnalysis::query()->make([
            'organization_id' => $organization->id,
            'organization_user_id' => 99,
        ]);

        $this->assertFalse(CustomerAnalysisVisibility::canViewEmployeePerformance(1, $analysis, false));
        $this->assertTrue(CustomerAnalysisVisibility::canViewEmployeePerformance(99, $analysis, false));
        $this->assertTrue(CustomerAnalysisVisibility::canViewEmployeePerformance(null, $analysis, true));
    }

    public function test_aggregated_next_actions_keeps_only_the_five_highest_priority_items(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'Sara',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'organization_id' => $organization->id,
            'name' => 'علی رضایی',
            'phone_number' => '09120001111',
            'normalized_phone' => '09120001111',
        ]);

        $this->createCustomerAnalysis($organization, $employee, $customer, [
            'external_call_id' => 'routine-call',
            'analyzed_at' => now(),
            'next_actions_json' => [
                'ارسال کاتالوگ محصول',
                'هماهنگی جلسه معرفی',
                'ارسال لینک آموزش',
                'یادآوری تمدید اشتراک',
                'ثبت یادداشت در سی‌آر‌ام',
                'ارسال نمونه قرارداد',
            ],
        ]);

        $this->createCustomerAnalysis($organization, $employee, $customer, [
            'external_call_id' => 'critical-call',
            'analyzed_at' => now()->subDay(),
            'needs_attention' => true,
            'sentiment' => AnalysisSentiment::Negative,
            'customer_insights_json' => [
                'urgency_level' => 'critical',
                'risk_level' => 'high',
            ],
            'next_actions_json' => ['ثبت تیکت فوری برای قطع سرویس'],
        ]);

        $actions = app(CustomerIntelligenceService::class)->aggregatedNextActions($customer);

        $this->assertCount(5, $actions);
        $this->assertSame('ثبت تیکت فوری برای قطع سرویس', $actions[0]);
        $this->assertNotContains('ارسال نمونه قرارداد', $actions);
    }

    public function test_company_aggregated_next_actions_keeps_only_the_five_highest_priority_items(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'Sara',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);

        $company = CustomerCompany::query()->create([
            'organization_id' => $organization->id,
            'name' => 'شرکت آلفا',
        ]);

        $firstContact = Customer::query()->create([
            'organization_id' => $organization->id,
            'customer_company_id' => $company->id,
            'name' => 'مخاطب اول',
            'phone_number' => '09120001111',
            'normalized_phone' => '09120001111',
        ]);
        $secondContact = Customer::query()->create([
            'organization_id' => $organization->id,
            'customer_company_id' => $company->id,
            'name' => 'مخاطب دوم',
            'phone_number' => '09120002222',
            'normalized_phone' => '09120002222',
        ]);

        $this->createCustomerAnalysis($organization, $employee, $firstContact, [
            'external_call_id' => 'company-routine',
            'analyzed_at' => now(),
            'next_actions_json' => [
                'ارسال کاتالوگ محصول',
                'هماهنگی جلسه معرفی',
                'ارسال لینک آموزش',
                'یادآوری تمدید اشتراک',
            ],
        ]);

        $this->createCustomerAnalysis($organization, $employee, $secondContact, [
            'external_call_id' => 'company-critical',
            'analyzed_at' => now()->subDay(),
            'needs_attention' => true,
            'sentiment' => AnalysisSentiment::Negative,
            'customer_insights_json' => [
                'urgency_level' => 'high',
                'risk_level' => 'high',
            ],
            'next_actions_json' => [
                'ثبت تیکت فوری برای قطع سرویس',
                'تماس بازخورد امروز ساعت ۱۷',
                'ارسال پیامک وضعیت مرسوله',
            ],
        ]);

        $actions = app(CustomerCompanyIntelligenceService::class)->aggregatedNextActions($company);

        $this->assertCount(5, $actions);
        $this->assertSame('ثبت تیکت فوری برای قطع سرویس', $actions[0]);
        $this->assertContains('تماس بازخورد امروز ساعت ۱۷', $actions);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createCustomerAnalysis(
        Organization $organization,
        OrganizationUser $employee,
        Customer $customer,
        array $data,
    ): ConversationAnalysis {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'customer_id' => $customer->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => $data['external_call_id'],
            'direction' => 'inbound',
            'caller_number' => $customer->phone_number,
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'started_at' => $data['analyzed_at'],
        ]);

        return ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 70,
            'summary' => 'خلاصه تست',
            'sentiment' => $data['sentiment'] ?? AnalysisSentiment::Positive,
            'needs_attention' => $data['needs_attention'] ?? false,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => $data['next_actions_json'],
            'customer_insights_json' => $data['customer_insights_json'] ?? [],
            'analyzed_at' => $data['analyzed_at'],
        ]);
    }

    /**
     * @param  array{company_name: string, person_name?: string, confidence?: float}  $identity
     */
    private function makeIdentityAnalysis(
        Organization $organization,
        array $identity,
        string $direction = 'inbound',
        string $callerNumber = '09121234567',
        string $externalCallId = 'test-call-1',
    ): ConversationAnalysis {
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
            'external_call_id' => $externalCallId,
            'direction' => $direction,
            'caller_number' => $callerNumber,
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'started_at' => now()->subDay(),
        ]);

        return ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 85,
            'summary' => 'خلاصه',
            'sentiment' => AnalysisSentiment::Positive,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'lead_quality_json' => ['score' => 80, 'level' => 'high', 'reason' => 'test'],
            'customer_identity_json' => [
                'person_name' => $identity['person_name'] ?? 'علی رضایی',
                'company_name' => $identity['company_name'],
                'confidence' => $identity['confidence'] ?? 0.9,
            ],
            'analyzed_at' => now(),
        ]);
    }
}
