<?php

namespace App\Services;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\EmployeePerformanceSnapshot;
use App\Models\OrganizationUser;
use App\Services\Performance\Calculators\JsonFieldAggregator;
use App\Support\CompanyWorkCalendar;
use App\Support\JalaliDate;
use App\Support\OrganizationHolidays;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

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

    private ?Collection $cachedWorkdayAnalyses = null;

    /** @var list<int>|null */
    private ?array $resolvedHolidayWeekdays = null;

    public function cockpit(): array
    {
        $analyses = $this->workdayAnalyses();

        $weekly = $this->periodAverage(now()->subWeek());
        $monthly = $this->periodAverage(now()->startOfMonth());
        $previousWeek = $this->periodAverage(now()->subWeeks(2), now()->subWeek());
        $previousMonth = $this->periodAverage(now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth());

        $callCount = Call::query()
            ->where('organization_user_id', $this->employee->id)
            ->count();

        $evaluable = $analyses->filter(fn (ConversationAnalysis $analysis) => $analysis->isEvaluable());
        $avgScore = $evaluable->isNotEmpty() ? round((float) $evaluable->avg('score'), 1) : 0.0;
        $latestEvaluable = $evaluable->sortByDesc('analyzed_at')->first();

        return [
            'performance_score' => $latestEvaluable?->score ?? $avgScore,
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
            $badges[] = ['icon' => 'sparkles', 'title' => 'در مسیر رشد', 'description' => 'با تحلیل تماس‌های بیشتر، نشان‌های عملکرد را باز کنید'];
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
            $this->workdayAnalyses()->sortByDesc('analyzed_at')->take(20),
            'strengths_json',
            $limit,
        );
    }

    /** @return array{items: list<array{item: string, count: int}>, derived: bool} */
    public function topImprovementAreas(int $limit = 5, int $analysisLimit = 50): array
    {
        return $this->aggregator()->rankedImprovementAreas(
            $this->workdayAnalyses()
                ->sortByDesc('analyzed_at')
                ->take($analysisLimit),
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

    /** @return Collection<int, ConversationAnalysis> */
    private function workdayAnalyses(): Collection
    {
        return $this->cachedWorkdayAnalyses ??= $this->analysisQuery()
            ->with('call:id,conversation_date,started_at,created_at')
            ->get()
            ->reject(function (ConversationAnalysis $analysis): bool {
                $at = $analysis->occurredAt() ?? $analysis->analyzed_at;

                return $at instanceof CarbonInterface && CompanyWorkCalendar::isHolidayMoment($at, $this->holidayWeekdays());
            })
            ->values();
    }

    private function aggregator(): JsonFieldAggregator
    {
        return $this->jsonFieldAggregator ??= app(JsonFieldAggregator::class);
    }

    private function periodAverage(?CarbonInterface $from = null, ?CarbonInterface $to = null): ?float
    {
        $scores = $this->workdayAnalyses()->filter(function (ConversationAnalysis $analysis) use ($from, $to): bool {
            if (! $analysis->isEvaluable() || $analysis->analyzed_at === null) {
                return false;
            }

            if ($from && $analysis->analyzed_at->lt($from)) {
                return false;
            }

            if ($to && $analysis->analyzed_at->gt($to)) {
                return false;
            }

            return true;
        });

        if ($scores->isEmpty()) {
            return null;
        }

        return round((float) $scores->avg('score'), 1);
    }

    private function sentimentScore(): float
    {
        $weights = [
            AnalysisSentiment::Positive->value => 100,
            AnalysisSentiment::Mixed->value => 60,
            AnalysisSentiment::Neutral->value => 50,
            AnalysisSentiment::Negative->value => 20,
        ];

        $analyses = $this->workdayAnalyses();
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
        $from = now(CompanyWorkCalendar::TIMEZONE)->subDays($days - 1)->startOfDay();

        $grouped = $this->analysisQuery()
            ->with('call:id,conversation_date,started_at,created_at')
            ->where('analyzed_at', '>=', $from->copy()->utc())
            ->orderBy('analyzed_at')
            ->get()
            ->groupBy(function (ConversationAnalysis $analysis) {
                $occurredAt = $analysis->call?->occurredAt() ?? $analysis->analyzed_at;

                return CompanyWorkCalendar::dayKey($occurredAt);
            });

        $series = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $from->copy()->addDays($offset);
            $period = $date->toDateString();

            if (CompanyWorkCalendar::isHoliday($period, $this->holidayWeekdays())) {
                continue;
            }

            $items = $grouped->get($period, collect());

            $series[] = array_merge([
                'period' => $period,
                'label' => JalaliDate::monthDay($period),
            ], $aggregator($items));
        }

        return $series;
    }

    /** @return list<int> */
    private function holidayWeekdays(): array
    {
        return $this->resolvedHolidayWeekdays ??= OrganizationHolidays::weekdays($this->employee->organization_id);
    }
}
