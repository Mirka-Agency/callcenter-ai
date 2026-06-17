@php
    use App\Support\AgentPerformancePresenter;

    $kpis = $dashboard['kpis'];
    $deltas = $dashboard['kpis_delta'];
    $employees = $dashboard['employees'];
    $qualityTrend = $dashboard['quality_trend'];

    $topCount = collect($employees)->where('tier', 'top')->count();
    $attentionCount = collect($employees)->where('tier', 'attention')->count();

    $qualityChart = [
        'labels' => collect($qualityTrend)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'امتیاز کیفیت مکالمه',
            'data' => collect($qualityTrend)->pluck('avg_score')->all(),
            'borderColor' => 'rgb(16, 185, 129)',
            'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
            'fill' => true,
            'tension' => 0.35,
        ]],
    ];

    $employeeChart = [
        'labels' => collect($employees)->pluck('name')->all(),
        'datasets' => [
            ['label' => 'امتیاز مکالمه', 'data' => collect($employees)->pluck('average_score')->all(), 'backgroundColor' => 'rgba(99, 102, 241, 0.85)', 'borderRadius' => 6],
            ['label' => 'امتیاز لید', 'data' => collect($employees)->pluck('average_lead_score')->all(), 'backgroundColor' => 'rgba(16, 185, 129, 0.75)', 'borderRadius' => 6],
        ],
    ];

    $profileUrl = fn (array $agent) => route('employer.intelligence.performance.show', $agent['id']).'?preset='.$datePreset.'&from='.$customFrom.'&to='.$customTo;

    $filterLoadingTargets = 'datePreset,customFrom,customTo,applyCustomDateRange,selectedEmployeeIds,setDatePreset,closeCustomDateRangePanel,clearDateFilter,clearFilters,clearEmployeeFilter';
@endphp

