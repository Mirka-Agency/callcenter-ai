<?php

namespace App\Livewire\Crm\Deals;

use App\Application\Crm\Deals\Actions\AcceptDealSuggestion;
use App\Application\Crm\Deals\Actions\ChangeDealStage;
use App\Application\Crm\Deals\Actions\CreateDeal;
use App\Application\Crm\Deals\Actions\CreatePipeline;
use App\Application\Crm\Deals\Actions\CreatePipelineStage;
use App\Application\Crm\Deals\Actions\DeletePipeline;
use App\Application\Crm\Deals\Actions\DeletePipelineStage;
use App\Application\Crm\Deals\Actions\DismissDealSuggestion;
use App\Application\Crm\Deals\Actions\UpdatePipelineStage;
use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealOverview;
use App\Application\Crm\Deals\DealQuery;
use App\Domain\Crm\Deals\DealSource;
use App\Domain\Crm\Deals\PipelineStagePalette;
use App\Enums\UserRole;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmDeal;
use App\Models\CrmDealAiSuggestion;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\OrganizationUser;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('کاریز')]
class Index extends Component
{
    use ResolvesCrmContext;
    use WithPagination;

    #[Url(except: 'kanban')]
    public string $viewMode = 'kanban';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $pipelineId = '';

    #[Url(except: 'all')]
    public string $owner = 'all';

    #[Url(except: 'activity')]
    public string $sort = 'activity';

    #[Url(except: 'desc')]
    public string $direction = 'desc';

    #[Url(as: 'customer', except: '')]
    public string $customerFilter = '';

    public bool $showCreate = false;

    public bool $showCreatePipeline = false;

    public bool $showStagesModal = false;

    public string $pipelineKind = 'sales';

    public string $pipelineName = '';

    public string $pipelineDescription = '';

    public string $newStageName = '';

    /** @var array<string, string> */
    public array $stageNames = [];

    public string $title = '';

    public string $amountRials = '';

    public string $stageId = '';

    public string $customerQuery = '';

    public string $customerId = '';

    public string $companyId = '';

    public string $ownerId = '';

    public string $expectedCloseOn = '';

    public string $description = '';

    public function mount(): void
    {
        if ($this->pipelineId === '') {
            $pipeline = CrmPipeline::query()
                ->where('organization_id', $this->crmOrganizationId())
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();
            $this->pipelineId = $pipeline ? (string) $pipeline->id : '';
        }

        $this->syncViewModeForStatus();
        $this->syncDefaultStage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->syncViewModeForStatus();
        $this->resetPage();
    }

    public function updatedPipelineId(): void
    {
        $this->syncDefaultStage();
        $this->resetPage();
    }

    public function updatedOwner(): void
    {
        $this->resetPage();
    }

    public function selectPipeline(string $pipelineId): void
    {
        $this->pipelineId = $pipelineId;
        $this->syncDefaultStage();
        $this->resetPage();
    }

    public function selectStatus(string $status): void
    {
        if (! in_array($status, ['open', 'won', 'lost'], true)) {
            return;
        }

        $this->status = $status;
        $this->syncViewModeForStatus();
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        $allowed = ['activity', 'amount', 'close', 'title', 'owner', 'stage'];

        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }

