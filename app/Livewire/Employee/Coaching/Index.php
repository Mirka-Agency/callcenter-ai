<?php

namespace App\Livewire\Employee\Coaching;

use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Models\ConversationAnalysis;
use App\Services\EmployeeContext;
use App\Services\EmployeeDashboardAnalytics;
use App\Services\Performance\Calculators\JsonFieldAggregator;
use App\Services\Performance\EmployeePerformanceAnalytics;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('مربیگری فروش')]
class Index extends Component
{
    #[Url(as: 'preset')]
    public string $period = 'last_30';

    public function setPeriod(string $preset): void
    {
        $this->period = $preset;
    }

    public function render()
    {
        $membership = EmployeeContext::membership()->load('user');
        $preset = ReportDatePreset::tryFrom($this->period) ?? ReportDatePreset::Last30;
        $filter = ReportFilter::make(
            organizationId: EmployeeContext::organizationId(),
            preset: $preset,
            employeeIds: [$membership->id],
        );

        $profile = app(EmployeePerformanceAnalytics::class)->employeeProfile($filter, $membership);

        $analyses = ConversationAnalysis::query()
            ->where('organization_user_id', $membership->id)
            ->whereBetween('analyzed_at', [$filter->from, $filter->to])
            ->latest('analyzed_at')
            ->get();

        $aggregator = app(JsonFieldAggregator::class);

        $strengthsRanked = $aggregator->rankedItems($analyses, 'strengths_json', 8);
        $improvementAreas = $aggregator->rankedImprovementAreas($analyses, 8);
        $weaknessesRanked = $improvementAreas['items'];
        $weaknessesDerived = $improvementAreas['derived'];
        $actionsRanked = $aggregator->rankedItems($analyses, 'next_actions_json', 8);

        $followUps = collect($analyses)
            ->take(15)
            ->flatMap(fn (ConversationAnalysis $analysis) => collect($analysis->next_actions_json ?? [])->map(fn ($action) => [
                'action' => is_string($action) ? $action : ($action['action'] ?? $action['title'] ?? 'پیگیری'),
                'analysis_id' => $analysis->id,
                'date' => $analysis->analyzed_at,
                'score' => $analysis->score,
            ]))
            ->take(10)
            ->values()
            ->all();

        $practiceCalls = collect($profile['recent_calls'] ?? [])
            ->filter(fn (array $call) => ($call['quality_score'] ?? 100) < 75)
            ->take(5)
            ->values()
            ->all();

        $recommendations = collect($weaknessesRanked)
            ->take(5)
            ->map(fn (array $row) => [
                'topic' => $row['item'],
                'occurrences' => $row['count'],
                'priority' => $row['count'] >= 3 ? 'high' : ($row['count'] >= 2 ? 'medium' : 'low'),
                'tip' => "روی «{$row['item']}» در مکالمات بعدی تمرکز کنید.",
            ])
            ->all();

        return view('livewire.employee.coaching.index', [
            'membership' => $membership,
            'profile' => $profile,
            'coaching' => $profile['coaching'],
            'metrics' => $profile['metrics'],
            'dimensions' => $profile['dimension_averages'] ?? [],
            'strengthsRanked' => $strengthsRanked,
            'weaknessesRanked' => $weaknessesRanked,
            'weaknessesDerived' => $weaknessesDerived,
            'actionsRanked' => $actionsRanked,
            'followUps' => $followUps,
            'practiceCalls' => $practiceCalls,
            'recommendations' => $recommendations,
            'analyzedCount' => $analyses->count(),
            'periodPresets' => [
                ReportDatePreset::Last7,
                ReportDatePreset::Last30,
                ReportDatePreset::ThisMonth,
                ReportDatePreset::CurrentQuarter,
            ],
            'activePreset' => $preset,
        ]);
    }
}