<div class="saas-page space-y-6">
    <x-saas.filter-loading-overlay :target="$filterLoadingTargets" />

    <x-saas.page-header
        data-tour="page-header"
        title="عملکرد کارشناسان"
        description="مقایسه، رتبه‌بندی و شناسایی فرصت‌های مربیگری تیم تماس."
    >
        <x-slot:actions>
            <button type="button" wire:click="export('csv')" class="saas-btn-secondary text-sm">CSV</button>
            <button type="button" wire:click="export('xlsx')" class="saas-btn-secondary text-sm">Excel</button>
            <button type="button" wire:click="export('pdf')" class="saas-btn-secondary text-sm">PDF</button>
        </x-slot:actions>
    </x-saas.page-header>

    @include('livewire.employer.intelligence.partials.performance-filters', [
        'primaryDatePresets' => $primaryDatePresets,
        'moreDatePresets' => $moreDatePresets,
        'filterEmployees' => $filterEmployees,
    ])

    <div class="space-y-6" wire:loading.class="opacity-60" wire:target="{{ $filterLoadingTargets }}">
    <section class="saas-hero saas-hero--accent" data-tour="performance-summary">
        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">جمع‌بندی مدیریتی</p>
        <p class="mt-3 text-base leading-8 text-zinc-700 dark:text-zinc-200">{{ $dashboard['executive_summary'] }}</p>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-saas.stat-card label="کارشناسان فعال" :value="$kpis['active_employees']" />
        <x-saas.stat-card label="تماس‌های تحلیل‌شده" :value="$kpis['total_analyzed']" :trend="$deltas['total_analyzed']" />
        <x-saas.stat-card label="میانگین امتیاز مکالمه" :value="$kpis['average_quality_score'] ?: '—'" :trend="$deltas['average_quality_score']" />
        <x-saas.stat-card label="میانگین رضایت مشتری" :value="$kpis['average_sentiment'] ? $kpis['average_sentiment'].'%' : '—'" :trend="$deltas['average_sentiment']" />
    </div>

    <section class="space-y-4" data-tour="performance-cards" x-data="{ filter: 'all' }">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold">کارت‌های عملکرد</h2>
                <p class="text-sm text-zinc-500">برای مشاهده جزئیات، روی هر کارشناس کلیک کنید.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600'" class="rounded-lg px-3 py-1.5 text-sm font-medium">همه</button>
                <button type="button" @click="filter = 'top'" :class="filter === 'top' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700'" class="rounded-lg px-3 py-1.5 text-sm font-medium">برترین‌ها ({{ $topCount }})</button>
                <button type="button" @click="filter = 'attention'" :class="filter === 'attention' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-700'" class="rounded-lg px-3 py-1.5 text-sm font-medium">نیازمند توجه ({{ $attentionCount }})</button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @forelse ($employees as $agent)
                <div x-show="filter === 'all' || filter === '{{ $agent['tier'] }}'" x-cloak>
                    <x-saas.agent-performance-card :agent="$agent" :href="$profileUrl($agent)" />
                </div>
            @empty
                <div class="col-span-full">
                    <x-saas.empty-state
                        title="{{ __('ui.empty.no_activity.title') }}"
                        description="{{ __('ui.empty.no_activity.description') }}"
                    />
                </div>
            @endforelse
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2" data-tour="performance-charts">
        <div class="saas-card">
            <h2 class="text-lg font-semibold">روند کیفیت مکالمه</h2>
            <div class="mt-4 h-64" wire:ignore>
                <canvas id="perf-quality-trend" data-report-chart data-type="line" data-config='@json($qualityChart)'></canvas>
            </div>
        </div>
        <div class="saas-card">
            <h2 class="text-lg font-semibold">مقایسه امتیاز کارشناسان</h2>
            <div class="mt-4 h-64" wire:ignore>
                <canvas id="perf-employee-compare" data-report-chart data-type="bar" data-config='@json(array_merge($employeeChart, ["options" => ["plugins" => ["legend" => ["position" => "bottom"]]]]))'></canvas>
            </div>
        </div>
    </div>

    @if (! empty($dashboard['team_weaknesses']))
        <div class="saas-card">
            <h2 class="text-lg font-semibold">ضعف‌های پرتکرار تیم</h2>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach (array_slice($dashboard['team_weaknesses'], 0, 8) as $weakness)
                    <span class="rounded-md bg-red-50 px-3 py-1 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300">{{ $weakness['item'] }} ({{ $weakness['count'] }})</span>
                @endforeach
            </div>
        </div>
    @endif

    @php
        $rankingMeta = [
            'best_quality' => [
                'title' => 'بالاترین امتیاز',
                'subtitle' => 'کیفیت مکالمه',
                'accent' => 'indigo',
                'icon' => 'M12 3l2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.8 6.5 20l1-6.2L3 9.4l6.2-.9L12 3z',
            ],
            'best_lead' => [
                'title' => 'بهترین لید',
                'subtitle' => 'میانگین امتیاز لید',
                'accent' => 'emerald',
                'icon' => 'M3 17l6-6 4 4 8-8M14 7h7v7',
            ],
            'most_improved' => [
                'title' => 'بیشترین پیشرفت',
                'subtitle' => 'رشد دوره اخیر',
                'accent' => 'amber',
                'icon' => 'M4 14l4-4 3 3 5-6 4 4M20 8V4h-4',
            ],
            'most_calls' => [
                'title' => 'بیشترین تماس',
                'subtitle' => 'حجم فعالیت',
                'accent' => 'sky',
                'icon' => 'M5 7a2 2 0 0 1 2-2h2l2 4-1.5 1.5a12 12 0 0 0 5 5L16 14l4 2v2a2 2 0 0 1-2 2h-1C10.4 20 4 13.6 4 6V5z',
            ],
            'best_sentiment' => [
                'title' => 'بالاترین رضایت',
                'subtitle' => 'احساس مثبت مشتری',
                'accent' => 'rose',
                'icon' => 'M12 21s-7-4.4-7-10a4 4 0 0 1 7-2 4 4 0 0 1 7 2c0 5.6-7 10-7 10z',
            ],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2" data-tour="performance-rankings">
        @foreach ($rankingMeta as $key => $meta)
            @php
                $accentClasses = match ($meta['accent']) {
                    'emerald' => 'from-emerald-500/10 to-emerald-100/30 border-emerald-200/70 dark:from-emerald-500/15 dark:to-emerald-950/20 dark:border-emerald-500/20',
                    'amber' => 'from-amber-500/10 to-amber-100/30 border-amber-200/70 dark:from-amber-500/15 dark:to-amber-950/20 dark:border-amber-500/20',
                    'sky' => 'from-sky-500/10 to-sky-100/30 border-sky-200/70 dark:from-sky-500/15 dark:to-sky-950/20 dark:border-sky-500/20',
                    'rose' => 'from-rose-500/10 to-rose-100/30 border-rose-200/70 dark:from-rose-500/15 dark:to-rose-950/20 dark:border-rose-500/20',
                    default => 'from-indigo-500/10 to-indigo-100/30 border-indigo-200/70 dark:from-indigo-500/15 dark:to-indigo-950/20 dark:border-indigo-500/20',
                };
            @endphp
            <div @class([
                'saas-widget border bg-gradient-to-b',
                $accentClasses,
            ])>
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/80 text-zinc-700 shadow-sm dark:bg-zinc-900/70 dark:text-zinc-200">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}" />
                        </svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $meta['title'] }}</h3>
                        <p class="mt-0.5 text-[11px] text-zinc-500">{{ $meta['subtitle'] }}</p>
                    </div>
                </div>

                <ol class="mt-3 space-y-1.5">
                    @forelse (array_slice($dashboard['rankings'][$key] ?? [], 0, 3) as $i => $row)
                        <li>
                            @php
                                $metricValue = match ($key) {
                                    'most_calls' => $row['total_calls'],
                                    'most_improved' => (($row['improvement_percent'] ?? 0) > 0 ? '+' : '').($row['improvement_percent'] ?? 0).'%',
                                    'best_sentiment' => $row['average_sentiment'] ?? '—',
                                    'best_lead' => $row['average_lead_score'] ?? '—',
                                    default => $row['average_score'] ?? '—',
                                };
                            @endphp
                            <x-saas.agent-rank-row
                                :row="$row"
                                :rank="$i + 1"
                                :href="$profileUrl($row)"
                                :value="$metricValue"
                            />
                        </li>
                    @empty
                        <li class="rounded-lg border border-dashed border-zinc-200 px-3 py-4 text-center text-xs text-zinc-500 dark:border-zinc-700">
                            هنوز داده کافی برای رتبه‌بندی نیست.
                        </li>
                    @endforelse
                </ol>
            </div>
        @endforeach
    </div>
    </div>
</div>
