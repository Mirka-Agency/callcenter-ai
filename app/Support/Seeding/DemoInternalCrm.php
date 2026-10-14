<?php

namespace App\Support\Seeding;

use App\Application\Crm\Deals\AnalysisDealEvidence;
use App\Application\Crm\Deals\DealAudit;
use App\Domain\Crm\Deals\DealActivityType;
use App\Domain\Crm\Deals\DealSource;
use App\Domain\Crm\Deals\DealStatus;
use App\Domain\Crm\Deals\DealSuggestionStatus;
use App\Domain\Crm\Deals\DealSuggestionType;
use App\Domain\Crm\Deals\PipelineStagePalette;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\CrmDeal;
use App\Models\CrmDealAiSuggestion;
use App\Models\CrmDealCall;
use App\Models\CrmDealContact;
use App\Models\CrmDealExternalLink;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationUser;
use Illuminate\Support\Facades\DB;

/**
 * Hypothetical internal CRM rows for demo organizations.
 * Didar connection settings stay in DemoCrmCatalog; these rows are local deals.
 */
class DemoInternalCrm
{
    public const SAMPLE_PIPELINE = 'فروش سازمانی';

    public function seed(Organization $organization): void
    {
        if (CrmPipeline::query()
            ->where('organization_id', $organization->id)
            ->where('name', self::SAMPLE_PIPELINE)
            ->exists()) {
            return;
        }

        $customers = Customer::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->limit(10)
            ->get();

        if ($customers->count() < 4) {
            return;
        }

        $agents = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();

        DB::transaction(function () use ($organization, $customers, $agents): void {
            $sales = $this->pipeline($organization, self::SAMPLE_PIPELINE, 'پایپ‌لاین نمونه فروش. اعداد واقعی شرکت نیستند.', true, PipelineStagePalette::DEFAULT_STAGES);
            $renewal = $this->pipeline($organization, 'تمدید قرارداد', 'پایپ‌لاین نمونه تمدید.', false, [
                ['name' => 'بررسی تمدید', 'color' => '#6366F1'],
                ['name' => 'پیشنهاد تمدید', 'color' => '#7C25FD'],
                ['name' => 'مذاکره تمدید', 'color' => '#D97706'],
            ]);

            $salesStages = $sales->stages()->get()->values();
            $renewalStages = $renewal->stages()->get()->values();
            $actorId = (int) $organization->user_id;

            $specs = [
                ['pipeline' => $sales, 'stage' => 0, 'customer' => 0, 'owner' => 0, 'title' => 'خرید لایسنس تحلیل تماس', 'amount' => 180_000_000, 'status' => DealStatus::Open, 'stale' => false],
                ['pipeline' => $sales, 'stage' => 1, 'customer' => 1, 'owner' => 1, 'title' => 'تجهیز شعبه جدید', 'amount' => 96_000_000, 'status' => DealStatus::Open, 'stale' => false],
                ['pipeline' => $sales, 'stage' => 2, 'customer' => 2, 'owner' => 2, 'title' => 'بسته مربی‌گری کارشناسان', 'amount' => 45_000_000, 'status' => DealStatus::Open, 'stale' => true],
                ['pipeline' => $sales, 'stage' => 3, 'customer' => 3, 'owner' => null, 'title' => 'جستجو در مکالمات فروش', 'amount' => null, 'status' => DealStatus::Open, 'stale' => false],
                ['pipeline' => $sales, 'stage' => 3, 'customer' => 4, 'owner' => 0, 'title' => 'گزارش عملکرد تیم', 'amount' => 320_000_000, 'status' => DealStatus::Won, 'stale' => false],
                ['pipeline' => $sales, 'stage' => 2, 'customer' => 5, 'owner' => 1, 'title' => 'یکپارچه‌سازی مرکز تماس', 'amount' => 28_000_000, 'status' => DealStatus::Lost, 'stale' => false],
                ['pipeline' => $renewal, 'stage' => 1, 'customer' => 6, 'owner' => 3, 'title' => 'تمدید سه‌ماهه', 'amount' => 54_000_000, 'status' => DealStatus::Open, 'stale' => false],
            ];

            $created = [];
            foreach ($specs as $index => $spec) {
                $customer = $customers->get($spec['customer']);
                if (! $customer instanceof Customer) {
                    continue;
                }

                $stages = $spec['pipeline']->is($sales) ? $salesStages : $renewalStages;
                $stage = $stages->get($spec['stage']) ?? $stages->first();
                if (! $stage instanceof CrmPipelineStage) {
                    continue;
                }

                $owner = null;
                if ($spec['owner'] !== null && $agents->isNotEmpty()) {
                    $owner = $agents->get($spec['owner'] % $agents->count());
                }

                $created[] = $this->deal(
                    organization: $organization,
                    pipeline: $spec['pipeline'],
                    stage: $stage,
                    customer: $customer,
                    owner: $owner instanceof OrganizationUser ? $owner : null,
                    actorId: $actorId,
                    title: $spec['title'],
                    amount: $spec['amount'],
                    status: $spec['status'],
                    stale: $spec['stale'],
                    index: $index,
                );
            }

            $this->linkSampleCall($organization, $created[0] ?? null, $actorId);
            $this->suggestSample($organization, $customers->get(7));
            $this->mapSampleExternal($organization, $created[4] ?? null);
        });
    }

