<?php

namespace Tests\Unit;

use App\Application\Llm\Services\PromptBuilder;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\CustomerIntelligenceService;
use App\Support\CustomerTenantGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class CustomerTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_phone_creates_separate_customers_per_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $phone = '09121234567';

        $customerA = $this->syncCustomerForOrg($orgA, $phone, 'علی در شرکت الف');
        $customerB = $this->syncCustomerForOrg($orgB, $phone, 'علی در شرکت ب');

        $this->assertNotSame($customerA->id, $customerB->id);
        $this->assertSame($orgA->id, $customerA->organization_id);
        $this->assertSame($orgB->id, $customerB->organization_id);
        $this->assertSame($customerA->normalized_phone, $customerB->normalized_phone);
        $this->assertSame('علی در شرکت الف', $customerA->name);
        $this->assertSame('علی در شرکت ب', $customerB->name);

        $this->assertSame(2, Customer::query()->where('normalized_phone', $customerA->normalized_phone)->count());
    }

    public function test_customer_timeline_never_includes_calls_from_other_organizations(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $phone = '09129876543';

        $customerA = $this->syncCustomerForOrg($orgA, $phone, 'مشتری الف', 3);
        $this->syncCustomerForOrg($orgB, $phone, 'مشتری ب', 5);

        $timeline = app(CustomerIntelligenceService::class)->timeline($customerA);

        $this->assertCount(3, $timeline);
        $this->assertSame(3, $customerA->fresh()->total_calls);
        $this->assertSame(5, Customer::query()->where('organization_id', $orgB->id)->first()->total_calls);
    }

    public function test_call_cannot_be_linked_to_customer_outside_its_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $customerB = Customer::query()->create([
            'organization_id' => $orgB->id,
            'normalized_phone' => '09121111111',
            'phone_number' => '09121111111',
        ]);

        $callA = Call::query()->create([
            'organization_id' => $orgA->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => 'cross-org-call',
            'direction' => 'inbound',
            'caller_number' => '09121111111',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot link a call to a customer outside its organization.');

        $callA->update(['customer_id' => $customerB->id]);
    }

    public function test_customer_tenant_guard_rejects_cross_organization_access(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $customer = Customer::query()->create([
            'organization_id' => $orgA->id,
            'normalized_phone' => '09122222222',
            'phone_number' => '09122222222',
        ]);

        $this->expectException(NotFoundHttpException::class);

        CustomerTenantGuard::assertCustomerInOrganization($customer, $orgB->id);
    }

    public function test_prompt_includes_tenant_isolation_policy(): void
    {
        $policy = PromptBuilder::customerIdentityPolicy();

        $this->assertStringContainsString('ایزولاسیون چندمستاجری', $policy);
        $this->assertStringContainsString('سازمان فعلی + شماره تلفن', $policy);
    }

    private function syncCustomerForOrg(
        Organization $organization,
        string $phone,
        string $name,
        int $callCount = 1,
    ): Customer {
        $user = User::factory()->create();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'Agent',
            'last_name' => 'Test',
            'is_active' => true,
        ]);

        $service = app(CustomerIntelligenceService::class);
        $customer = null;

        for ($i = 0; $i < $callCount; $i++) {
            $call = Call::query()->create([
                'organization_id' => $organization->id,
                'organization_user_id' => $employee->id,
                'source' => ConversationSource::Voip,
                'provider_code' => 'novatel',
                'external_call_id' => "call-{$organization->id}-{$i}",
                'direction' => 'inbound',
                'caller_number' => $phone,
                'receiver_number' => '02100000000',
                'status' => 'completed',
                'processing_status' => 'analyzed',
                'started_at' => now()->subDays($callCount - $i),
            ]);

            CallRecording::query()->create([
                'call_id' => $call->id,
                'source_url' => 'https://pbx.example/'.$call->id.'.wav',
                'storage_disk' => 'local',
                'storage_path' => 'recordings/test-'.$call->id.'.wav',
                'status' => 'completed',
                'is_expired' => false,
            ]);

            $analysis = ConversationAnalysis::query()->create([
                'organization_id' => $organization->id,
                'organization_user_id' => $employee->id,
                'call_id' => $call->id,
                'source' => ConversationSource::Voip,
                'llm_provider' => 'openai',
                'model_name' => 'gpt-4o-mini',
                'score' => 70 + $i,
                'summary' => "خلاصه {$name} #{$i}",
                'sentiment' => AnalysisSentiment::Positive,
                'strengths_json' => [],
                'weaknesses_json' => [],
                'next_actions_json' => [],
                'lead_quality_json' => ['score' => 70, 'level' => 'medium', 'reason' => 'test'],
                'customer_identity_json' => [
                    'person_name' => $name,
                    'confidence' => 0.9,
                ],
                'analyzed_at' => now(),
            ]);

            $customer = $service->syncFromAnalysis($analysis);
        }

        return $customer->fresh();
    }
}
