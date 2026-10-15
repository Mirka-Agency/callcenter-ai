<?php

namespace App\Livewire\Crm\Deals;

use App\Application\Crm\Deals\Actions\ArchiveDeal;
use App\Application\Crm\Deals\DealQuery;
use App\Domain\Crm\Deals\DealStatus;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmPipeline;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('فهرست فرصت‌ها')]
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

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

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

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
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

    public function render(DealQuery $query)
    {
        $organizationId = $this->crmOrganizationId();
        $actor = $this->crmUser();

        $filters = [
            'search' => $this->search,
            'status' => $this->normalizedStatus(),
            'pipeline_id' => ctype_digit($this->pipelineId) ? (int) $this->pipelineId : null,
            'date_from' => $this->normalizedDate($this->dateFrom),
            'date_to' => $this->normalizedDate($this->dateTo),
            'sort' => 'activity',
            'direction' => 'desc',
        ];

        return view('livewire.crm.deals.opportunity-list', [
            'portal' => $this->crmPortal(),
            'pipelines' => CrmPipeline::query()
                ->where('organization_id', $organizationId)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'statuses' => DealStatus::cases(),
            'deals' => $query->paginate($organizationId, $actor, $filters),
            'kanbanUrl' => route($this->crmPortal().'.crm.deals.index'),
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
