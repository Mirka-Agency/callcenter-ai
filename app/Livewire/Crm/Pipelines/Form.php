<?php

namespace App\Livewire\Crm\Pipelines;

use App\Application\Crm\Deals\Actions\SavePipelineBuilder;
use App\Application\Crm\Deals\DealAuthorization;
use App\Domain\Crm\Deals\PipelineStagePalette;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmPipeline;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('کاریز')]
class Form extends Component
{
    use ResolvesCrmContext;

    public ?CrmPipeline $pipeline = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    public bool $isDefault = false;

    public bool $showWinProbability = true;

    /** @var list<array{key: string, id: int|null, name: string, description: string, color: string, win_probability: string, is_won_stage: bool, is_lost_stage: bool, stale_enabled: bool}> */
    public array $stages = [];

    public function mount(DealAuthorization $auth, ?CrmPipeline $pipeline = null): void
    {
        if (! $auth->canManagePipelines($this->crmUser(), $this->crmOrganizationId())) {
            abort(403);
        }

        if ($pipeline instanceof CrmPipeline) {
            if ((int) $pipeline->organization_id !== $this->crmOrganizationId()) {
                abort(404);
            }

            $this->pipeline = $pipeline->load(['stages' => fn ($q) => $q->orderBy('position')]);
            $this->name = (string) $pipeline->name;
            $this->description = (string) ($pipeline->description ?? '');
            $this->isActive = (bool) $pipeline->is_active;
            $this->isDefault = (bool) $pipeline->is_default;
            $this->showWinProbability = (bool) $pipeline->show_win_probability;
            $this->stages = $pipeline->stages->values()->map(fn ($stage) => [
                'key' => 'stage-'.$stage->id,
                'id' => (int) $stage->id,
                'name' => (string) $stage->name,
                'description' => (string) ($stage->description ?? ''),
                'color' => (string) $stage->color,
                'win_probability' => $stage->win_probability !== null ? (string) $stage->win_probability : '',
                'is_won_stage' => (bool) $stage->is_won_stage,
                'is_lost_stage' => (bool) $stage->is_lost_stage,
                'stale_enabled' => $stage->stale_after_days !== null,
            ])->all();
        } else {
            $this->name = 'کاریز فروش';
            $this->stages = $this->defaultDraftStages();
            $hasAny = CrmPipeline::query()
                ->where('organization_id', $this->crmOrganizationId())
                ->exists();
            $this->isDefault = ! $hasAny;
        }
    }

    public function addStage(): void
    {
        $this->stages[] = [
            'key' => 'new-'.uniqid(),
            'id' => null,
            'name' => 'مرحله جدید',
            'description' => '',
            'color' => PipelineStagePalette::colorForPosition(count($this->stages) + 1),
            'win_probability' => '',
            'is_won_stage' => false,
            'is_lost_stage' => false,
            'stale_enabled' => false,
        ];
    }

    public function removeStage(int $index): void
    {
        if (! isset($this->stages[$index])) {
            return;
        }

        if (count($this->stages) <= 1) {
            $this->addError('stages', 'حداقل یک مرحله باید باقی بماند.');

            return;
        }

        unset($this->stages[$index]);
        $this->stages = array_values($this->stages);
    }

    public function reorderStages(array $orderedKeys): void
    {
        $byKey = collect($this->stages)->keyBy('key');
        $reordered = [];

        foreach ($orderedKeys as $key) {
            if ($byKey->has($key)) {
                $reordered[] = $byKey[$key];
            }
        }

        foreach ($byKey as $key => $stage) {
            if (! in_array($key, $orderedKeys, true)) {
                $reordered[] = $stage;
            }
        }

        $this->stages = array_values($reordered);
    }

    public function moveStage(string $fromKey, string $toKey): void
    {
        if ($fromKey === '' || $toKey === '' || $fromKey === $toKey) {
            return;
        }

        $keys = collect($this->stages)->pluck('key')->values()->all();
        $from = array_search($fromKey, $keys, true);
        $to = array_search($toKey, $keys, true);

        if ($from === false || $to === false) {
            return;
        }

        $moved = array_splice($keys, (int) $from, 1);
        array_splice($keys, (int) $to, 0, $moved);
        $this->reorderStages($keys);
    }

    public function save(SavePipelineBuilder $action): void
    {
        $action->handle(
            $this->crmOrganizationId(),
            $this->crmUser(),
            $this->pipeline?->id,
            $this->name,
            $this->description !== '' ? $this->description : null,
            $this->isActive,
            $this->isDefault,
            $this->showWinProbability,
            collect($this->stages)->values()->map(fn (array $stage, int $index) => [
                'id' => $stage['id'],
                'name' => $stage['name'],
                'description' => $stage['description'],
                'color' => PipelineStagePalette::colorForPosition($index + 1),
                'win_probability' => $stage['win_probability'] !== '' ? (int) $stage['win_probability'] : null,
                'is_won_stage' => $stage['is_won_stage'],
                'is_lost_stage' => $stage['is_lost_stage'],
                'stale_enabled' => $stage['stale_enabled'],
            ])->all(),
        );

        $this->toast($this->pipeline ? 'کاریز به‌روزرسانی شد.' : 'کاریز ساخته شد.');
        $this->redirect(route($this->crmPortal().'.crm.pipelines.index'), navigate: true);
    }

    public function cancel(): void
    {
        $this->redirect(route($this->crmPortal().'.crm.pipelines.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.crm.pipelines.form', [
            'portal' => $this->crmPortal(),
            'isEditing' => $this->pipeline instanceof CrmPipeline,
            'pipelinesUrl' => route($this->crmPortal().'.crm.pipelines.index'),
            'dealsUrl' => route($this->crmPortal().'.crm.deals.index'),
        ])->layout($this->crmLayout());
    }

    /** @return list<array{key: string, id: int|null, name: string, description: string, color: string, win_probability: string, is_won_stage: bool, is_lost_stage: bool, stale_enabled: bool}> */
    private function defaultDraftStages(): array
    {
        $defaults = [
            ['name' => 'مخاطب اولیه', 'win_probability' => '0'],
            ['name' => 'نیازسنجی', 'win_probability' => '20'],
            ['name' => 'ارائه پیشنهاد', 'win_probability' => '50'],
            ['name' => 'مذاکره', 'win_probability' => '80'],
            ['name' => 'نهایی‌سازی', 'win_probability' => '90'],
            ['name' => 'برد', 'win_probability' => '100', 'is_won_stage' => true],
        ];

        return collect($defaults)->map(fn (array $stage, int $index) => [
            'key' => 'draft-'.$index,
            'id' => null,
            'name' => $stage['name'],
            'description' => '',
            'color' => PipelineStagePalette::colorForPosition($index + 1),
            'win_probability' => $stage['win_probability'],
            'is_won_stage' => (bool) ($stage['is_won_stage'] ?? false),
            'is_lost_stage' => false,
            'stale_enabled' => false,
        ])->all();
    }
}
