<?php

namespace App\Livewire\Employer\Intelligence;

use App\Application\Intelligence\Services\ReanalyzeConversationsService;
use App\Domain\Intelligence\Enums\ReanalyzeScope;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Livewire\Employer\Intelligence\Concerns\HasAnalysisListFilters;
use App\Models\OrganizationUser;
use App\Services\AnalysisListQuery;
use App\Services\EmployerContext;
use App\Support\CustomerPresenter;
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

    public ?string $selectedConcern = null;

    public ?string $selectedSentiment = null;

    public function drilldown(string $dimension, string $value): void
    {
        if ($dimension !== 'concern') {
            return;
        }

        $this->selectConcern($value);
    }

    public function selectConcern(string $type): void
    {
        $type = strtolower(trim($type));

        if ($type === '' || mb_strlen($type) > 32) {
            return;
        }

        $this->selectedConcern = $this->selectedConcern === $type ? null : $type;
    }

    public function clearConcern(): void
    {
        $this->selectedConcern = null;
    }

    public function selectNegativeSentiment(string $sentiment): void
    {
        if (strtolower(trim($sentiment)) !== AnalysisSentiment::Negative->value) {
            return;
        }

        $this->selectedSentiment = $this->selectedSentiment === AnalysisSentiment::Negative->value
            ? null
            : AnalysisSentiment::Negative->value;
    }

    public function clearSentiment(): void
    {
        $this->selectedSentiment = null;
    }

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

        $charts = $query->charts($filter);
        $selectedConcern = $this->resolvedConcern($charts['concerns']);
        $concernCallList = $selectedConcern
            ? $query->callsForConcern($filter, $selectedConcern)
            : ['total' => 0, 'calls' => []];
        $selectedSentiment = $this->resolvedSentiment($charts['sentiment_breakdown']);
        $sentimentCallList = $selectedSentiment
            ? $query->callsForSentiment($filter, $selectedSentiment)
            : ['total' => 0, 'calls' => []];

        return view('livewire.employer.intelligence.index', [
            'analyses' => $query->paginate($filter),
            'overview' => $query->overview($filter),
            'charts' => $charts,
            'filter' => $filter,
            'employees' => $employees,
            'callStatuses' => CallStatus::cases(),
            'directions' => CallDirection::cases(),
            'selectedConcern' => $selectedConcern,
            'selectedConcernLabel' => $selectedConcern ? CustomerPresenter::concernLabel($selectedConcern) : null,
            'concernCalls' => $concernCallList['calls'],
            'concernCallTotal' => $concernCallList['total'],
            'selectedSentiment' => $selectedSentiment,
            'sentimentCalls' => $sentimentCallList['calls'],
            'sentimentCallTotal' => $sentimentCallList['total'],
        ]);
    }

    /**
     * @param  list<array{type: string, label: string, count: int}>  $concerns
     */
    private function resolvedConcern(array $concerns): ?string
    {
        if ($this->selectedConcern === null) {
            return null;
        }

        $allowed = collect($concerns)->pluck('type')->all();

        if (! in_array($this->selectedConcern, $allowed, true)) {
            $this->selectedConcern = null;

            return null;
        }

        return $this->selectedConcern;
    }

    /**
     * @param  list<array{key: string, label: string, count: int}>  $sentimentBreakdown
     */
    private function resolvedSentiment(array $sentimentBreakdown): ?string
    {
        if ($this->selectedSentiment !== AnalysisSentiment::Negative->value) {
            $this->selectedSentiment = null;

            return null;
        }

        $allowed = collect($sentimentBreakdown)->pluck('key')->all();

        if (! in_array(AnalysisSentiment::Negative->value, $allowed, true)) {
            $this->selectedSentiment = null;

            return null;
        }

        return AnalysisSentiment::Negative->value;
    }
}
