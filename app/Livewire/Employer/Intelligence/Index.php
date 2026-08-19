<?php

namespace App\Livewire\Employer\Intelligence;

use App\Application\Intelligence\Services\ReanalyzeConversationsService;
use App\Domain\Intelligence\Enums\ReanalyzeScope;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Enums\ReportDatePreset;
use App\Livewire\Employer\Intelligence\Concerns\HasAnalysisListFilters;
use App\Models\OrganizationUser;
use App\Services\AnalysisListQuery;
use App\Services\EmployerContext;
use Carbon\Carbon;
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

    public string $reanalyzeRangeMode = 'all';

    public ?string $reanalyzeFrom = null;

    public ?string $reanalyzeTo = null;

    public function reanalyzeConversations(string $scope): void
    {
        $resolved = ReanalyzeScope::tryFrom($scope);

        if (! $resolved) {
            return;
        }

        $from = null;
        $to = null;

        if ($this->reanalyzeRangeMode === 'range') {
            if (! $this->reanalyzeFrom || ! $this->reanalyzeTo) {
                session()->flash('error', __('ui.intelligence.reanalyze_dates_required'));

                return;
            }

            $from = Carbon::parse($this->reanalyzeFrom)->startOfDay();
            $to = Carbon::parse($this->reanalyzeTo)->endOfDay();

            if ($from->greaterThan($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }
        }

        $queued = app(ReanalyzeConversationsService::class)->queue(
            EmployerContext::organization(),
            $resolved,
            $from,
            $to,
        );

        $scopeLabel = $resolved->label();

        if ($from && $to) {
            $scopeLabel .= ' از '.shamsi($from, 'date').' تا '.shamsi($to, 'date');
        } else {
            $scopeLabel .= ' — '.__('ui.intelligence.reanalyze_all_dates');
        }

        session()->flash(
            'status',
            $queued > 0
                ? __('ui.intelligence.reanalyze_queued', ['count' => $queued, 'scope' => $scopeLabel])
                : __('ui.intelligence.reanalyze_empty', ['scope' => $scopeLabel]),
        );
    }

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
