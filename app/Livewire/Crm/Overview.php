<?php

namespace App\Livewire\Crm;

use App\Application\Crm\Deals\DealOverview;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmPipeline;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('نمای کلی فروش')]
class Overview extends Component
{
    use ResolvesCrmContext;

    #[Url(except: '')]
    public string $pipelineId = '';

    public function render(DealOverview $overview)
    {
        $organizationId = $this->crmOrganizationId();
        $pipelines = CrmPipeline::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($this->pipelineId === '' && $pipelines->isNotEmpty()) {
            $default = $pipelines->firstWhere('is_default', true) ?? $pipelines->first();
            $this->pipelineId = (string) $default->id;
        }

        $selected = ctype_digit($this->pipelineId) ? (int) $this->pipelineId : null;

        return view('livewire.crm.overview', [
            'summary' => $overview->summarize($organizationId, $this->crmUser(), $selected),
            'pipelines' => $pipelines,
            'portal' => $this->crmPortal(),
            'isManager' => $this->crmUser()->role->value === 'employer',
        ])->layout($this->crmLayout());
    }
}