        $this->viewMode = 'table';
        $this->resetPage();
    }

    public function openCreateDealModal(?int $stageId = null): void
    {
        if (! ctype_digit($this->pipelineId)) {
            return;
        }

        if ($stageId !== null) {
            $stage = CrmPipelineStage::query()
                ->where('organization_id', $this->crmOrganizationId())
                ->where('crm_pipeline_id', (int) $this->pipelineId)
                ->whereKey($stageId)
                ->where('is_active', true)
                ->first();

            if (! $stage instanceof CrmPipelineStage) {
                return;
            }

            $this->stageId = (string) $stage->id;
        } elseif ($this->stageId === '') {
            $this->syncDefaultStage();
        }

        $this->resetErrorBag();
        $this->showCreate = true;
    }

    public function closeCreateDealModal(): void
    {
        $this->showCreate = false;
        $this->reset(['title', 'amountRials', 'customerQuery', 'customerId', 'companyId', 'ownerId', 'expectedCloseOn', 'description']);
        $this->resetErrorBag();
    }

    public function startCreateInStage(int $stageId): void
    {
        $this->openCreateDealModal($stageId);
    }

    public function chooseCustomer(int $customerId): void
    {
        $customer = Customer::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->find($customerId);

        if (! $customer instanceof Customer) {
            return;
        }

        $this->customerId = (string) $customer->id;
        $this->customerQuery = $customer->displayName();
        $this->companyId = $customer->customer_company_id ? (string) $customer->customer_company_id : '';
    }

    public function createDeal(CreateDeal $action): void
    {
        $deal = $action->handle($this->crmOrganizationId(), $this->crmUser(), [
            'title' => $this->title,
            'pipeline_id' => (int) $this->pipelineId,
            'stage_id' => (int) $this->stageId,
            'amount_rials' => $this->amountRials,
            'customer_ids' => $this->customerId !== '' ? [(int) $this->customerId] : [],
            'primary_customer_id' => $this->customerId !== '' ? (int) $this->customerId : null,
            'company_id' => $this->companyId !== '' ? (int) $this->companyId : null,
            'owner_organization_user_id' => $this->ownerId !== '' ? (int) $this->ownerId : null,
            'expected_close_on' => $this->expectedCloseOn,
            'description' => $this->description,
        ], DealSource::Manual);

        $this->closeCreateDealModal();
        $this->toast('فرصت ثبت شد.');
        $this->redirect(route($this->crmPortal().'.crm.deals.show', $deal), navigate: true);
    }

    public function openCreatePipelineModal(): void
    {
        $this->pipelineKind = PipelineStagePalette::KIND_SALES;
        $this->pipelineName = PipelineStagePalette::defaultName($this->pipelineKind);
        $this->pipelineDescription = '';
        $this->resetErrorBag();
        $this->showCreatePipeline = true;
    }

    public function closeCreatePipelineModal(): void
    {
        $this->showCreatePipeline = false;
        $this->reset(['pipelineName', 'pipelineDescription']);
        $this->pipelineKind = PipelineStagePalette::KIND_SALES;
        $this->resetErrorBag();
    }

    public function selectPipelineKind(string $kind): void
    {
        $kind = PipelineStagePalette::normalizeKind($kind);
        $previousDefault = PipelineStagePalette::defaultName($this->pipelineKind);
        $this->pipelineKind = $kind;

        if (trim($this->pipelineName) === '' || trim($this->pipelineName) === $previousDefault) {
            $this->pipelineName = PipelineStagePalette::defaultName($kind);
        }
    }

    public function createPipeline(CreatePipeline $action): void
    {
        $pipeline = $action->handle(
            $this->crmOrganizationId(),
            $this->crmUser(),
            $this->pipelineName,
            $this->pipelineDescription !== '' ? $this->pipelineDescription : null,
            true,
            $this->pipelineKind,
        );

        $this->closeCreatePipelineModal();
        $this->pipelineId = (string) $pipeline->id;
        $this->syncDefaultStage();
        $this->toast('کاریز ساخته شد.');
    }

    public function deleteCurrentPipeline(DeletePipeline $action): void
    {
        if (! ctype_digit($this->pipelineId)) {
            return;
        }

        $pipeline = $this->pipeline();
        $action->handle($pipeline, $this->crmUser());

        $next = CrmPipeline::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $this->pipelineId = $next ? (string) $next->id : '';
        $this->showStagesModal = false;
        $this->syncDefaultStage();
        $this->toast('کاریز حذف شد.');
    }

    public function openStagesModal(): void
    {
        if (! ctype_digit($this->pipelineId)) {
            return;
        }

        $this->pipeline();
        $this->syncStageNames();
        $this->newStageName = '';
        $this->resetErrorBag();
        $this->showStagesModal = true;
    }

    public function closeStagesModal(): void
    {
        $this->showStagesModal = false;
        $this->reset(['newStageName']);
        $this->resetErrorBag();
    }

    public function addStage(CreatePipelineStage $action): void
    {
        $pipeline = $this->pipeline()->loadCount('stages');
        $palette = PipelineStagePalette::ALLOWED;
        $color = $palette[((int) $pipeline->stages_count) % count($palette)];

        $action->handle($pipeline, $this->crmUser(), $this->newStageName, $color);
        $this->newStageName = '';
        $this->syncStageNames();
        $this->toast('مرحله اضافه شد.');
    }

    public function saveStageName(int $stageId, UpdatePipelineStage $action): void
    {
        $stage = $this->stage($stageId);
        $name = trim((string) ($this->stageNames[(string) $stageId] ?? ''));

        $action->handle(
            $stage,
            $this->crmUser(),
            $name,
            $stage->color,
            (bool) $stage->is_active,
            $stage->stale_after_days,
        );
        $this->stageNames[(string) $stageId] = $stage->fresh()->name;
        $this->toast('نام مرحله ذخیره شد.');
    }

    public function deleteManagedStage(int $stageId, DeletePipelineStage $action): void
    {
        $stage = $this->stage($stageId);
        $transferId = null;

        $hasDeals = CrmDeal::withTrashed()
            ->where('crm_pipeline_stage_id', $stage->id)
            ->exists();

        if ($hasDeals) {
            $transferId = CrmPipelineStage::query()
                ->where('crm_pipeline_id', $stage->crm_pipeline_id)
                ->whereKeyNot($stage->id)
                ->orderBy('position')
                ->value('id');
        }

        $action->handle($stage, $this->crmUser(), $transferId ? (int) $transferId : null);

        if ((string) $this->stageId === (string) $stageId) {
            $this->syncDefaultStage();
        }

        $this->syncStageNames();
        $this->toast('مرحله حذف شد.');
    }

    public function moveDeal(int $dealId, int $stageId, ChangeDealStage $action, DealQuery $query): void
    {
        $deal = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $dealId);
        $action->handle($deal, $this->crmUser(), $stageId, (int) $deal->crm_pipeline_stage_id);
        $this->toast('مرحله فرصت به‌روز شد.');
    }

    public function acceptSuggestion(int $suggestionId, ?int $dealId, AcceptDealSuggestion $action): void
    {
        $suggestion = $this->suggestion($suggestionId);
        $deal = $action->handle($suggestion, $this->crmUser(), $dealId);
        $this->toast('پیشنهاد روی فرصت اعمال شد.');
        $this->redirect(route($this->crmPortal().'.crm.deals.show', $deal), navigate: true);
    }

    public function dismissSuggestion(int $suggestionId, DismissDealSuggestion $action): void
    {
        $action->handle($this->suggestion($suggestionId), $this->crmUser());
        $this->toast('پیشنهاد رد شد.');
    }

    public function render(DealQuery $query, DealAuthorization $auth, DealOverview $overview)
    {
        $organizationId = $this->crmOrganizationId();
        $actor = $this->crmUser();
        $filters = [
            'search' => $this->search,
            'status' => $this->status,
            'pipeline_id' => ctype_digit($this->pipelineId) ? (int) $this->pipelineId : null,
            'owner' => $this->owner,
            'customer_id' => ctype_digit($this->customerFilter) ? (int) $this->customerFilter : null,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ];
        $pipelines = CrmPipeline::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
        $stages = ctype_digit($this->pipelineId)
            ? CrmPipelineStage::query()->where('crm_pipeline_id', (int) $this->pipelineId)->orderBy('position')->get()
            : collect();
        $customerChoices = mb_strlen(trim($this->customerQuery)) >= 2
            ? Customer::query()->forOrganization($organizationId)
                ->where(function ($inner): void {
                    $term = '%'.addcslashes(trim($this->customerQuery), '%_\\').'%';
                    $inner->where('name', 'like', $term)->orWhere('phone_number', 'like', $term);
                })
                ->limit(8)
                ->get()
            : collect();
        $pipelineId = ctype_digit($this->pipelineId) ? (int) $this->pipelineId : null;
        $currentPipeline = $pipelines->firstWhere('id', $pipelineId);
        $showKanban = $this->status === 'open' && $pipelineId !== null;
        $summary = $overview->summarize($organizationId, $actor, $pipelineId);
        $stageOpenCounts = collect($summary['stages'] ?? [])
            ->mapWithKeys(fn (array $row) => [(int) $row['id'] => (int) $row['count']])
            ->all();

        return view('livewire.crm.deals.index', [
            'portal' => $this->crmPortal(),
            'isManager' => $actor->role === UserRole::Employer,
            'pipelines' => $pipelines,
            'currentPipeline' => $currentPipeline,
            'stages' => $stages,
            'summary' => $summary,
            'stageOpenCounts' => $stageOpenCounts,
            'pipelineOpenCounts' => $query->openCountsByPipeline($organizationId, $actor),
            'deals' => $showKanban
                ? null
                : $query->paginate($organizationId, $actor, $filters),
            'board' => $showKanban
                ? $query->board($organizationId, $actor, $pipelineId, $filters)
                    ->groupBy(fn ($deal) => (int) $deal->crm_pipeline_stage_id)
                : collect(),
            'suggestions' => $query->pendingSuggestions($organizationId, $actor),
            'owners' => OrganizationUser::query()->where('organization_id', $organizationId)->where('is_active', true)->orderBy('last_name')->get(),
            'customerChoices' => $customerChoices,
            'canCreate' => $auth->canCreate($actor, $organizationId),
            'canManagePipelines' => $auth->canManagePipelines($actor, $organizationId),
        ])->layout($this->crmLayout());
    }

    private function syncViewModeForStatus(): void
    {
        $this->viewMode = $this->status === 'open' ? 'kanban' : 'table';
    }

    private function syncDefaultStage(): void
    {
        if (! ctype_digit($this->pipelineId)) {
            $this->stageId = '';

            return;
        }

        $stage = CrmPipelineStage::query()
            ->where('crm_pipeline_id', (int) $this->pipelineId)
            ->where('is_active', true)
            ->orderBy('position')
            ->first();
        $this->stageId = $stage ? (string) $stage->id : '';
    }

    private function syncStageNames(): void
    {
        if (! ctype_digit($this->pipelineId)) {
            $this->stageNames = [];

            return;
        }

        $this->stageNames = CrmPipelineStage::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->where('crm_pipeline_id', (int) $this->pipelineId)
            ->orderBy('position')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (CrmPipelineStage $stage) => [(string) $stage->id => (string) $stage->name])
            ->all();
    }

    private function pipeline(): CrmPipeline
    {
        $pipeline = CrmPipeline::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->find((int) $this->pipelineId);

        if (! $pipeline instanceof CrmPipeline) {
            abort(404);
        }

        return $pipeline;
    }

    private function stage(int $stageId): CrmPipelineStage
    {
        $stage = CrmPipelineStage::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->where('crm_pipeline_id', (int) $this->pipelineId)
            ->find($stageId);

        if (! $stage instanceof CrmPipelineStage) {
            abort(404);
        }

        return $stage;
    }

    private function suggestion(int $suggestionId): CrmDealAiSuggestion
    {
        $suggestion = CrmDealAiSuggestion::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->find($suggestionId);

        if (! $suggestion instanceof CrmDealAiSuggestion) {
            abort(404);
        }

        return $suggestion;
    }
}
