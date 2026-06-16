@php
    use App\Support\AgentPerformancePresenter;
    use App\Support\AnalysisInsightPresenter;

    $employee = $profile['employee'];
    $metrics = $profile['metrics'];
    $deltas = $profile['metrics_delta'];
    $coaching = $profile['coaching'];
    $qualityTrend = $profile['quality_trend'];
    $dimensions = $profile['dimension_averages'] ?? [];

    $qualityChart = [
        'labels' => collect($qualityTrend)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'امتیاز مکالمه',
            'data' => collect($qualityTrend)->pluck('avg_score')->all(),
            'borderColor' => 'rgb(99, 102, 241)',
            'backgroundColor' => 'rgba(99, 102, 241, 0.12)',
            'fill' => true,
            'tension' => 0.35,
        ]],
    ];

    $volumeChart = [
        'labels' => collect($profile['volume_trend'])->pluck('label')->all(),
        'datasets' => [[
            'label' => 'تعداد تماس',
            'data' => collect($profile['volume_trend'])->pluck('count')->all(),
            'backgroundColor' => 'rgba(14, 165, 233, 0.75)',
            'borderRadius' => 6,
        ]],
    ];
@endphp

<div class="space-y-6" wire:loading.class="opacity-60">
    <a href="{{ route('employer.intelligence.performance') }}?preset={{ $datePreset }}&from={{ $customFrom }}&to={{ $customTo }}" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
        <svg class="h-4 w-4 rtl:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" /></svg>
        بازگشت به عملکرد کارشناسان
    </a>

    <section class="saas-hero">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-5">
                <x-saas.avatar :name="$employee['name']" :url="$employee['avatar_url'] ?? null" size="xl" ring />
                <div>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $employee['name'] }}</h1>
                    <p class="mt-1 text-zinc-500">{{ $employee['department'] ?? 'بدون بخش' }} · {{ $employee['position'] ?? 'کارشناس تماس' }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span @class(['rounded-md px-2.5 py-1 text-xs font-medium', AgentPerformancePresenter::trendBadgeClass($deltas['quality_trend'] ?? null)])>
                            کیفیت: {{ AgentPerformancePresenter::trendLabel($deltas['quality_trend'] ?? null) }}
                        </span>
                        <span @class(['rounded-md px-2.5 py-1 text-xs font-medium', AgentPerformancePresenter::trendBadgeClass($deltas['sentiment_trend'] ?? null)])>
                            رضایت: {{ AgentPerformancePresenter::trendLabel($deltas['sentiment_trend'] ?? null) }}
                        </span>
                        @if ($deltas['quality_improvement_percent'])
                            <span class="rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800">
                                {{ $deltas['quality_improvement_percent'] > 0 ? '+' : '' }}{{ $deltas['quality_improvement_percent'] }}% نسبت به دوره قبل
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <x-saas.score-ring :score="$metrics['average_quality_score']" size="lg" label="امتیاز کلی" />
                <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="export('csv')" class="saas-btn-secondary text-sm">CSV</button>
                <button type="button" wire:click="export('xlsx')" class="saas-btn-secondary text-sm">Excel</button>
                <button type="button" wire:click="export('pdf')" class="saas-btn-secondary text-sm">PDF</button>
                </div>
            </div>
        </div>
        <p class="mt-6 text-sm leading-7 text-zinc-600 dark:text-zinc-300">{{ $profile['executive_summary'] }}</p>
    </section>

    @include('livewire.employer.intelligence.partials.performance-filters')

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-saas.stat-card label="تماس‌های پاسخ‌داده" :value="$metrics['answered_calls']" :hint="$metrics['total_calls'].' کل'" />
        <x-saas.stat-card label="میانگین مدت مکالمه" :value="$metrics['average_duration_label']" />
        <x-saas.stat-card label="امتیاز لید فروش" :value="$metrics['average_lead_score'] ?: '—'" :trend="$deltas['lead_improvement_percent']" />
        <x-saas.stat-card label="رضایت مشتری" :value="$metrics['average_sentiment'] ? $metrics['average_sentiment'].'%' : '—'" :trend="$deltas['sentiment_improvement_percent']" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="saas-card">
            <h2 class="text-lg font-semibold">روند امتیاز مکالمه</h2>
            <div class="mt-4 h-56" wire:ignore>
                <canvas id="emp-quality-trend" data-report-chart data-type="line" data-config='@json($qualityChart)'></canvas>
            </div>
        </div>
        <div class="saas-card">
            <h2 class="text-lg font-semibold">حجم تماس‌ها</h2>
            <div class="mt-4 h-56" wire:ignore>
                <canvas id="emp-volume-trend" data-report-chart data-type="bar" data-config='@json($volumeChart)'></canvas>
            </div>
        </div>
    </div>

    @if (! empty($dimensions))
        <div class="saas-card">
            <h2 class="text-lg font-semibold">ابعاد عملکرد</h2>
            <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز هر بعد در بازه انتخاب‌شده</p>
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
                        <li class="text-zinc-500">پیشنهادی ثبت نشده</li>
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

    <section class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">آخرین مکالمات</h2>
                <p class="text-sm text-zinc-500">کلیک برای مشاهده تحلیل کامل</p>
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($profile['recent_calls'] as $call)
                <a href="{{ route('employer.intelligence.show', $call['analysis_id']) }}" class="block rounded-lg border border-zinc-200/80 bg-white p-4 transition hover:border-indigo-300 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-800">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-zinc-900 dark:text-white">{{ $call['customer'] }}</p>
                                <span class="text-xs text-zinc-400">{{ $call['date'] }}</span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $call['summary'] ?? '—' }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-3 text-sm">
                            <span class="rounded-lg bg-zinc-100 px-2.5 py-1 font-medium dark:bg-zinc-800">{{ $call['duration_label'] }}</span>
                            <span @class(['rounded-lg px-2.5 py-1 font-bold tabular-nums', AgentPerformancePresenter::scoreTextClass($call['quality_score'] ?? null)])>
                                {{ $call['quality_score'] ?? '—' }}
                            </span>
                            <span class="rounded-lg bg-indigo-50 px-2.5 py-1 font-medium text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                                لید {{ $call['lead_score'] ?? '—' }}
                            </span>
                            <span class="text-xs text-zinc-500">{{ $call['sentiment'] ?? '—' }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <x-saas.empty-state title="تماسی در این بازه نیست" />
            @endforelse
        </div>
    </section>
</div>