    /**
     * @param  list<array{name: string, color: string}>  $stages
     */
    private function pipeline(Organization $organization, string $name, string $description, bool $default, array $stages): CrmPipeline
    {
        $pipeline = CrmPipeline::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'description' => 'نمونه دمو — '.$description,
            'is_default' => $default,
            'is_active' => true,
        ]);

        foreach (array_values($stages) as $position => $stage) {
            CrmPipelineStage::query()->create([
                'organization_id' => $organization->id,
                'crm_pipeline_id' => $pipeline->id,
                'name' => $stage['name'],
                'color' => $stage['color'],
                'position' => $position + 1,
                'is_active' => true,
            ]);
        }

        return $pipeline;
    }

    private function deal(
        Organization $organization,
        CrmPipeline $pipeline,
        CrmPipelineStage $stage,
        Customer $customer,
        ?OrganizationUser $owner,
        int $actorId,
        string $title,
        ?int $amount,
        DealStatus $status,
        bool $stale,
        int $index,
    ): CrmDeal {
        $label = mb_substr($customer->displayName(), 0, 80);
        $deal = CrmDeal::query()->create([
            'organization_id' => $organization->id,
            'crm_pipeline_id' => $pipeline->id,
            'crm_pipeline_stage_id' => $stage->id,
            'customer_company_id' => $customer->customer_company_id,
            'owner_organization_user_id' => $owner?->id,
            'title' => mb_substr($title.' — '.$label, 0, 180),
            'description' => 'نمونه دمو — این معامله برای نمایش صفحه ساخته شده و فروش واقعی نیست.',
            'amount_rials' => $amount,
            'currency' => 'IRR',
            'status' => $status,
            'source' => DealSource::Manual,
            'expected_close_on' => $status === DealStatus::Open ? now()->addDays(12 + $index)->toDateString() : null,
            'closed_at' => $status === DealStatus::Open ? null : now()->subDays(4 + $index),
            'lost_reason' => $status === DealStatus::Lost ? 'بودجه در این دوره تأیید نشد.' : null,
            'last_activity_at' => $stale ? now()->subDays(18) : now()->subDays($index),
        ]);

        CrmDealContact::query()->create([
            'organization_id' => $organization->id,
            'crm_deal_id' => $deal->id,
            'customer_id' => $customer->id,
            'is_primary' => true,
        ]);

        $audit = app(DealAudit::class);
        $audit->stage($deal, null, $stage, null, $status->value, $actorId > 0 ? $actorId : null, DealSource::Manual->value);
        $audit->record($deal, DealActivityType::Created, $actorId > 0 ? $actorId : null, DealSource::Manual->value, null, ['sample' => true]);

        if ($status === DealStatus::Won) {
            $audit->record($deal, DealActivityType::Won, $actorId > 0 ? $actorId : null, DealSource::Manual->value, null, ['sample' => true]);
        }

        if ($status === DealStatus::Lost) {
            $audit->record($deal, DealActivityType::Lost, $actorId > 0 ? $actorId : null, DealSource::Manual->value, null, ['sample' => true]);
        }

        return $deal;
    }

    private function linkSampleCall(Organization $organization, ?CrmDeal $deal, int $actorId): void
    {
        if ($deal === null) {
            return;
        }

        $customerId = CrmDealContact::query()
            ->where('crm_deal_id', $deal->id)
            ->where('is_primary', true)
            ->value('customer_id');

        if (! $customerId) {
            return;
        }

        $call = Call::query()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $customerId)
            ->whereDoesntHave('dealLink')
            ->orderByDesc('id')
            ->first();

        if (! $call instanceof Call) {
            return;
        }

        $analysis = $call->conversationAnalysis;

        CrmDealCall::query()->create([
            'organization_id' => $organization->id,
            'crm_deal_id' => $deal->id,
            'call_id' => $call->id,
            'conversation_analysis_id' => $analysis?->id,
            'source' => DealSource::Manual->value,
            'evidence_json' => $analysis ? AnalysisDealEvidence::from($analysis) + ['sample' => true] : ['sample' => true],
            'linked_by_user_id' => $actorId > 0 ? $actorId : null,
        ]);
    }

    private function suggestSample(Organization $organization, mixed $customer): void
    {
        if (! $customer instanceof Customer) {
            return;
        }

        $analysis = ConversationAnalysis::query()
            ->where('organization_id', $organization->id)
            ->where('is_personal', false)
            ->whereNotNull('call_id')
            ->whereHas('call', fn ($query) => $query->where('customer_id', $customer->id))
            ->whereNotIn('id', CrmDealAiSuggestion::query()->select('conversation_analysis_id'))
            ->whereNotIn('id', CrmDealCall::query()->whereNotNull('conversation_analysis_id')->select('conversation_analysis_id'))
            ->orderByDesc('id')
            ->first();

        if (! $analysis instanceof ConversationAnalysis) {
            return;
        }

        CrmDealAiSuggestion::query()->create([
            'organization_id' => $organization->id,
            'conversation_analysis_id' => $analysis->id,
            'customer_id' => $customer->id,
            'call_id' => $analysis->call_id,
            'suggestion_type' => DealSuggestionType::CreateDeal,
            'status' => DealSuggestionStatus::Pending,
            'proposed_title' => mb_substr('فرصت فروش — '.$customer->displayName(), 0, 180),
            'payload' => AnalysisDealEvidence::from($analysis) + [
                'sample' => true,
                'auto_create' => false,
                'reason' => 'no_open_deal',
                'candidate_deal_ids' => [],
                'summary' => $analysis->summary ?: 'پیشنهاد نمونه؛ معامله به‌صورت خودکار ساخته نمی‌شود.',
            ],
        ]);
    }

    private function mapSampleExternal(Organization $organization, ?CrmDeal $deal): void
    {
        if ($deal === null) {
            return;
        }

        $connection = OrganizationCrmConnection::query()
            ->where('organization_id', $organization->id)
            ->first();

        if (! $connection instanceof OrganizationCrmConnection) {
            return;
        }

        CrmDealExternalLink::query()->create([
            'organization_id' => $organization->id,
            'crm_deal_id' => $deal->id,
            'organization_crm_connection_id' => $connection->id,
            'external_id' => 'sample-deal-'.$deal->id,
            'sync_status' => 'idle',
        ]);
    }
}
