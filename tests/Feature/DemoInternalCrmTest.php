<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Deals\DealSuggestionStatus;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\CrmDeal;
use App\Models\CrmDealAiSuggestion;
use App\Models\CrmDealCall;
use App\Models\CrmPipeline;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\CustomerProfileUpdateService;
use App\Support\Seeding\DemoInternalCrm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoInternalCrmTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_deals_are_created_once_for_an_organization(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create([
            'user_id' => $manager->id,
            'is_demo' => true,
        ]);
        OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'نگار',
            'last_name' => 'کریمی',
            'is_active' => true,
        ]);

        $customers = [];
        foreach (range(1, 8) as $index) {
            $customers[] = app(CustomerProfileUpdateService::class)->create($organization->id, [
                'phone_number' => '0912000000'.$index,
                'name' => 'شخص نمونه '.$index,
            ]);
        }

        $this->analysis($organization, $customers[0], '09120000001');
        $this->analysis($organization, $customers[7], '09120000008');

        app(DemoInternalCrm::class)->seed($organization);
        app(DemoInternalCrm::class)->seed($organization);

        $this->assertSame(2, CrmPipeline::query()->where('organization_id', $organization->id)->count());
        $this->assertSame(7, CrmDeal::query()->where('organization_id', $organization->id)->count());
        $this->assertSame(1, CrmDeal::query()->where('status', DealStatus::Won)->count());
        $this->assertSame(1, CrmDeal::query()->where('status', DealStatus::Lost)->count());
        $this->assertSame(1, CrmDeal::query()->whereNull('amount_rials')->count());
        $this->assertSame(1, CrmDeal::query()->whereNull('owner_organization_user_id')->count());
        $this->assertSame(1, CrmDealCall::query()->count());
        $this->assertSame(1, CrmDealAiSuggestion::query()->where('status', DealSuggestionStatus::Pending)->count());
        $this->assertDatabaseHas('crm_deals', [
            'organization_id' => $organization->id,
            'currency' => 'IRR',
        ]);
    }

    private function analysis(Organization $organization, Customer $customer, string $phone): void
    {
        $agent = OrganizationUser::query()->where('organization_id', $organization->id)->first();
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => 'sample-'.$phone,
            'direction' => 'inbound',
            'caller_number' => $phone,
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'started_at' => now()->subHour(),
            'customer_id' => $customer->id,
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 80,
            'summary' => 'نمونه: مشتری درباره خرید پرسید.',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'customer_identity_json' => [],
            'lead_quality_json' => ['score' => 40],
            'analyzed_at' => now(),
            'is_personal' => false,
        ]);
    }
}
