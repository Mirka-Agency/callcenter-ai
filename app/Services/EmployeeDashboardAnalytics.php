<?php

namespace App\Services;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\EmployeePerformanceSnapshot;
use App\Models\OrganizationUser;
use App\Services\Performance\Calculators\JsonFieldAggregator;
use App\Support\JalaliDate;
use Illuminate\Support\Collection;
use Carbon\CarbonInterface;

class EmployeeDashboardAnalytics
{
    public function __construct(
        private OrganizationUser $employee,
        private ?JsonFieldAggregator $jsonFieldAggregator = null,
    ) {}

    public static function forEmployee(OrganizationUser $employee): self
    {
        return new self($employee, app(JsonFieldAggregator::class));
    }

    public function cockpit(): array
    {
        $analyses = $this->analysisQuery()->get();
        $latest = $analyses->sortByDesc('analyzed_at')->first();

        $weekly = $this->periodAverage(now()->subWeek());
        $monthly = $this->periodAverage(now()->startOfMonth());
        $previousWeek = $this->periodAverage(now()->subWeeks(2), now()->subWeek());
        $previousMonth = $this->periodAverage(now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth());

        $callCount = Call::query()
            ->where('organization_user_id', $this->employee->id)
            ->count();

        $avgScore = round((float) $this->analysisQuery()->avg('score'), 1);

        return [
            'performance_score' => $latest?->score ?? $avgScore,
            'weekly_progress' => $weekly,
            'monthly_progress' => $monthly,
            'weekly_delta' => $weekly && $previousWeek ? round($weekly - $previousWeek, 1) : 0,
            'monthly_delta' => $monthly && $previousMonth ? round($monthly - $previousMonth, 1) : 0,
            'call_count' => $callCount,
            'analyzed_count' => $analyses->count(),
            'average_call_score' => $avgScore,
            'customer_satisfaction' => $this->sentimentScore(),
            'improvement_trend' => $this->improvementTrend(),
            'score_trend' => $this->scoreTrend(),
            'sentiment_trend' => $this->sentimentTrend(),
            'token_usage' => (int) $this->analysisQuery()->sum('total_tokens'),
        ];
    }

    public function achievements(): array
    {
        $cockpit = $this->cockpit();
        $badges = [];

        if ($cockpit['performance_score'] >= 90) {
            $badges[] = ['icon' => 'star', 'title' => 'عملکرد برتر', 'description' => 'امتیاز ۹۰+ در تحلیل‌های اخیر'];
        }
        if ($cockpit['weekly_delta'] > 5) {
            $badges[] = ['icon' => 'trend', 'title' => 'ستاره در حال رشد', 'description' => 'بیش از ۵ واحد بهبود در این هفته'];
        }
        if ($cockpit['analyzed_count'] >= 10) {
            $badges[] = ['icon' => 'calls', 'title' => 'حرفه‌ای مکالمه', 'description' => 'بیش از ۱۰ تماس تحلیل‌شده'];
        }
        if ($cockpit['customer_satisfaction'] >= 80) {
            $badges[] = ['icon' => 'heart', 'title' => 'قهرمان رضایت', 'description' => 'رضایت بالای مشتریان'];
        }
        if ($cockpit['monthly_delta'] > 0) {
            $badges[] = ['icon' => 'growth', 'title' => 'پیشرفت ماهانه', 'description' => 'رشد مثبت نسبت به ماه قبل'];
        }

        if (empty($badges)) {
            $badges[] = ['icon' => 'sparkles', 'title' => 'شروع مسیر', 'description' => 'با تحلیل تماس‌های بیشتر نشان‌ها را باز کنید'];
        }

        return $badges;
    }

    public function followUps(): array
    {
        return $this->analysisQuery()
            ->latest('analyzed_at')
            ->limit(15)
            ->get()
            ->flatMap(fn (ConversationAnalysis $a) => collect($a->next_actions_json ?? [])->map(fn ($action) => [
                'action' => is_string($action) ? $action : ($action['action'] ?? $action['title'] ?? 'Follow up'),
                'date' => JalaliDate::ago($a->analyzed_at),
                'call_id' => $a->call_id,
                'analysis_id' => $a->id,
            ]))
            ->take(8)
            ->values()
            ->all();
    }

    public function recommendations(): array
    {
        return collect($this->topImprovementAreas(5, 10)['items'])
            ->map(fn (array $row) => [
                'topic' => $row['item'],
                'priority' => $row['count'] >= 3 ? 'high' : ($row['count'] >= 2 ? 'medium' : 'low'),
                'occurrences' => $row['count'],
                'tip' => "روی «{$row['item']}» در مکالمات بعدی تمرکز کنید.",
            ])
            ->all();
    }

