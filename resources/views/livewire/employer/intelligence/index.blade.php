@php
    use App\Support\AgentPerformancePresenter;
    use App\Support\AnalysisInsightPresenter;

    $qualityTrend = $charts['quality_trend'] ?? [];
    $volumeTrend = $charts['volume_trend'] ?? [];
    $sentimentBreakdown = $charts['sentiment_breakdown'] ?? [];
    $concerns = $charts['concerns'] ?? [];

    $hasQualityTrend = collect($qualityTrend)->isNotEmpty();
    $hasVolumeTrend = collect($volumeTrend)->isNotEmpty();
    $hasSentiment = count($sentimentBreakdown) > 0;
    $hasNegativeSentiment = collect($sentimentBreakdown)->contains(fn (array $item): bool => ($item['key'] ?? '') === 'negative' && (int) ($item['count'] ?? 0) > 0);
    $hasConcerns = count($concerns) > 0;

    $qualityChart = [
        'labels' => collect($qualityTrend)->pluck('label')->all(),
        'tooltipTitles' => collect($qualityTrend)->pluck('tooltip_label')->all(),
        'datasets' => [[
            'label' => 'میانگین امتیاز',
            'data' => collect($qualityTrend)->pluck('avg_score')->all(),
            'borderColor' => 'rgb(99, 102, 241)',
            'fill' => true,
            'tension' => 0.4,
        ]],
        'options' => [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => ['min' => 0, 'max' => 100, 'ticks' => ['stepSize' => 25]],
            ],
        ],
    ];

    $volumeChart = [
        'labels' => collect($volumeTrend)->pluck('label')->all(),
        'tooltipTitles' => collect($volumeTrend)->pluck('tooltip_label')->all(),
        'datasets' => [[
            'label' => 'تعداد تحلیل',
            'data' => collect($volumeTrend)->pluck('count')->all(),
            'backgroundColor' => 'rgba(14, 165, 233, 0.85)',
        ]],
        'options' => ['plugins' => ['legend' => ['display' => false]]],
    ];

    $sentimentColors = [
        'positive' => 'rgb(16, 185, 129)',
        'neutral' => 'rgb(161, 161, 170)',
        'negative' => 'rgb(244, 63, 94)',
        'mixed' => 'rgb(245, 158, 11)',
    ];

    $sentimentChart = [
        'labels' => collect($sentimentBreakdown)->pluck('label')->all(),
        'datasets' => [[
            'data' => collect($sentimentBreakdown)->pluck('count')->all(),
            'backgroundColor' => collect($sentimentBreakdown)->map(fn (array $item) => $sentimentColors[$item['key']] ?? 'rgb(161, 161, 170)')->all(),
        ]],
    ];

    $concernChart = [
        'labels' => collect($concerns)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'تعداد',
            'data' => collect($concerns)->pluck('count')->all(),
            'backgroundColor' => 'rgba(245, 158, 11, 0.85)',
        ]],
        'options' => [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
        ],
    ];

    $filterActionTargets = 'applyCustomDateRange,applyQuickFilter,setDatePreset,closeCustomDateRangePanel,clearDateFilter,clearFilters,sortByColumn,filterByAgent';
    $pinAnalysisListUnderFilters = $callStatus === 'lost' || $needsAttention;
@endphp

