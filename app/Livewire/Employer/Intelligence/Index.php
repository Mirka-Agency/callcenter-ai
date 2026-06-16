<?php

namespace App\Livewire\Employer\Intelligence;

use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Enums\ReportDatePreset;
use App\Livewire\Employer\Intelligence\Concerns\HasAnalysisListFilters;
use App\Models\OrganizationUser;
use App\Services\AnalysisListQuery;
use App\Services\EmployerContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.employer')]
#[Title('تحلیل تماس‌ها')]
class Index extends Component
{
    use HasAnalysisListFilters;
    use WithPagination;

    public function render()
    {
        $organizationId = EmployerContext::organizationId();
        $filter = $this->analysisListFilter();
        $query = app(AnalysisListQuery::class);

        $employees = OrganizationUser::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'department']);

        return view('livewire.employer.intelligence.index', [
            'analyses' => $query->paginate($filter),
            'overview' => $query->overview($filter),
            'charts' => $query->charts($filter),
            'filter' => $filter,
            'employees' => $employees,
            'primaryDatePresets' => [
                ReportDatePreset::Today,
                ReportDatePreset::Yesterday,
                ReportDatePreset::Last7,
                ReportDatePreset::Last30,
                ReportDatePreset::ThisMonth,
            ],
            'moreDatePresets' => [
                ReportDatePreset::PreviousMonth,
                ReportDatePreset::CurrentQuarter,
                ReportDatePreset::CurrentYear,
            ],
            'callStatuses' => CallStatus::cases(),
            'directions' => CallDirection::cases(),
        ]);
    }
}
