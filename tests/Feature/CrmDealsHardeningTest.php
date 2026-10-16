<?php

namespace Tests\Feature;

use App\Application\Crm\CrmManager;
use App\Application\Crm\Deals\Actions\ChangeDealStage;
use App\Application\Crm\Deals\Actions\CloseDealAsLost;
use App\Application\Crm\Deals\Actions\CloseDealAsWon;
use App\Application\Crm\Deals\Actions\CreateDeal;
use App\Application\Crm\Deals\Actions\EnsureDefaultPipeline;
use App\Application\Crm\Deals\Actions\LinkCallToDeal;
use App\Application\Crm\Deals\Actions\ReopenDeal;
use App\Application\Crm\Deals\Actions\SuggestDealFromAnalysis;
use App\Application\Crm\Deals\Actions\UpdateDeal;
use App\Application\Crm\Deals\Actions\UpdatePipelineStage;
use App\Application\Crm\Deals\DealAging;
use App\Application\Crm\Deals\DealHealthScorer;
use App\Application\Crm\Jobs\SyncCrmDataJob;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Crm\Deals\DealBarrierStatus;
use App\Domain\Crm\Deals\DealHealthLevel;
use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Deals\DealSuggestionStatus;
use App\Domain\Crm\Deals\DealSuggestionType;
use App\Domain\Crm\OrganizationCrmMode;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\ConversationAnalysis;
use App\Models\CrmDeal;
use App\Models\CrmDealActivity;
use App\Models\CrmDealAiSuggestion;
use App\Models\CrmDealBarrier;
use App\Models\CrmDealCall;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\CustomerProfileUpdateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CrmDealsHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_related_deal_matching_is_idempotent_and_does_not_merge(): void
    {
        [$organization, $manager, $agent] = $this->organization();
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $customer = $this->customer($organization, '09121110001');
        $dealA = $this->deal($organization, $manager, $pipeline, $customer, $pipeline->stages->first()->id, null, 'لایسنس تحلیل تماس');
        $dealB = $this->deal($organization, $manager, $pipeline, $customer, $pipeline->stages->get(1)->id, null, 'پشتیبانی سالانه');

        $analysis = $this->analysis($organization, $agent, $customer, '09121110001', 'match', [
            'summary' => 'مشتری درباره لایسنس تحلیل تماس پرسید.',
            'concerns' => [['type' => 'price', 'text' => 'قیمت لایسنس بالاست', 'severity' => 'medium']],
        ]);

        $first = app(SuggestDealFromAnalysis::class)->handle($analysis, $customer->id);
        $second = app(SuggestDealFromAnalysis::class)->handle($analysis, $customer->id);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(2, CrmDeal::query()->count());
        $this->assertSame(1, CrmDealCall::query()->count());
        $this->assertTrue(in_array($first->suggestion_type, [DealSuggestionType::LinkCall, DealSuggestionType::ReviewLink], true));
        $this->assertContains($dealA->id, array_map('intval', (array) ($first->payload['candidate_deal_ids'] ?? []))
            ?: [(int) $first->applied_crm_deal_id]);
        $this->assertSame(2, CrmDeal::query()->whereIn('id', [$dealA->id, $dealB->id])->count());
    }

    public function test_stage_aging_and_stale_detection_respect_threshold(): void
    {
        [$organization, $manager] = $this->organization();
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $stage = $pipeline->stages->first();
        app(UpdatePipelineStage::class)->handle($stage, $manager, $stage->name, $stage->color, true, 7);
        $customer = $this->customer($organization, '09121110002');
        $deal = $this->deal($organization, $manager, $pipeline, $customer, $stage->id);
        $deal->forceFill([
            'stage_entered_at' => now()->subDays(10),
            'last_activity_at' => now()->subDays(2),
        ])->save();

        $aging = app(DealAging::class)->forDeal($deal->fresh(['stage']));
        $this->assertTrue($aging['is_stale_by_stage']);
        $this->assertTrue($aging['is_stale']);
        $this->assertSame(10, $aging['days_in_current_stage']);
    }

    public function test_history_records_value_change_and_close_reasons(): void
    {
        [$organization, $manager] = $this->organization();
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $customer = $this->customer($organization, '09121110003');
        $deal = $this->deal($organization, $manager, $pipeline, $customer, $pipeline->stages->first()->id);

        $updated = app(UpdateDeal::class)->handle($deal, $manager, [
            'title' => $deal->title,
            'amount_rials' => '5000000',
            'description' => 'یادداشت',
            'customer_ids' => [$customer->id],
            'primary_customer_id' => $customer->id,
            'expected_version' => $deal->version,
        ]);

        $this->assertDatabaseHas('crm_deal_activities', [
            'crm_deal_id' => $updated->id,
            'type' => 'value_changed',
        ]);

        $lost = app(CloseDealAsLost::class)->handle($updated, $manager, '', 'price', $updated->version);
        $this->assertSame(DealStatus::Lost, $lost->status);
        $this->assertSame('price', $lost->lost_reason_code);
        $this->assertNotNull($lost->lost_reason);

        $reopened = app(ReopenDeal::class)->handle($lost, $manager, $lost->version);
        $this->assertSame(DealStatus::Open, $reopened->status);
        $this->assertNull($reopened->lost_reason_code);

        $won = app(CloseDealAsWon::class)->handle($reopened, $manager, 'value_fit', null, $reopened->version);
        $this->assertSame(DealStatus::Won, $won->status);
        $this->assertNotNull($won->won_reason);
        $this->assertGreaterThan(0, CrmDealActivity::query()->where('crm_deal_id', $won->id)->where('type', 'won')->count());
    }

    public function test_health_score_and_insufficient_data_and_barriers(): void
    {
        [$organization, $manager, $agent] = $this->organization();
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $customer = $this->customer($organization, '09121110004');
        $deal = $this->deal($organization, $manager, $pipeline, $customer, $pipeline->stages->first()->id);
        $deal->forceFill([
            'last_activity_at' => now()->subDays(20),
            'stage_entered_at' => now()->subDays(20),
            'next_action_text' => null,
        ])->save();

        $health = app(DealHealthScorer::class)->persist($deal->fresh(['stage']));
        $this->assertNotNull($health->health_score);
        $this->assertContains($health->health_level, [DealHealthLevel::Watch, DealHealthLevel::AtRisk]);

        $analysis = $this->analysis($organization, $agent, $customer, '09121110004', 'barrier', [
            'summary' => 'اعتراض به قیمت',
            'concerns' => [['type' => 'price', 'text' => 'قیمت برای ما زیاد است', 'severity' => 'high']],
        ]);
        $call = $analysis->call;
        app(LinkCallToDeal::class)->handle($deal->fresh(), $call, $manager, 'manual', $analysis);

        $barrier = CrmDealBarrier::query()->where('crm_deal_id', $deal->id)->first();
        $this->assertNotNull($barrier);
        $this->assertSame(DealBarrierStatus::Suggested, $barrier->status);
        $this->assertSame('قیمت برای ما زیاد است', $barrier->evidence_text);

        $barrier->update(['status' => DealBarrierStatus::Confirmed, 'resolved_by_user_id' => $manager->id, 'resolved_at' => now()]);
        $second = $this->analysis($organization, $agent, $customer, '09121110004', 'barrier2', [
            'summary' => 'باز هم قیمت',
            'concerns' => [['type' => 'price', 'text' => 'قیمت برای ما زیاد است', 'severity' => 'high']],
        ]);
        app(LinkCallToDeal::class)->handle($deal->fresh(), $second->call, $manager, 'manual', $second);

        $confirmed = $barrier->fresh();
        $this->assertSame(DealBarrierStatus::Confirmed, $confirmed->status);
        $this->assertSame('قیمت برای ما زیاد است', $confirmed->evidence_text);
        $this->assertGreaterThan(1, (int) $confirmed->occurrence_count);

        $closedHealth = app(DealHealthScorer::class)->calculate($deal->fresh()->forceFill(['status' => DealStatus::Won])->syncOriginal());
        $this->assertSame(DealHealthLevel::InsufficientData, $closedHealth['level']);
    }

    public function test_optimistic_locking_rejects_stale_version(): void
    {
        [$organization, $manager] = $this->organization();
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $customer = $this->customer($organization, '09121110005');
        $deal = $this->deal($organization, $manager, $pipeline, $customer, $pipeline->stages->first()->id);

        app(UpdateDeal::class)->handle($deal, $manager, [
            'title' => 'عنوان تازه',
            'amount_rials' => $deal->amount_rials,
            'customer_ids' => [$customer->id],
            'primary_customer_id' => $customer->id,
            'expected_version' => $deal->version,
        ]);

        $this->expectException(ValidationException::class);
        app(ChangeDealStage::class)->handle(
            $deal->fresh(),
            $manager,
            (int) $pipeline->stages->get(1)->id,
            (int) $deal->crm_pipeline_stage_id,
            false,
            $deal->version,
        );
    }

    public function test_crm_mode_isolation_blocks_internal_and_external_cross_use(): void
    {
        [$organization, $manager] = $this->organization();
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $customer = $this->customer($organization, '09121110006');
        $deal = $this->deal($organization, $manager, $pipeline, $customer, $pipeline->stages->first()->id);

        $organization->update(['crm_mode' => OrganizationCrmMode::External]);

        $blocked = false;
        try {
            app(CreateDeal::class)->handle($organization->id, $manager, [
                'title' => 'نباید ساخته شود',
                'pipeline_id' => $pipeline->id,
                'stage_id' => $pipeline->stages->first()->id,
                'customer_ids' => [$customer->id],
            ]);
        } catch (AuthorizationException) {
            $blocked = true;
        }
        $this->assertTrue($blocked);
        $this->assertSame(1, CrmDeal::query()->whereKey($deal->id)->count());

        $internal = Organization::factory()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Employer])->id,
            'crm_mode' => OrganizationCrmMode::Internal,
        ]);
        $result = CrmManager::forOrganization($internal->id)->testConnection();
        $this->assertFalse($result->success);

        SyncCrmDataJob::dispatchSync($internal->id);
        $this->assertTrue($deal->fresh()->exists);
    }

    public function test_employer_cannot_change_crm_mode_via_mass_assignment_on_profile_path(): void
    {
        [$organization, $manager] = $this->organization();
        $this->assertSame(OrganizationCrmMode::Internal, $organization->crmMode());

        $this->actingAs($manager)
            ->get(route('employer.organization.profile'))
            ->assertOk()
            ->assertDontSee('crm_mode', false);

        $organization->update(['title' => $organization->title.' ']);
        $this->assertSame(OrganizationCrmMode::Internal, $organization->fresh()->crmMode());
    }

    /**
     * @return array{0: Organization, 1: User, 2: OrganizationUser}
     */
    private function organization(): array
    {
        $manager = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create([
            'user_id' => $manager->id,
            'crm_mode' => OrganizationCrmMode::Internal,
        ]);
        $agent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'نگار',
            'last_name' => 'کریمی',
            'is_active' => true,
        ]);

        return [$organization, $manager, $agent];
    }

    private function customer(Organization $organization, string $phone): Customer
    {
        return app(CustomerProfileUpdateService::class)->create($organization->id, [
            'phone_number' => $phone,
            'name' => 'شخص '.$phone,
        ]);
    }

    private function deal(
        Organization $organization,
        User $manager,
        $pipeline,
        Customer $customer,
        int $stageId,
        ?int $ownerId = null,
        string $title = 'معامله نمونه',
    ): CrmDeal {
        return app(CreateDeal::class)->handle($organization->id, $manager, [
            'title' => $title,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stageId,
            'amount_rials' => '1000000',
            'customer_ids' => [$customer->id],
            'primary_customer_id' => $customer->id,
            'owner_organization_user_id' => $ownerId,
        ]);
    }

    /** @param  array{summary?: string, concerns?: list<array<string, mixed>>}  $extra */
    private function analysis(
        Organization $organization,
        OrganizationUser $agent,
        Customer $customer,
        string $phone,
        string $suffix = 'one',
        array $extra = [],
    ): ConversationAnalysis {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => 'hard-'.$suffix.'-'.$phone,
            'direction' => 'inbound',
            'caller_number' => $phone,
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'started_at' => now()->subHour(),
            'customer_id' => $customer->id,
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
            'organization_user_id' => $agent->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 80,
            'summary' => $extra['summary'] ?? 'مشتری درباره تمدید پرسید.',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'customer_identity_json' => [],
            'lead_quality_json' => ['score' => 40],
            'concerns_json' => $extra['concerns'] ?? [],
            'analyzed_at' => now(),
            'is_personal' => false,
        ]);
    }
}
