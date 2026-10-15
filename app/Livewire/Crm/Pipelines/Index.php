<?php

namespace App\Livewire\Crm\Pipelines;

use App\Application\Crm\Deals\Actions\CreatePipeline;
use App\Application\Crm\Deals\Actions\DeletePipeline;
use App\Application\Crm\Deals\Actions\EnsureDefaultPipeline;
use App\Application\Crm\Deals\Actions\SetDefaultPipeline;
use App\Application\Crm\Deals\Actions\UpdatePipeline;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmPipeline;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('مدیریت مراحل کاریز')]
class Index extends Component
{
    use ResolvesCrmContext;

    public string $name = '';

    public string $description = '';

    public bool $showCreate = false;

    public function mount(): void
    {
        if ($this->crmPortal() !== 'employer') {
            abort(403);
        }
    }

    public function createDefault(EnsureDefaultPipeline $action): void
    {
        $pipeline = $action->handle($this->crmOrganizationId());
        $this->toast('پایپ‌لاین پیش‌فرض آماده است.');
        $this->redirect(route('employer.crm.pipelines.show', $pipeline), navigate: true);
    }

    public function createPipeline(CreatePipeline $action): void
    {
        $pipeline = $action->handle($this->crmOrganizationId(), $this->crmUser(), $this->name, $this->description);
        $this->reset(['name', 'description', 'showCreate']);
        $this->toast('پایپ‌لاین ساخته شد.');
        $this->redirect(route('employer.crm.pipelines.show', $pipeline), navigate: true);
    }

    public function makeDefault(int $pipelineId, SetDefaultPipeline $action): void
    {
        $action->handle($this->pipeline($pipelineId), $this->crmUser());
        $this->toast('پایپ‌لاین پیش‌فرض شد.');
    }

    public function saveActive(int $pipelineId, bool $isActive, UpdatePipeline $action): void
    {
        $pipeline = $this->pipeline($pipelineId);
        $action->handle($pipeline, $this->crmUser(), $pipeline->name, $pipeline->description, $isActive);
        $this->toast($isActive ? 'پایپ‌لاین فعال شد.' : 'پایپ‌لاین غیرفعال شد.');
    }

    public function deletePipeline(int $pipelineId, DeletePipeline $action): void
    {
        $action->handle($this->pipeline($pipelineId), $this->crmUser());
        $this->toast('پایپ‌لاین حذف شد.');
    }

    public function render()
    {
        return view('livewire.crm.pipelines.index', [
            'pipelines' => CrmPipeline::query()
                ->where('organization_id', $this->crmOrganizationId())
                ->withCount([
                    'stages',
                    'deals' => fn ($query) => $query->withTrashed(),
                ])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
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
