<?php

namespace App\Livewire\Crm\Deals;

use App\Application\Crm\Deals\Actions\ArchiveDeal;
use App\Application\Crm\Deals\DealAuthorization;
use App\Application\Crm\Deals\DealQuery;
use App\Domain\Crm\Deals\DealStatus;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmPipeline;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('معاملات')]
class OpportunityList extends Component
{
    use ResolvesCrmContext;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    #[Url(except: '')]
    public string $pipelineId = '';

    #[Url(except: 'today_and_past')]
    public string $datePreset = 'today_and_past';

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    public bool $showFilters = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPipelineId(): void
    {
        $this->resetPage();
    }

    public function updatedDatePreset(): void
    {
        if (! in_array($this->datePreset, ['all', 'today_and_past', 'today', 'upcoming'], true)) {
            $this->datePreset = 'today_and_past';
        }

        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function closeFilters(): void
    {
        $this->showFilters = false;
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = 'all';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function refreshBoard(): void
    {
        $this->resetPage();
    }

    public function archiveDeal(int $dealId, ArchiveDeal $action, DealQuery $query): void
    {
        $deal = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $dealId);

        if ($deal->trashed()) {
            return;
        }

        $action->handle($deal, $this->crmUser());
        $this->toast('فرصت حذف شد.');
    }

    public function render(DealQuery $query, DealAuthorization $auth)
    {
        $organizationId = $this->crmOrganizationId();
        $actor = $this->crmUser();

        $filters = [
            'search' => $this->search,
            'status' => $this->normalizedStatus(),
            'pipeline_id' => ctype_digit($this->pipelineId) ? (int) $this->pipelineId : null,
            'date_preset' => $this->datePreset,
            'date_from' => $this->normalizedDate($this->dateFrom),
            'date_to' => $this->normalizedDate($this->dateTo),
            'sort' => 'activity',
            'direction' => 'desc',
        ];

        $pipelines = CrmPipeline::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('livewire.crm.deals.opportunity-list', [
            'portal' => $this->crmPortal(),
            'pipelines' => $pipelines,
            'currentPipeline' => $pipelines->firstWhere('id', ctype_digit($this->pipelineId) ? (int) $this->pipelineId : null),
            'statuses' => DealStatus::cases(),
            'deals' => $query->paginate($organizationId, $actor, $filters),
            'kanbanUrl' => route($this->crmPortal().'.crm.deals.index'),
            'listUrl' => route($this->crmPortal().'.crm.deals.list'),
            'pipelinesUrl' => route($this->crmPortal().'.crm.pipelines.index'),
            'canCreate' => $auth->canCreate($actor, $organizationId),
            'canManagePipelines' => $auth->canManagePipelines($actor, $organizationId),
            'showOwnerFilter' => false,
            'activeView' => 'list',
            'showFilters' => $this->showFilters,
            'datePreset' => $this->datePreset,
        ])->layout($this->crmLayout());
    }

    private function normalizedStatus(): string
    {
        if ($this->status === 'all') {
            return 'all';
        }

        return DealStatus::tryFrom($this->status)?->value ?? 'all';
    }

    private function normalizedDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }
}
