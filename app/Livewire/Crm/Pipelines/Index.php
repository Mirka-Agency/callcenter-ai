<?php

namespace App\Livewire\Crm\Pipelines;

use App\Application\Crm\Deals\Actions\DeletePipeline;
use App\Application\Crm\Deals\Actions\SetDefaultPipeline;
use App\Application\Crm\Deals\DealAuthorization;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmPipeline;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('همه کاریزها')]
class Index extends Component
{
    use ResolvesCrmContext;

    #[Url(except: '')]
    public string $search = '';

    public function mount(DealAuthorization $auth): void
    {
        if (! $auth->canManagePipelines($this->crmUser(), $this->crmOrganizationId())) {
            abort(403);
        }
    }

    public function setDefault(int $pipelineId, SetDefaultPipeline $action): void
    {
        $action->handle($this->pipeline($pipelineId), $this->crmUser());
        $this->toast('کاریز پیش‌فرض تنظیم شد.');
    }

    public function remove(int $pipelineId, DeletePipeline $action): void
    {
        $action->handle($this->pipeline($pipelineId), $this->crmUser());
        $this->toast('کاریز حذف شد.');
    }

    public function render()
    {
        $organizationId = $this->crmOrganizationId();
        $search = trim($this->search);

        $pipelines = CrmPipeline::query()
            ->where('organization_id', $organizationId)
            ->withCount(['stages', 'deals'])
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('livewire.crm.pipelines.index', [
            'portal' => $this->crmPortal(),
            'pipelines' => $pipelines,
            'dealsUrl' => route($this->crmPortal().'.crm.deals.index'),
            'createUrl' => route($this->crmPortal().'.crm.pipelines.create'),
        ])->layout($this->crmLayout());
    }

    private function pipeline(int $pipelineId): CrmPipeline
    {
        $pipeline = CrmPipeline::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->find($pipelineId);

        if (! $pipeline instanceof CrmPipeline) {
            abort(404);
        }

        return $pipeline;
    }
}
