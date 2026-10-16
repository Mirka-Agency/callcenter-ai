<?php

namespace App\Livewire\Crm;

use App\Application\Crm\Analytics\CrmStatsOverview;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmPipeline;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('آمار و تحلیل CRM')]
class Overview extends Component
{
    use ResolvesCrmContext;

    #[Url(except: '')]
    public string $pipelineId = '';

    public function render(CrmStatsOverview $stats)
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
            'stats' => $stats->summarize($organizationId, $this->crmUser(), $selected),
            'portal' => $this->crmPortal(),
        ])->layout($this->crmLayout());
    }
}
