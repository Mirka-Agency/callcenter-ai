@php
    use App\Support\AgentPerformancePresenter;
    use App\Support\AnalysisInsightPresenter;

    $employee = $profile['employee'];
    $metrics = $profile['metrics'];
    $deltas = $profile['metrics_delta'];
    $coaching = $profile['coaching'];
    $qualityTrend = $profile['quality_trend'];
    $volumeTrend = $profile['volume_trend'];
    $sentimentTrend = $profile['sentiment_trend'] ?? [];
    $leadTrend = $profile['lead_trend'] ?? [];
    $dimensions = $profile['dimension_averages'] ?? [];

    $hasQualityTrend = collect($qualityTrend)->contains(fn (array $point) => ($point['avg_score'] ?? null) !== null);
    $hasVolumeTrend = collect($volumeTrend)->contains(fn (array $point) => ($point['count'] ?? 0) > 0);
    $sentimentScores = collect($sentimentTrend)->map(function (array $point) {
        $total = ($point['positive'] ?? 0) + ($point['neutral'] ?? 0) + ($point['negative'] ?? 0) + ($point['mixed'] ?? 0);
        if ($total === 0) {
            return null;
        }

        $weighted = ($point['positive'] ?? 0) * 100
            + ($point['mixed'] ?? 0) * 60
            + ($point['neutral'] ?? 0) * 50
            + ($point['negative'] ?? 0) * 20;

        return round($weighted / $total, 1);
    });
    $hasSentimentTrend = $sentimentScores->contains(fn ($score) => $score !== null);
    $hasLeadTrend = collect($leadTrend)->contains(fn (array $point) => ($point['avg_score'] ?? 0) > 0);
    $bestScore = collect($qualityTrend)->pluck('avg_score')->filter()->max();

    $chartOptions = [
        'plugins' => ['legend' => ['display' => false]],
        'scales' => [
            'x' => ['ticks' => ['maxTicksLimit' => 8, 'autoSkip' => true], 'grid' => ['display' => false]],
            'y' => ['min' => 0, 'max' => 100, 'ticks' => ['stepSize' => 25]],
        ],
    ];

    $qualityChart = [
        'labels' => collect($qualityTrend)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'امتیاز مکالمه',
            'data' => collect($qualityTrend)->pluck('avg_score')->all(),
            'borderColor' => 'rgb(99, 102, 241)',
            'backgroundColor' => 'rgba(99, 102, 241, 0.12)',
            'fill' => true,
            'tension' => 0.35,
            'spanGaps' => true,
            'pointRadius' => 3,
            'borderWidth' => 2,
        ]],
        'options' => $chartOptions,
    ];

    $volumeChart = [
        'labels' => collect($volumeTrend)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'تعداد تماس',
            'data' => collect($volumeTrend)->pluck('count')->all(),
            'backgroundColor' => 'rgba(14, 165, 233, 0.75)',
            'borderRadius' => 6,
        ]],
        'options' => ['plugins' => ['legend' => ['display' => false]]],
    ];

    $sentimentChart = [
        'labels' => collect($sentimentTrend)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'رضایت مشتری (%)',
            'data' => $sentimentScores->all(),
            'borderColor' => 'rgb(16, 185, 129)',
            'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
            'fill' => true,
            'tension' => 0.35,
            'spanGaps' => true,
            'pointRadius' => 3,
            'borderWidth' => 2,
        ]],
        'options' => $chartOptions,
    ];

    $leadChart = [
        'labels' => collect($leadTrend)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'امتیاز سرنخ',
            'data' => collect($leadTrend)->pluck('avg_score')->all(),
            'borderColor' => 'rgb(245, 158, 11)',
            'backgroundColor' => 'rgba(245, 158, 11, 0.12)',
            'fill' => true,
            'tension' => 0.35,
            'spanGaps' => true,
            'pointRadius' => 3,
            'borderWidth' => 2,
        ]],
        'options' => $chartOptions,
    ];

    $filterActionTargets = 'setPeriod';
    $analyzedInPeriod = (int) ($metrics['total_analyzed'] ?? 0);
@endphp