<div class="saas-page space-y-6">
    <x-saas.page-header
        data-tour="page-header"
        title="تحلیل تماس‌ها"
        description="پایش کیفیت مکالمات، روند تحلیل‌ها و بررسی جزئیات هر تماس در یک نما."
    >
        <x-slot:actions>
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" class="saas-btn-secondary" @click="open = ! open">
                    {{ __('ui.intelligence.reanalyze_menu') }}
                </button>
                <div
                    x-show="open"
                    x-cloak
                    class="absolute end-0 z-30 mt-2 w-80 space-y-3 rounded-lg border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <p class="text-xs font-medium text-zinc-500">بازه زمانی</p>
                    <div class="flex flex-col gap-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" wire:model.live="reanalyzeRangeMode" value="all" class="text-indigo-600">
                            {{ __('ui.intelligence.reanalyze_all_dates') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" wire:model.live="reanalyzeRangeMode" value="range" class="text-indigo-600">
                            {{ __('ui.intelligence.reanalyze_date_range') }}
                        </label>
                    </div>

                    @if ($reanalyzeRangeMode === 'range')
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <p class="mb-1 text-xs text-zinc-500">{{ __('ui.intelligence.reanalyze_from') }}</p>
                                <x-saas.jalali-date-input wire:key="reanalyze-from" wire:model="reanalyzeFrom" class="text-sm" />
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-zinc-500">{{ __('ui.intelligence.reanalyze_to') }}</p>
                                <x-saas.jalali-date-input wire:key="reanalyze-to" wire:model="reanalyzeTo" class="text-sm" />
                            </div>
                        </div>
                    @endif

                    <div class="space-y-1 border-t border-zinc-200 pt-2 dark:border-zinc-700">
                        <button
                            type="button"
                            class="block w-full rounded-md px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                            wire:click="reanalyzeConversations('under_20')"
                            wire:confirm="{{ __('ui.intelligence.reanalyze_confirm_under_20') }}"
                            @click="open = false"
                        >{{ __('ui.intelligence.reanalyze_under_20') }}</button>
                        <button
                            type="button"
                            class="block w-full rounded-md px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                            wire:click="reanalyzeConversations('under_50')"
                            wire:confirm="{{ __('ui.intelligence.reanalyze_confirm_under_50') }}"
                            @click="open = false"
                        >{{ __('ui.intelligence.reanalyze_under_50') }}</button>
                        <button
                            type="button"
                            class="block w-full rounded-md px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                            wire:click="reanalyzeConversations('all')"
                            wire:confirm="{{ __('ui.intelligence.reanalyze_confirm_all') }}"
                            @click="open = false"
                        >{{ __('ui.intelligence.reanalyze_all') }}</button>
                    </div>
                </div>
            </div>
            <a href="{{ route('employer.intelligence.performance') }}" class="saas-btn-secondary">عملکرد کارشناسان</a>
        </x-slot:actions>
    </x-saas.page-header>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100">
            {{ session('error') }}
        </div>
    @endif

    @include('livewire.employer.intelligence.partials.analysis-filters', [
        'employees' => $employees,
        'callStatuses' => $callStatuses,
        'directions' => $directions,
        'filter' => $filter,
    ])

    <div class="relative space-y-6">
        <x-saas.filter-loading-overlay scoped :target="$filterActionTargets" />

    @if ($pinAnalysisListUnderFilters)
        @include('livewire.employer.intelligence.partials.analysis-list')
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5" data-tour="analysis-stats">
        <x-saas.stat-card label="تعداد کل تماس‌ها" :value="number_format($overview['total_calls'])" />
        <x-saas.stat-card
            label="تماس‌های تحلیل‌شده"
            :value="number_format($overview['total'])"
            :hint="($overview['in_flight_count'] ?? 0) > 0
                ? number_format($overview['in_flight_count']).' در صف یا در حال پردازش'
                : null"
        />
        <x-saas.stat-card
            label="تماس‌های خارج از تحلیل"
            :value="number_format($overview['outside_analysis_count'] ?? 0)"
            hint="داخلی‌های تعریف‌نشده"
        />
        <x-saas.stat-card
            label="تماس از دست رفته"
            :value="number_format($overview['missed_count'])"
            :hint="'ورودی '.number_format($overview['inbound_count']).' · خروجی '.number_format($overview['outbound_count'])"
        />
        <x-saas.stat-card label="کل لیدها" :value="number_format($overview['total_leads'])" />
        <x-saas.stat-card label="میانگین کیفیت لیدها" :value="$overview['average_lead_score'] ?: '—'" :tone="\App\Support\MetricTone::fromScore($overview['average_lead_score'])" />
        <x-saas.stat-card label="رضایت مشتری" :value="$overview['average_sentiment'] ? $overview['average_sentiment'].'%' : '—'" :hint="$overview['dominant_sentiment']" :tone="\App\Support\MetricTone::fromScore($overview['average_sentiment'])" />
        <x-saas.stat-card label="میانگین امتیاز مکالمه" :value="$overview['average_score'] ?: '—'" :tone="\App\Support\MetricTone::fromScore($overview['average_score'])" />
        <x-saas.stat-card label="میانگین مدت تماس" :value="$overview['average_duration_label']" />
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-4" data-tour="analysis-charts">
        <div class="saas-card lg:col-span-2">
            <h2 class="text-lg font-semibold">روند کیفیت مکالمه</h2>
            <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز در بازه فیلتر فعلی</p>
            @if ($hasQualityTrend)
                <div wire:key="intel-quality-{{ md5(json_encode($qualityTrend)) }}">
                    <div class="mt-4 h-56" wire:ignore>
                        <canvas id="intel-quality-trend" data-report-chart data-type="line" data-config='@json($qualityChart)'></canvas>
                    </div>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.chart_trend.title') }}" description="{{ __('ui.empty.chart_trend.description') }}" />
                </div>
            @endif
        </div>

        <div class="saas-card lg:col-span-2">
            <h2 class="text-lg font-semibold">حجم تحلیل‌ها</h2>
            <p class="mt-1 text-sm text-zinc-500">تعداد تحلیل‌های انجام‌شده در هر بازه</p>
            @if ($hasVolumeTrend)
                <div wire:key="intel-volume-{{ md5(json_encode($volumeTrend)) }}">
                    <div class="mt-4 h-56" wire:ignore>
                        <canvas id="intel-volume-trend" data-report-chart data-type="bar" data-config='@json($volumeChart)'></canvas>
                    </div>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.chart_volume.title') }}" description="{{ __('ui.empty.chart_volume.description') }}" />
                </div>
            @endif
        </div>

        <div class="saas-card lg:col-span-1" data-drilldown-selected="{{ $selectedSentiment ?? '' }}">
            <h2 class="text-lg font-semibold">احساسات مشتری</h2>
            <p class="mt-1 text-sm text-zinc-500">
                توزیع احساس در مکالمات
                @if ($hasNegativeSentiment)
                    · برای دیدن تماس‌ها، بخش قرمز را انتخاب کنید
                @endif
            </p>
            @if ($hasSentiment)
                <div wire:key="intel-sentiment-{{ md5(json_encode($sentimentBreakdown)) }}">
                    <div class="mx-auto mt-4 aspect-square w-full" wire:ignore>
                        <canvas
                            id="intel-sentiment-dist"
                            data-report-chart
                            data-type="doughnut"
                            data-config='@json($sentimentChart)'
                            @if ($hasNegativeSentiment)
                                data-drilldown="sentiment"
                                data-drilldown-method="selectNegativeSentiment"
                                data-drilldown-allow="negative"
                                data-drilldown-values='@json(collect($sentimentBreakdown)->pluck('key')->values()->all())'
                            @endif
                        ></canvas>
                    </div>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.chart_sentiment.title') }}" description="{{ __('ui.empty.chart_sentiment.description') }}" />
                </div>
            @endif
        </div>

        @if ($hasConcerns)
            <div class="saas-card lg:col-span-3" data-drilldown-selected="{{ $selectedConcern ?? '' }}">
                <h2 class="text-lg font-semibold">نگرانی‌های پرتکرار</h2>
                <p class="mt-1 text-sm text-zinc-500">موضوعاتی که بیشتر در مکالمات مطرح شده‌اند. برای دیدن تماس‌ها روی هر مورد کلیک کنید.</p>
                <div wire:key="intel-concerns-{{ md5(json_encode($concerns)) }}">
                    <div class="mt-4 h-56" wire:ignore>
                        <canvas
                            id="intel-concerns-chart"
                            class="cursor-pointer"
                            data-report-chart
                            data-type="bar"
                            data-config='@json($concernChart)'
                            data-drilldown="concern"
                            data-drilldown-values='@json(collect($concerns)->pluck('type')->values()->all())'
                        ></canvas>
                    </div>
                </div>

                <div
                    wire:loading.delay.short.flex
                    wire:target="drilldown,selectConcern"
                    class="mt-4 items-center gap-2 text-sm text-zinc-500"
                >
                    <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-amber-500 border-t-transparent" aria-hidden="true"></span>
                    در حال آوردن تماس‌ها…
                </div>

                @if ($selectedConcern)
                    <div id="concern-call-list" wire:key="concern-call-list-{{ $selectedConcern }}" class="mt-4 overflow-hidden rounded-xl border border-zinc-200/80 bg-zinc-50/80 dark:border-zinc-800 dark:bg-zinc-950/40">
                        <div class="flex items-center justify-between gap-3 border-b border-zinc-200/80 px-4 py-3 dark:border-zinc-800">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">
                                    تماس‌های مرتبط با {{ $selectedConcernLabel }}
                                </p>
                                <p class="mt-0.5 text-xs text-zinc-500">
                                    {{ number_format($concernCallTotal) }} تماس در بازه فعلی
                                    @if (count($concernCalls) < $concernCallTotal)
                                        · {{ number_format(count($concernCalls)) }} تماس اخیر
                                    @endif
                                </p>
                            </div>
                            <button type="button" wire:click="clearConcern" class="saas-btn-secondary shrink-0 px-3 py-1.5 text-xs">
                                بستن
                            </button>
                        </div>

                        <div class="max-h-80 divide-y divide-zinc-200/80 overflow-y-auto dark:divide-zinc-800">
                            @forelse ($concernCalls as $call)
                                <a
                                    href="{{ route('employer.intelligence.show', $call['analysis_id']) }}"
                                    wire:key="concern-call-{{ $selectedConcern }}-{{ $call['analysis_id'] }}"
                                    class="group flex items-start gap-3 px-4 py-3 transition hover:bg-white dark:hover:bg-zinc-900"
                                >
                                    <div class="flex w-12 shrink-0 flex-col items-center rounded-lg bg-white px-1 py-1.5 text-center ring-1 ring-zinc-200/80 dark:bg-zinc-900 dark:ring-zinc-800">
                                        <span @class(['text-sm font-bold tabular-nums leading-none', AgentPerformancePresenter::scoreTextClass($call['quality_score'])])>
                                            {{ $call['quality_score'] ?? '—' }}
                                        </span>
                                        <span class="mt-1 text-[10px] text-zinc-400">امتیاز</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $call['customer'] }}</p>
                                            <span class="text-xs text-zinc-400">{{ $call['date'] }}</span>
                                        </div>
                                        <p class="mt-0.5 text-xs text-zinc-500">
                                            {{ $call['employee'] }}
                                            <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                            {{ $call['duration_label'] }}
                                        </p>
                                        @if ($call['concerns'] !== [])
                                            <div class="mt-2 space-y-1.5">
                                                @foreach ($call['concerns'] as $concern)
                                                    <p class="text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                                                        <span @class(['me-1.5 inline-flex rounded-md px-1.5 py-0.5 text-[11px] font-medium align-middle', AnalysisInsightPresenter::severityBadgeClass($concern['severity'])])>
                                                            شدت {{ AnalysisInsightPresenter::severityLabel($concern['severity']) }}
                                                        </span>
                                                        {{ $concern['text'] }}
                                                    </p>
                                                @endforeach
                                            </div>
                                        @elseif ($call['summary'])
                                            <p class="mt-1.5 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $call['summary'] }}</p>
                                        @endif
                                    </div>
                                    <span class="mt-1 shrink-0 text-xs font-medium text-zinc-400 transition group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                        جزئیات
                                    </span>
                                </a>
                            @empty
                                <p class="px-4 py-8 text-center text-sm text-zinc-500">تماسی با این نگرانی پیدا نشد.</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div
            wire:loading.delay.short.flex
            wire:target="selectNegativeSentiment"
            class="mt-0 items-center gap-2 text-sm text-zinc-500 lg:col-span-4"
        >
            <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-rose-500 border-t-transparent" aria-hidden="true"></span>
            در حال آوردن تماس‌های منفی…
        </div>

        @if ($selectedSentiment)
            <div id="sentiment-call-list" wire:key="sentiment-call-list" class="overflow-hidden rounded-xl border border-rose-200/80 bg-rose-50/40 lg:col-span-4 dark:border-rose-900/50 dark:bg-rose-950/20">
                <div class="flex items-center justify-between gap-3 border-b border-rose-200/70 px-4 py-3 dark:border-rose-900/40">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">تماس‌های با احساس منفی</p>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            {{ number_format($sentimentCallTotal) }} تماس در بازه فعلی
                            @if (count($sentimentCalls) < $sentimentCallTotal)
                                · {{ number_format(count($sentimentCalls)) }} تماس اخیر
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="clearSentiment" class="saas-btn-secondary shrink-0 px-3 py-1.5 text-xs">
                        بستن
                    </button>
                </div>

                <div class="max-h-80 divide-y divide-rose-200/60 overflow-y-auto bg-white dark:divide-rose-950/40 dark:bg-zinc-950">
                    @forelse ($sentimentCalls as $call)
                        <a
                            href="{{ route('employer.intelligence.show', $call['analysis_id']) }}"
                            wire:key="sentiment-call-{{ $call['analysis_id'] }}"
                            class="group flex items-start gap-3 px-4 py-3 transition hover:bg-rose-50/60 dark:hover:bg-rose-950/20"
                        >
                            <div class="flex w-12 shrink-0 flex-col items-center rounded-lg bg-rose-50 px-1 py-1.5 text-center ring-1 ring-rose-200/80 dark:bg-rose-950/40 dark:ring-rose-900/50">
                                <span @class(['text-sm font-bold tabular-nums leading-none', AgentPerformancePresenter::scoreTextClass($call['quality_score'])])>
                                    {{ $call['quality_score'] ?? '—' }}
                                </span>
                                <span class="mt-1 text-[10px] text-rose-400">امتیاز</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $call['customer'] }}</p>
                                    <span class="rounded-md bg-rose-100 px-1.5 py-0.5 text-[11px] font-medium text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">منفی</span>
                                    <span class="text-xs text-zinc-400">{{ $call['date'] }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-zinc-500">
                                    {{ $call['employee'] }}
                                    <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                    {{ $call['duration_label'] }}
                                </p>
                                @if ($call['summary'])
                                    <p class="mt-1.5 line-clamp-2 text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $call['summary'] }}</p>
                                @endif
                            </div>
                            <span class="mt-1 shrink-0 text-xs font-medium text-zinc-400 transition group-hover:text-rose-600 dark:group-hover:text-rose-400">
                                جزئیات
                            </span>
                        </a>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-zinc-500">تماسی با احساس منفی پیدا نشد.</p>
                    @endforelse
                </div>
            </div>
        @endif
    </div>

    @unless ($pinAnalysisListUnderFilters)
        @include('livewire.employer.intelligence.partials.analysis-list')
    @endunless
    </div>
</div>
