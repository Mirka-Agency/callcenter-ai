<?php

namespace Tests\Feature;

use App\Application\Crm\CrmManager;
use App\Application\Crm\Deals\Actions\ChangeDealStage;
use App\Application\Crm\Deals\Actions\CloseDealAsLost;
use App\Application\Crm\Deals\Actions\CloseDealAsWon;
use App\Application\Crm\Deals\Actions\CreateDeal;
use App\Application\Crm\Deals\Actions\CreatePipeline;
use App\Application\Crm\Deals\Actions\EnsureDefaultPipeline;
use App\Application\Crm\Deals\Actions\ReopenDeal;
use App\Application\Crm\Deals\Actions\ReorderPipelineStages;
use App\Application\Crm\Deals\Actions\SuggestDealFromAnalysis;
use App\Application\Crm\Deals\Actions\UpdateDeal;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Deals\DealSuggestionStatus;
use App\Domain\Customer\CustomerLifecycleStage;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Crm\Deals\Index as DealsIndex;
use App\Livewire\Crm\Deals\Show as DealsShow;
use App\Livewire\Crm\Pipelines\Index as PipelinesIndex;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\ConversationAnalysis;
use App\Models\CrmDeal;
use App\Models\CrmDealActivity;
use App\Models\CrmDealAiSuggestion;
use App\Models\CrmDealCall;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineSync;
use App\Models\CrmSyncLog;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\CustomerProfileUpdateService;
use App\Support\Navigation\EmployeeNavigation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CrmDealsAndPipelinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_pipeline_stages_and_reorder_them(): void
    {
        [$organization, $manager] = $this->organization();
        $pipeline = app(CreatePipeline::class)->handle($organization->id, $manager, 'فروش سازمانی', 'توضیح', true);

        $this->assertTrue($pipeline->is_default);
        $this->assertSame(
            ['سرنخ جدید', 'نیازسنجی', 'ارائه پیشنهاد', 'مذاکره'],
            $pipeline->stages->pluck('name')->all(),
        );

        $ids = $pipeline->stages->pluck('id')->map(fn ($id) => (int) $id)->all();
        $reordered = array_reverse($ids);
        $updated = app(ReorderPipelineStages::class)->handle($pipeline, $manager, $reordered);

        $this->assertSame($reordered, $updated->stages->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_default_pipeline_is_created_once(): void
    {
        [$organization] = $this->organization();
        $first = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $second = app(EnsureDefaultPipeline::class)->handle($organization->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CrmPipeline::query()->count());
    }

    public function test_deal_links_customer_and_rejects_other_tenant_and_owner(): void
    {
        [$organization, $manager, $agent] = $this->organization();
        $other = Organization::factory()->create();
        $outsider = $this->agent($other, 'بیگانه', 'سازمان');
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $stage = $pipeline->stages->first();
        $customer = $this->customer($organization, '09121111111');
        $foreign = $this->customer($other, '09122222222');

        $deal = app(CreateDeal::class)->handle($organization->id, $manager, [
            'title' => 'قرارداد پشتیبانی',
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'amount_rials' => '2500000',
            'customer_ids' => [$customer->id],
            'primary_customer_id' => $customer->id,
            'owner_organization_user_id' => $agent->id,
        ]);

        $this->assertSame(2500000, $deal->amount_rials);
        $this->assertSame($agent->id, (int) $deal->owner_organization_user_id);
        $this->assertSame($customer->id, (int) $deal->primaryContact->customer_id);
        $this->assertDatabaseHas('crm_deal_activities', [
            'crm_deal_id' => $deal->id,
            'type' => 'created',
        ]);

        $rejectedCustomer = false;

        try {
            app(CreateDeal::class)->handle($organization->id, $manager, [
                'title' => 'نامعتبر',
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stage->id,
                'customer_ids' => [$foreign->id],
            ]);
        } catch (ValidationException) {
            $rejectedCustomer = true;
        }

        $rejectedOwner = false;

        try {
            app(CreateDeal::class)->handle($organization->id, $manager, [
                'title' => 'مالک نامعتبر',
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stage->id,
                'owner_organization_user_id' => $outsider->id,
            ]);
        } catch (ValidationException) {
            $rejectedOwner = true;
        }

        $this->assertTrue($rejectedCustomer);
        $this->assertTrue($rejectedOwner);
        $this->assertSame(1, CrmDeal::query()->count());
    }

    public function test_stage_change_is_historized_and_does_not_touch_customer_lifecycle(): void
    {
        [$organization, $manager] = $this->organization();
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $customer = $this->customer($organization, '09123333333');
        $customer->update(['lifecycle_stage' => CustomerLifecycleStage::Qualified]);
        $stages = $pipeline->stages->values();
        $deal = $this->deal($organization, $manager, $pipeline, $customer, $stages[0]->id);

        $moved = app(ChangeDealStage::class)->handle($deal, $manager, (int) $stages[1]->id, (int) $stages[0]->id);

        $this->assertSame((int) $stages[1]->id, (int) $moved->crm_pipeline_stage_id);
        $this->assertDatabaseHas('crm_deal_stage_history', [
            'crm_deal_id' => $deal->id,
            'to_stage_id' => $stages[1]->id,
            'to_status' => DealStatus::Open->value,
        ]);
        $this->assertSame(CustomerLifecycleStage::Qualified, $customer->fresh()->lifecycle_stage);
    }

    public function test_closed_deals_reject_stage_changes_until_reopened_by_manager(): void
    {
        [$organization, $manager, $agent] = $this->organization();
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $customer = $this->customer($organization, '09124444444');
        $stages = $pipeline->stages->values();
        $deal = $this->deal($organization, $manager, $pipeline, $customer, $stages[0]->id, $agent->id);

        $won = app(CloseDealAsWon::class)->handle($deal, $manager);
        $this->assertSame(DealStatus::Won, $won->status);
        $this->assertNotNull($won->closed_at);

        $blocked = false;

        try {
            app(ChangeDealStage::class)->handle($won, $manager, (int) $stages[1]->id, (int) $stages[0]->id);
        } catch (ValidationException) {
            $blocked = true;
        }

        $this->assertTrue($blocked);

        $lost = app(CloseDealAsLost::class)->handle(
            app(ReopenDeal::class)->handle($won->fresh(), $manager),
            $manager,
            'بودجه نداشت',
        );
        $this->assertSame(DealStatus::Lost, $lost->status);
        $this->assertSame('بودجه نداشت', $lost->lost_reason);

        $agentBlocked = false;

        try {
            app(ReopenDeal::class)->handle($lost, User::query()->find($agent->user_id));
        } catch (AuthorizationException) {
            $agentBlocked = true;
        }

        $this->assertTrue($agentBlocked);
        $this->assertSame(DealStatus::Lost, $lost->fresh()->status);
    }

    public function test_analysis_suggests_once_and_does_not_invent_amount_or_duplicate_on_retry(): void
    {
        [$organization, $manager, $agent] = $this->organization();
        $customer = $this->customer($organization, '09125555555');
        $analysis = $this->analysis($organization, $agent, $customer, '09125555555');
        $action = app(SuggestDealFromAnalysis::class);

        $first = $action->handle($analysis, $customer->id);
        $second = $action->handle($analysis->fresh(), $customer->id);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(DealSuggestionStatus::Pending, $first->status);
        $this->assertSame(40, $first->payload['lead_score']);
        $this->assertFalse($first->payload['amount_inferred']);
        $this->assertSame(0, CrmDeal::query()->count());
        $this->assertSame(1, CrmDealAiSuggestion::query()->count());

        $analysis->update(['lead_quality_json' => ['score' => 95]]);
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $deal = $this->deal($organization, $manager, $pipeline, $customer, $pipeline->stages->first()->id);
        $linkedAnalysis = $this->analysis($organization, $agent, $customer, '09125555556', 'second');
        $linkedAnalysis->update(['lead_quality_json' => ['score' => 90, 'level' => 'high']]);

        $linked = $action->handle($linkedAnalysis, $customer->id);
        $again = $action->handle($linkedAnalysis->fresh(), $customer->id);

        $this->assertSame(DealSuggestionStatus::AutoLinked, $linked->status);
        $this->assertSame($linked->id, $again->id);
        $this->assertSame(DealStatus::Open, $deal->fresh()->status);
        $this->assertNull($deal->fresh()->amount_rials);
        $this->assertSame(1, CrmDealCall::query()->count());
        $this->assertSame(0, CrmPipelineSync::query()->count());
        $this->assertSame(0, CrmSyncLog::query()->count());
        $this->assertInstanceOf(CrmManager::class, app(CrmManager::class));
    }

    public function test_multiple_open_deals_wait_for_a_human_and_personal_calls_are_ignored(): void
    {
        [$organization, $manager, $agent] = $this->organization();
        $customer = $this->customer($organization, '09126666666');
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $stageId = (int) $pipeline->stages->first()->id;
        $this->deal($organization, $manager, $pipeline, $customer, $stageId);
        $this->deal($organization, $manager, $pipeline, $customer, $stageId, title: 'معامله دوم');
        $analysis = $this->analysis($organization, $agent, $customer, '09126666666');

        $suggestion = app(SuggestDealFromAnalysis::class)->handle($analysis, $customer->id);

        $this->assertSame('review_link', $suggestion->suggestion_type->value);
        $this->assertCount(2, $suggestion->payload['candidate_deal_ids']);
        $this->assertSame(0, CrmDealCall::query()->count());

        $personal = $this->analysis($organization, $agent, $customer, '09126666667', 'personal');
        $personal->update(['is_personal' => true]);
        $this->assertNull(app(SuggestDealFromAnalysis::class)->handle($personal, $customer->id));
    }

    public function test_employee_sees_only_owned_deals_and_pages_render_rtl(): void
    {
        [$organization, $manager, $agent, $otherAgent] = $this->organization(twoAgents: true);
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $customer = $this->customer($organization, '09127777777');
        $stageId = (int) $pipeline->stages->first()->id;
        $own = $this->deal($organization, $manager, $pipeline, $customer, $stageId, $agent->id, 'معامله من');
        $other = $this->deal($organization, $manager, $pipeline, $customer, $stageId, $otherAgent->id, 'معامله همکار');

        $employee = User::query()->findOrFail($agent->user_id);
        $this->actingAs($employee);

        Livewire::test(DealsIndex::class)
            ->assertSee('معامله من')
            ->assertDontSee('معامله همکار')
            ->set('viewMode', 'table')
            ->assertSee('معامله من')
            ->call('sortBy', 'owner')
            ->assertSee('معامله من')
            ->assertDontSee('معامله همکار');

        Livewire::test(DealsShow::class, ['deal' => $own->id])
            ->assertSee('معامله من');

        Livewire::test(DealsShow::class, ['deal' => $other->id])
            ->assertStatus(404);

        $this->get(route('employee.crm.deals.index'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('معاملات');

        $this->get('/workspace/crm/pipelines')->assertNotFound();

        $this->actingAs($manager);

        $this->get(route('employer.crm.deals.index'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('معامله همکار');

        $this->get(route('employer.crm.overview'))->assertOk()->assertSee('نمای کلی فروش');
        $this->get(route('employer.crm.settings'))->assertOk()->assertSee('ساخت خودکار خاموش');
        $this->get(route('employer.crm.index'))->assertNotFound();
        $this->get(route('employer.crm.pipelines.index'))->assertOk()->assertSee('پایپ‌لاین پیش‌فرض');

        Livewire::test(PipelinesIndex::class)->assertSee('فروش');

        $routes = array_column(collect(EmployeeNavigation::items())->firstWhere('label', 'CRM')['children'], 'route');
        $this->assertSame([
            'employee.crm.overview',
            'employee.customers.index',
            'employee.crm.deals.index',
        ], $routes);
        $this->assertFalse(collect(EmployeeNavigation::items())->contains(fn (array $item) => ($item['route'] ?? null) === 'employee.customers.index'));
    }

    public function test_updating_a_closed_deal_does_not_replace_human_customer_data(): void
    {
        [$organization, $manager] = $this->organization();
        $pipeline = app(EnsureDefaultPipeline::class)->handle($organization->id);
        $customer = $this->customer($organization, '09128888888');
        $customer->update(['name' => 'نام تأییدشده', 'lifecycle_stage' => CustomerLifecycleStage::Customer]);
        $deal = $this->deal($organization, $manager, $pipeline, $customer, $pipeline->stages->first()->id);
        $won = app(CloseDealAsWon::class)->handle($deal, $manager);

        $blocked = false;

        try {
            app(UpdateDeal::class)->handle($won, $manager, [
                'title' => 'عنوان تازه',
                'amount_rials' => 10,
                'description' => 'یادداشت',
                'customer_ids' => [$customer->id],
                'primary_customer_id' => $customer->id,
                'expected_updated_at' => $won->fresh()->updated_at?->toDateTimeString(),
            ]);
        } catch (ValidationException) {
            $blocked = true;
        }

        $this->assertTrue($blocked);
        $this->assertSame('نام تأییدشده', $customer->fresh()->name);
        $this->assertSame(CustomerLifecycleStage::Customer, $customer->fresh()->lifecycle_stage);
        $this->assertSame($deal->title, $won->fresh()->title);
        $this->assertGreaterThan(0, CrmDealActivity::query()->where('type', 'won')->count());
    }

    /**
     * @return array{0: Organization, 1: User, 2: OrganizationUser, 3?: OrganizationUser}
     */
    private function organization(bool $twoAgents = false): array
    {
        $manager = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $manager->id]);
        $agent = $this->agent($organization, 'نگار', 'کریمی');

        if (! $twoAgents) {
            return [$organization, $manager, $agent];
        }

        return [$organization, $manager, $agent, $this->agent($organization, 'سارا', 'راد')];
    }

    private function agent(Organization $organization, string $firstName, string $lastName): OrganizationUser
    {
        return OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_active' => true,
        ]);
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
        CrmPipeline $pipeline,
        Customer $customer,
        int $stageId,
        ?int $ownerId = null,
        string $title = 'معامله نمونه',
    ): CrmDeal {
        return app(CreateDeal::class)->handle($organization->id, $manager, [
            'title' => $title,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stageId,
            'customer_ids' => [$customer->id],
            'primary_customer_id' => $customer->id,
            'owner_organization_user_id' => $ownerId,
        ]);
    }

    private function analysis(
        Organization $organization,
        OrganizationUser $agent,
        Customer $customer,
        string $phone,
        string $suffix = 'one',
    ): ConversationAnalysis {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => 'deal-'.$suffix.'-'.$phone,
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
            'summary' => 'مشتری درباره تمدید پرسید.',
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