<div class="saas-page space-y-6">
    <x-saas.filter-loading-overlay :target="$filterActionTargets" />

    <section class="saas-hero saas-hero--accent" data-tour="performance-hero">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-5">
                <x-saas.avatar :employee="$membership" size="xl" ring />
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">عملکرد من</p>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $employee['name'] }}</h1>
                    <p class="mt-1 text-sm text-zinc-500">
                        {{ $employee['department'] ?? 'بدون بخش' }}
                        @if ($employee['position'] ?? null)
                            · {{ $employee['position'] }}
                        @endif
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span @class(['rounded-md px-2.5 py-1 text-xs font-medium', AgentPerformancePresenter::trendBadgeClass($deltas['quality_trend'] ?? null)])>
                            کیفیت: {{ AgentPerformancePresenter::trendLabel($deltas['quality_trend'] ?? null) }}
                        </span>
                        <span @class(['rounded-md px-2.5 py-1 text-xs font-medium', AgentPerformancePresenter::trendBadgeClass($deltas['sentiment_trend'] ?? null)])>
                            رضایت: {{ AgentPerformancePresenter::trendLabel($deltas['sentiment_trend'] ?? null) }}
                        </span>
                        @if ($deltas['quality_improvement_percent'] ?? null)
                            <span class="rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800">
                                {{ $deltas['quality_improvement_percent'] > 0 ? '+' : '' }}{{ $deltas['quality_improvement_percent'] }}٪ نسبت به دوره قبل
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <x-saas.score-ring :score="$metrics['average_quality_score']" size="lg" label="امتیاز کلی" />
                <div class="rounded-xl border border-indigo-200/70 bg-white/70 px-4 py-3 text-center shadow-sm dark:border-indigo-500/20 dark:bg-zinc-900/60">
                    <p class="text-xs text-zinc-500">تحلیل در بازه</p>
                    <p class="text-2xl font-bold tabular-nums text-indigo-600 dark:text-indigo-400">{{ $analyzedInPeriod }}</p>
                </div>
            </div>
        </div>
        @if ($profile['executive_summary'] ?? null)
            <p class="mt-6 text-sm leading-7 text-zinc-600 dark:text-zinc-300">{{ $profile['executive_summary'] }}</p>
        @endif
    </section>

    @include('livewire.employee.performance.partials.period-filters', [
        'periodPresets' => $periodPresets,
        'activePreset' => $activePreset,
    ])

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-tour="performance-stats">
        <x-saas.stat-card label="تماس‌های پاسخ‌داده" :value="$metrics['answered_calls'] ?? 0" :hint="($metrics['total_calls'] ?? 0).' کل'" />
        <x-saas.stat-card label="میانگین مدت مکالمه" :value="$metrics['average_duration_label'] ?? '—'" />
        <x-saas.stat-card label="امتیاز سرنخ فروش" :value="$metrics['average_lead_score'] ?: '—'" :trend="$deltas['lead_improvement_percent'] ?? null" />
        <x-saas.stat-card label="رضایت مشتری" :value="$metrics['average_sentiment'] ? $metrics['average_sentiment'].'%' : '—'" :trend="$deltas['sentiment_improvement_percent'] ?? null" />
        <x-saas.stat-card label="بهترین امتیاز" :value="$bestScore ?: '—'" />
        <x-saas.stat-card label="میانگین کیفیت" :value="$metrics['average_quality_score'] ?: '—'" :trend="$deltas['quality_improvement_percent'] ?? null" />
    </div>

    @if ($analyzedInPeriod === 0)
        <div class="saas-card">
            <x-saas.empty-state
                title="{{ __('ui.empty.no_analyses.title') }}"
                description="{{ __('ui.empty.no_analyses.description') }}"
            >
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('employee.calls') }}" class="saas-btn-secondary text-sm">تماس‌های من</a>
                    <a href="{{ route('employee.uploads') }}" class="saas-btn-primary text-sm">@lang('ui.cta.upload_first_call')</a>
                </div>
            </x-saas.empty-state>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-2" data-tour="performance-charts">
            <div class="saas-card">
                <h2 class="text-lg font-semibold">روند امتیاز مکالمه</h2>
                <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز در بازه انتخاب‌شده</p>
                @if ($hasQualityTrend)
                    <div class="mt-4 h-56" wire:key="employee-quality-{{ $period }}">
                        <canvas id="employee-performance-quality" data-report-chart data-type="line" data-config='@json($qualityChart)'></canvas>
                    </div>
                @else
                    <div class="mt-4">
                        <x-saas.empty-state
                            title="{{ __('ui.empty.chart_trend.title') }}"
                            description="{{ __('ui.empty.chart_trend.description') }}"
                        />
                    </div>
                @endif
            </div>

            <div class="saas-card">
                <h2 class="text-lg font-semibold">حجم تماس‌ها</h2>
                <p class="mt-1 text-sm text-zinc-500">تعداد تماس‌های تحلیل‌شده در بازه</p>
                @if ($hasVolumeTrend)
                    <div class="mt-4 h-56" wire:key="employee-volume-{{ $period }}">
                        <canvas id="employee-performance-volume" data-report-chart data-type="bar" data-config='@json($volumeChart)'></canvas>
                    </div>
                @else
                    <div class="mt-4">
                        <x-saas.empty-state
                            title="{{ __('ui.empty.chart_volume.title') }}"
                            description="{{ __('ui.empty.chart_volume.description') }}"
                        />
                    </div>
                @endif
            </div>

            <div class="saas-card">
                <h2 class="text-lg font-semibold">روند رضایت مشتری</h2>
                <p class="mt-1 text-sm text-zinc-500">شاخص احساسات مثبت در مکالمات</p>
                @if ($hasSentimentTrend)
                    <div class="mt-4 h-56" wire:key="employee-sentiment-{{ $period }}">
                        <canvas id="employee-performance-sentiment" data-report-chart data-type="line" data-config='@json($sentimentChart)'></canvas>
                    </div>
                @else
                    <div class="mt-4">
                        <x-saas.empty-state
                            title="{{ __('ui.empty.chart_satisfaction.title') }}"
                            description="{{ __('ui.empty.chart_satisfaction.description') }}"
                        />
                    </div>
                @endif
            </div>

            <div class="saas-card">
                <h2 class="text-lg font-semibold">روند کیفیت سرنخ</h2>
                <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز سرنخ در طول زمان</p>
                @if ($hasLeadTrend)
                    <div class="mt-4 h-56" wire:key="employee-lead-{{ $period }}">
                        <canvas id="employee-performance-lead" data-report-chart data-type="line" data-config='@json($leadChart)'></canvas>
                    </div>
                @else
                    <div class="mt-4">
                        <x-saas.empty-state
                            title="{{ __('ui.empty.chart_lead.title') }}"
                            description="{{ __('ui.empty.chart_lead.description') }}"
                        />
                    </div>
                @endif
            </div>
        </div>

        @if (! empty($dimensions))
            <div class="saas-card">
                <h2 class="text-lg font-semibold">ابعاد عملکرد</h2>
                <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز هر بعد — نقاط قوت و ضعف را سریع ببینید</p>
                <div class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($dimensions as $key => $score)
                        <x-saas.dimension-ring
                            :label="AnalysisInsightPresenter::dimensionLabel($key)"
                            :score="$score"
                        />
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <x-saas.analysis-insight-list title="نقاط قوت" :items="$profile['strengths']" tone="positive" />
            <x-saas.analysis-insight-list title="نقاط قابل بهبود" :items="$profile['weaknesses']" tone="warning" />
        </div>

        @if (! empty($coaching['training_areas']) || ! empty($coaching['coaching_plan']))
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="saas-card border border-indigo-200/60 bg-indigo-50/30 dark:border-indigo-900/40 dark:bg-indigo-950/20">
                    <h2 class="text-lg font-semibold">پیشنهادهای آموزشی</h2>
                    <ul class="mt-4 space-y-2 text-sm leading-relaxed">
                        @forelse ($coaching['training_areas'] as $area)
                            <li class="flex gap-2"><span class="text-indigo-500">•</span>{{ $area }}</li>
                        @empty
                            <li class="text-zinc-500">با تحلیل تماس‌های بیشتر، پیشنهادهای آموزشی اینجا ظاهر می‌شوند.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="saas-card">
                    <h2 class="text-lg font-semibold">برنامه مربیگری</h2>
                    <ol class="mt-4 list-decimal space-y-2 pr-5 text-sm leading-relaxed">
                        @foreach ($coaching['coaching_plan'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                </div>
            </div>
        @endif
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="saas-card lg:col-span-1">
            <h2 class="text-lg font-semibold">دستاوردهای من</h2>
            <div class="mt-4 space-y-3">
                @foreach ($achievements as $badge)
                    <div class="flex items-start gap-3 rounded-lg border border-amber-100 bg-amber-50/50 px-3 py-2.5 dark:border-amber-900/30 dark:bg-amber-950/20">
                        <span class="text-lg" aria-hidden="true">🏆</span>
                        <div>
                            <p class="text-sm font-medium">{{ $badge['title'] }}</p>
                            <p class="text-xs text-zinc-500">{{ $badge['description'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="saas-card lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">تمرکز مربیگری</h2>
                    <p class="mt-1 text-sm text-zinc-500">بر اساس ضعف‌های پرتکرار در مکالمات اخیر</p>
                </div>
                <a href="{{ route('employee.coaching') }}" class="shrink-0 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">جزئیات</a>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @forelse ($recommendations as $rec)
                    <div class="rounded-lg border border-zinc-200/80 p-4 dark:border-zinc-800">
                        <span @class([
                            'rounded-md px-2 py-0.5 text-xs font-medium',
                            'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $rec['priority'] === 'high',
                            'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' => $rec['priority'] === 'medium',
                            'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => $rec['priority'] === 'low',
                        ])>{{ match($rec['priority']) { 'high' => 'اولویت بالا', 'medium' => 'متوسط', 'low' => 'پایین', default => $rec['priority'] } }}</span>
                        <p class="mt-2 font-medium">{{ $rec['topic'] }}</p>
                        <p class="mt-1 text-sm text-zinc-500">{{ $rec['tip'] }}</p>
                    </div>
                @empty
                    <x-saas.empty-state
                        class="sm:col-span-2"
                        title="{{ __('ui.empty.no_recommendations.title') }}"
                        description="{{ __('ui.empty.no_recommendations.description') }}"
                    />
                @endforelse
            </div>
        </div>
    </div>

    <section class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold">آخرین مکالمات</h2>
                <p class="text-sm text-zinc-500">برای مشاهده تحلیل کامل روی هر مورد کلیک کنید</p>
            </div>
            <a href="{{ route('employee.calls') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">همه تماس‌ها</a>
        </div>

        <div class="space-y-3">
            @forelse ($profile['recent_calls'] as $call)
                <a
                    href="{{ route('employee.calls.show', $call['analysis_id']) }}"
                    class="block rounded-xl border border-zinc-200/80 bg-white p-4 transition hover:border-indigo-300 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-800"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-zinc-900 dark:text-white">{{ $call['customer'] }}</p>
                                <span class="text-xs text-zinc-400">{{ $call['date'] }}</span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $call['summary'] ?? '—' }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2 text-sm">
                            <span class="rounded-lg bg-zinc-100 px-2.5 py-1 font-medium dark:bg-zinc-800">{{ $call['duration_label'] }}</span>
                            <span @class(['rounded-lg px-2.5 py-1 font-bold tabular-nums', AgentPerformancePresenter::scoreTextClass($call['quality_score'] ?? null)])>
                                {{ $call['quality_score'] ?? '—' }}
                            </span>
                            <span class="rounded-lg bg-indigo-50 px-2.5 py-1 font-medium text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                                سرنخ {{ $call['lead_score'] ?? '—' }}
                            </span>
                            @if ($call['sentiment'] ?? null)
                                <span class="text-xs text-zinc-500">{{ $call['sentiment'] }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <x-saas.empty-state
                    title="{{ __('ui.empty.no_calls.title') }}"
                    description="{{ __('ui.empty.no_calls.description') }}"
                />
            @endforelse
        </div>
    </section>
</div>
