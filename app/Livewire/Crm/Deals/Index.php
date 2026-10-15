<?php

namespace App\Livewire\Crm\Deals;

use App\Application\Crm\Deals\Actions\AcceptDealSuggestion;
use App\Application\Crm\Deals\Actions\ChangeDealStage;
use App\Application\Crm\Deals\Actions\CreateDeal;
use App\Application\Crm\Deals\Actions\CreatePipeline;
use App\Application\Crm\Deals\Actions\DeletePipeline;
use App\Application\Crm\Deals\Actions\DismissDealSuggestion;
use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealOverview;
use App\Application\Crm\Deals\DealQuery;
use App\Domain\Crm\Deals\DealSource;
use App\Enums\UserRole;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
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

    public string $pipelineName = '';

    public string $pipelineDescription = '';

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

    public function startCreateInStage(int $stageId): void
    {
        if (! ctype_digit($this->pipelineId)) {
            return;
        }

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
        $this->showCreate = true;
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

        $this->reset(['showCreate', 'title', 'amountRials', 'customerQuery', 'customerId', 'companyId', 'ownerId', 'expectedCloseOn', 'description']);
        $this->toast('فرصت ثبت شد.');
        $this->redirect(route($this->crmPortal().'.crm.deals.show', $deal), navigate: true);
    }

    public function createPipeline(CreatePipeline $action): void
    {
        $pipeline = $action->handle(
            $this->crmOrganizationId(),
            $this->crmUser(),
            $this->pipelineName,
            $this->pipelineDescription !== '' ? $this->pipelineDescription : null,
        );

        $this->reset(['showCreatePipeline', 'pipelineName', 'pipelineDescription']);
        $this->pipelineId = (string) $pipeline->id;
        $this->syncDefaultStage();
        $this->toast('کاریز ساخته شد.');
    }

    public function deleteCurrentPipeline(DeletePipeline $action): void
    {
        if (! ctype_digit($this->pipelineId)) {
            return;
        }

        $pipeline = CrmPipeline::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->find((int) $this->pipelineId);

        if (! $pipeline instanceof CrmPipeline) {
            abort(404);
        }

        $action->handle($pipeline, $this->crmUser());

        $next = CrmPipeline::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $this->pipelineId = $next ? (string) $next->id : '';
        $this->syncDefaultStage();
        $this->toast('کاریز حذف شد.');
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

        return view('livewire.crm.deals.index', [
            'portal' => $this->crmPortal(),
            'isManager' => $actor->role === UserRole::Employer,
            'pipelines' => $pipelines,
            'currentPipeline' => $currentPipeline,
            'stages' => $stages,
            'summary' => $overview->summarize($organizationId, $actor, $pipelineId),
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
