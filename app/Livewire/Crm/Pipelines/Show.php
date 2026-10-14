<?php

namespace App\Livewire\Crm\Pipelines;

use App\Application\Crm\Deals\Actions\CreatePipelineStage;
use App\Application\Crm\Deals\Actions\DeletePipelineStage;
use App\Application\Crm\Deals\Actions\ReorderPipelineStages;
use App\Application\Crm\Deals\Actions\SetDefaultPipeline;
use App\Application\Crm\Deals\Actions\UpdatePipeline;
use App\Application\Crm\Deals\Actions\UpdatePipelineStage;
use App\Domain\Crm\Deals\PipelineStagePalette;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('مراحل پایپ‌لاین')]
class Show extends Component
{
    use ResolvesCrmContext;

    public int $pipelineId;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    public string $newStageName = '';

    public string $newStageColor = '#6366F1';

    public ?int $editingStageId = null;

    public string $stageName = '';

    public string $stageColor = '#6366F1';

    public bool $stageActive = true;

    public string $transferStageId = '';

    public bool $confirmDelete = false;

    public function mount(int $pipeline): void
    {
        if ($this->crmPortal() !== 'employer') {
            abort(403);
        }

        $this->pipelineId = $pipeline;
        $this->syncPipeline($this->pipeline());
    }

    public function savePipeline(UpdatePipeline $action): void
    {
        $this->syncPipeline($action->handle($this->pipeline(), $this->crmUser(), $this->name, $this->description, $this->isActive));
        $this->toast('پایپ‌لاین ذخیره شد.');
    }

    public function makeDefault(SetDefaultPipeline $action): void
    {
        $this->syncPipeline($action->handle($this->pipeline(), $this->crmUser()));
        $this->toast('این پایپ‌لاین پیش‌فرض شد.');
    }

    public function addStage(CreatePipelineStage $action): void
    {
        $action->handle($this->pipeline(), $this->crmUser(), $this->newStageName, $this->newStageColor);
        $this->reset(['newStageName']);
        $this->newStageColor = '#6366F1';
        $this->toast('مرحله اضافه شد.');
    }

    public function editStage(int $stageId): void
    {
        $stage = $this->stage($stageId);
        $this->editingStageId = (int) $stage->id;
        $this->stageName = $stage->name;
        $this->stageColor = $stage->color;
        $this->stageActive = (bool) $stage->is_active;
        $this->confirmDelete = false;
        $this->transferStageId = '';
    }

    public function saveStage(UpdatePipelineStage $action): void
    {
        if ($this->editingStageId === null) {
            return;
        }

        $action->handle($this->stage($this->editingStageId), $this->crmUser(), $this->stageName, $this->stageColor, $this->stageActive);
        $this->editingStageId = null;
        $this->toast('مرحله ذخیره شد.');
    }

    public function moveStage(int $stageId, string $direction, ReorderPipelineStages $action): void
    {
        $pipeline = $this->pipeline()->load('stages');
        $ids = $pipeline->stages->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $index = array_search($stageId, $ids, true);

        if ($index === false) {
            return;
        }

        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($ids[$swap])) {
            return;
        }

        [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
        $action->handle($pipeline, $this->crmUser(), $ids);
    }

    public function deleteStage(int $stageId, DeletePipelineStage $action): void
    {
        $transfer = $this->transferStageId === '' ? null : (int) $this->transferStageId;
        $action->handle($this->stage($stageId), $this->crmUser(), $transfer);
        $this->editingStageId = null;
        $this->confirmDelete = false;
        $this->toast('مرحله حذف شد.');
    }

    public function render()
    {
        $pipeline = $this->pipeline()->load('stages');
        $dealCounts = CrmDeal::withTrashed()
            ->where('crm_pipeline_id', $pipeline->id)
            ->selectRaw('crm_pipeline_stage_id, COUNT(*) as aggregate')
            ->groupBy('crm_pipeline_stage_id')
            ->pluck('aggregate', 'crm_pipeline_stage_id');

        return view('livewire.crm.pipelines.show', [
            'pipeline' => $pipeline,
            'colors' => PipelineStagePalette::ALLOWED,
            'dealCounts' => $dealCounts,
        ])->layout($this->crmLayout());
    }

    private function pipeline(): CrmPipeline
    {
        $pipeline = CrmPipeline::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->find($this->pipelineId);

        if (! $pipeline instanceof CrmPipeline) {
            abort(404);
        }

        return $pipeline;
    }

    private function stage(int $stageId): CrmPipelineStage
    {
        $stage = CrmPipelineStage::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->where('crm_pipeline_id', $this->pipelineId)
            ->find($stageId);

        if (! $stage instanceof CrmPipelineStage) {
            abort(404);
        }

        return $stage;
    }

    private function syncPipeline(CrmPipeline $pipeline): void
    {
        $this->name = $pipeline->name;
        $this->description = (string) $pipeline->description;
        $this->isActive = (bool) $pipeline->is_active;
    }
}
