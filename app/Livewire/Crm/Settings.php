<?php

namespace App\Livewire\Crm;

use App\Domain\Crm\Deals\DealAutomationPolicy;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmPipeline;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('تنظیمات CRM')]
class Settings extends Component
{
    use ResolvesCrmContext;

    public function mount(): void
    {
        if ($this->crmPortal() !== 'employer') {
            abort(403);
        }
    }

    public function render()
    {
        $organizationId = $this->crmOrganizationId();
        $pipelines = CrmPipeline::query()
            ->where('organization_id', $organizationId)
            ->withCount('stages')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('livewire.crm.settings', [
            'pipelines' => $pipelines,
            'autoCreate' => DealAutomationPolicy::autoCreateFromAnalysis(),
        ])->layout($this->crmLayout());
    }
}
