<?php

namespace Tests\Unit;

use App\Application\Customer\Actions\ResolveIdentityReview;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Models\CustomerIdentityReview;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\CustomerIntelligenceService;
use App\Services\CustomerPhoneResolver;
use App\Services\CustomerProfileUpdateService;
use App\Support\CustomerFieldProvenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerIdentityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_iranian_mobile_formats_and_persian_digits_share_one_valid_key(): void
    {
        $resolver = app(CustomerPhoneResolver::class);
        $formats = ['09121234567', '9121234567', '+989121234567', '00989121234567', '۰۹۱۲۱۲۳۴۵۶۷'];
        $sort = function (array $keys): array {
            sort($keys, SORT_STRING);

            return $keys;
        };
        $expected = $sort($resolver->equivalentKeys('09121234567'));

        foreach ($formats as $phone) {
            $this->assertSame($expected, $sort($resolver->equivalentKeys($phone)));
        }

        $persian = $resolver->inspect('۰۹۱۲۱۲۳۴۵۶۷');
        $this->assertTrue($persian->isValid);
        $this->assertSame('IR', $persian->country);
        $this->assertSame('09121234567', $persian->normalized);
    }

    public function test_extension_and_foreign_numbers_are_not_rewritten_as_iranian_mobiles(): void
    {
        $resolver = app(CustomerPhoneResolver::class);

        $extension = $resolver->inspect('101');
        $this->assertFalse($extension->isValid);
        $this->assertSame('extension', $extension->reason);

        $landline = $resolver->inspect('02188776655');
        $this->assertTrue($landline->isValid);
        $this->assertSame('landline', $landline->reason);

        $foreign = $resolver->inspect('+1 415 555 2671');
        $this->assertTrue($foreign->isValid);
        $this->assertNull($foreign->country);
        $this->assertSame('14155552671', $foreign->normalized);
        $this->assertFalse(str_starts_with((string) $foreign->normalized, '98'));
    }

    public function test_spoken_phone_alone_does_not_create_a_customer(): void
    {
        $organization = Organization::factory()->create();
        $analysis = $this->analysis($organization, [
            'person_name' => 'علی رضایی',
            'phone_number' => '09125556677',
            'confidence' => 0.99,
            'evidence' => 'مشتری گفت: شماره من ۰۹۱۲۵۵۵۶۶۷۷ است',
        ], caller: '101');

        $customer = app(CustomerIntelligenceService::class)->syncFromAnalysis($analysis);

        $this->assertNull($customer);
        $this->assertSame(0, Customer::query()->count());
        $this->assertNull($analysis->call->fresh()->customer_id);
        $this->assertDatabaseHas('customer_identity_reviews', [
            'kind' => 'unresolved_phone',
            'status' => 'pending',
        ]);
    }

    public function test_self_introduction_fills_an_empty_name_but_not_a_company(): void
    {
        $organization = Organization::factory()->create();
        $analysis = $this->analysis($organization, [
            'person_name' => 'علی رضایی',
            'company_name' => 'آلفا',
            'confidence' => 0.9,
            'evidence' => 'مشتری گفت: من علی رضایی هستم',
        ]);

        $customer = app(CustomerIntelligenceService::class)->syncFromAnalysis($analysis);

        $this->assertSame('علی رضایی', $customer->name);
        $this->assertNull($customer->customer_company_id);
        $this->assertSame(0, CustomerCompany::query()->count());
        $this->assertTrue(CustomerIdentityReview::query()->where('kind', 'company_candidate')->exists());
    }

    public function test_third_party_name_and_high_confidence_without_evidence_stay_suggestions(): void
    {
        $organization = Organization::factory()->create();
        $analysis = $this->analysis($organization, [
            'person_name' => 'حسین مرادی',
            'confidence' => 0.99,
            'evidence' => 'با آقای حسین مرادی صحبت کردم',
        ]);

        $customer = app(CustomerIntelligenceService::class)->syncFromAnalysis($analysis);

        $this->assertNull($customer->name);
        $this->assertDatabaseHas('customer_identity_reviews', [
            'customer_id' => $customer->id,
            'field' => 'name',
            'status' => 'pending',
        ]);
    }

    public function test_editing_only_the_name_does_not_lock_an_empty_email(): void
    {
        $organization = Organization::factory()->create();
        $profiles = app(CustomerProfileUpdateService::class);
        $customer = $profiles->create($organization->id, [
            'phone_number' => '09124445566',
        ]);
        $customer = $profiles->update($customer, [
            'name' => 'نام دستی',
            'phone_number' => '09124445566',
            'email' => null,
            'job_title' => null,
            'company_name' => null,
        ]);

        $this->assertTrue(CustomerFieldProvenance::isHumanLocked($customer->identity_field_sources, 'name'));
        $this->assertFalse(CustomerFieldProvenance::isHumanLocked($customer->identity_field_sources, 'email'));

        $analysis = $this->analysis($organization, [
            'person_name' => 'نام مدل',
            'email' => 'customer@example.com',
            'confidence' => 0.95,
            'evidence' => 'مشتری گفت: ایمیل من customer@example.com است',
        ], caller: '09124445566');

        $enriched = app(CustomerIntelligenceService::class)->syncFromAnalysis($analysis);

        $this->assertSame('نام دستی', $enriched->name);
        $this->assertSame('customer@example.com', $enriched->email);
    }

    public function test_manager_can_accept_a_name_suggestion_without_merging_duplicates(): void
    {
        $organization = Organization::factory()->create();
        $analysis = $this->analysis($organization, [
            'person_name' => 'مریم کریمی',
            'confidence' => 0.4,
            'evidence' => 'با خانم مریم صحبت شد',
        ]);
        $customer = app(CustomerIntelligenceService::class)->syncFromAnalysis($analysis);
        $review = CustomerIdentityReview::query()->where('field', 'name')->firstOrFail();

        app(ResolveIdentityReview::class)->accept($review, $organization->id, null);

        $this->assertSame('مریم کریمی', $customer->fresh()->name);
        $this->assertSame('accepted', $review->fresh()->status);
        $this->assertTrue(CustomerFieldProvenance::isHumanLocked($customer->fresh()->identity_field_sources, 'name'));
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function analysis(Organization $organization, array $identity, string $caller = '09121234567'): ConversationAnalysis
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
            'external_call_id' => 'identity-'.md5($caller.json_encode($identity)),
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
            'score' => 80,
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