    /** @return list<array{item: string, count: int}> */
    public function topStrengths(int $limit = 5): array
    {
        return $this->aggregator()->rankedItems(
            $this->analysisQuery()->latest('analyzed_at')->limit(20)->get(),
            'strengths_json',
            $limit,
        );
    }

    /** @return array{items: list<array{item: string, count: int}>, derived: bool} */
    public function topImprovementAreas(int $limit = 5, int $analysisLimit = 50): array
    {
        return $this->aggregator()->rankedImprovementAreas(
            $this->analysisQuery()
                ->latest('analyzed_at')
                ->limit($analysisLimit)
                ->get([
                    'weaknesses_json',
                    'performance_dimensions_json',
                    'concerns_json',
                    'operational_insights_json',
                ]),
            $limit,
        );
    }

    public function recentCalls(): array
    {
        return $this->analysisQuery()
            ->with('call')
            ->latest('analyzed_at')
            ->limit(6)
            ->get()
            ->map(fn (ConversationAnalysis $a) => [
                'id' => $a->id,
                'score' => $a->score,
                'summary' => $a->summary,
                'sentiment' => $a->sentiment?->value,
                'date' => JalaliDate::ago($a->analyzed_at),
            ])
            ->all();
    }

    public function weeklySnapshot(): ?EmployeePerformanceSnapshot
    {
        return EmployeePerformanceSnapshot::query()
            ->where('organization_user_id', $this->employee->id)
            ->where('period', 'weekly')
            ->latest('period_end')
            ->first();
    }

    private function analysisQuery()
    {
        return ConversationAnalysis::query()
            ->where('organization_user_id', $this->employee->id);
    }

    private function aggregator(): JsonFieldAggregator
    {
        return $this->jsonFieldAggregator ??= app(JsonFieldAggregator::class);
    }

    private function periodAverage(?CarbonInterface $from = null, ?CarbonInterface $to = null): ?float
    {
        $query = $this->analysisQuery();

        if ($from) {
            $query->where('analyzed_at', '>=', $from);
        }
        if ($to) {
            $query->where('analyzed_at', '<=', $to);
        }

        $avg = $query->avg('score');

        return $avg ? round((float) $avg, 1) : null;
    }

    private function sentimentScore(): float
    {
        $weights = [
            AnalysisSentiment::Positive->value => 100,
            AnalysisSentiment::Mixed->value => 60,
            AnalysisSentiment::Neutral->value => 50,
            AnalysisSentiment::Negative->value => 20,
        ];

        $analyses = $this->analysisQuery()->get(['sentiment']);
        if ($analyses->isEmpty()) {
            return 0;
        }

        $total = $analyses->sum(fn ($a) => $weights[$a->sentiment->value] ?? 50);

        return round($total / $analyses->count(), 1);
    }

    private function improvementTrend(): array
    {
        return $this->scoreTrend(14);
    }

    private function scoreTrend(int $days = 30): array
    {
        return $this->dailyTrendSeries($days, function (Collection $items): array {
            if ($items->isEmpty()) {
                return ['avg_score' => null, 'count' => 0];
            }

            return [
                'avg_score' => round((float) $items->avg('score'), 1),
                'count' => $items->count(),
            ];
        });
    }

    private function sentimentTrend(int $days = 30): array
    {
        $weights = [
            AnalysisSentiment::Positive->value => 100,
            AnalysisSentiment::Mixed->value => 60,
            AnalysisSentiment::Neutral->value => 50,
            AnalysisSentiment::Negative->value => 20,
        ];

        return $this->dailyTrendSeries($days, function (Collection $items) use ($weights): array {
            if ($items->isEmpty()) {
                return ['satisfaction' => null, 'count' => 0];
            }

            return [
                'satisfaction' => round($items->avg(fn ($a) => $weights[$a->sentiment->value] ?? 50), 1),
                'count' => $items->count(),
            ];
        });
    }

    /**
     * @param  callable(Collection<int, ConversationAnalysis>): array<string, mixed>  $aggregator
     * @return list<array<string, mixed>>
     */
    private function dailyTrendSeries(int $days, callable $aggregator): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $grouped = $this->analysisQuery()
            ->where('analyzed_at', '>=', $from)
            ->orderBy('analyzed_at')
            ->get()
            ->groupBy(fn (ConversationAnalysis $analysis) => $analysis->analyzed_at->format('Y-m-d'));

        $series = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $from->copy()->addDays($offset);
            $period = $date->format('Y-m-d');
            $items = $grouped->get($period, collect());

            $series[] = array_merge([
                'period' => $period,
                'label' => JalaliDate::monthDay($period),
            ], $aggregator($items));
        }

        return $series;
    }
}
