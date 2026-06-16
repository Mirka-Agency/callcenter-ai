<?php

namespace App\Livewire\Employee\Calls;

use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Enums\ReportDatePreset;
use App\Livewire\Employee\Calls\Concerns\HasEmployeeCallListFilters;
use App\Services\AnalysisListQuery;
use App\Services\EmployeeContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.employee')]
#[Title('تماس‌های من')]
class Index extends Component
{
    use HasEmployeeCallListFilters;
    use WithPagination;

    public function mount(): void
    {
        $this->mountCallListFilters();
    }

    public function render()
    {
        $filter = $this->employeeCallListFilter();
        $query = app(AnalysisListQuery::class);

        return view('livewire.employee.calls.index', [
            'membership' => EmployeeContext::membership()->load('user'),
            'analyses' => $query->paginate($filter, 15),
            'overview' => $query->overview($filter),
            'charts' => $query->charts($filter),
            'filter' => $filter,
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
