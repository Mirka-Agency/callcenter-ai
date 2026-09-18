@php
    use App\Support\AnalysisCallPresenter;

    $qualityTrend = $charts['quality_trend'] ?? [];
    $volumeTrend = $charts['volume_trend'] ?? [];
    $sentimentBreakdown = $charts['sentiment_breakdown'] ?? [];
    $concerns = $charts['concerns'] ?? [];

    $hasQualityTrend = collect($qualityTrend)->isNotEmpty();
    $hasVolumeTrend = collect($volumeTrend)->isNotEmpty();
    $hasSentiment = count($sentimentBreakdown) > 0;
    $hasConcerns = count($concerns) > 0;

    $qualityChart = [
        'labels' => collect($qualityTrend)->pluck('label')->all(),
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
            <a href="{{ route('employer.reports.index') }}" class="saas-btn-secondary">گزارش‌های مدیریتی</a>
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

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6" data-tour="analysis-stats">
        <x-saas.stat-card label="تحلیل‌های فیلترشده" :value="number_format($overview['total'])" />
        <x-saas.stat-card label="میانگین امتیاز" :value="$overview['average_score'] ?: '—'" hint="کیفیت مکالمه" />
        <x-saas.stat-card label="میانگین لید" :value="$overview['average_lead_score'] ?: '—'" :hint="$overview['high_lead_count'] ? $overview['high_lead_count'].' لید بالا' : null" />
        <x-saas.stat-card label="رضایت مشتری" :value="$overview['average_sentiment'] ? $overview['average_sentiment'].'%' : '—'" :hint="$overview['dominant_sentiment']" />
        <x-saas.stat-card label="میانگین مدت تماس" :value="$overview['average_duration_label']" />
        <x-saas.stat-card
            label="تماس از دست رفته"
            :value="number_format($overview['missed_count'])"
            :hint="'ورودی '.number_format($overview['inbound_count']).' · خروجی '.number_format($overview['outbound_count'])"
        />
    </div>

    @if ($overview['top_agent_name'] || $overview['top_concern'])
        <div class="flex flex-wrap gap-2">
            @if ($overview['top_agent_name'])
                <span class="inline-flex items-center gap-2 rounded-full border border-indigo-200/80 bg-indigo-50/80 px-3 py-1.5 text-xs font-medium text-indigo-800 dark:border-indigo-500/30 dark:bg-indigo-950/30 dark:text-indigo-300">
                    پرتحلیل‌ترین کارشناس: {{ $overview['top_agent_name'] }} ({{ $overview['top_agent_count'] }} تماس)
                </span>
            @endif
            @if ($overview['top_concern'])
                <span class="inline-flex items-center gap-2 rounded-full border border-amber-200/80 bg-amber-50/80 px-3 py-1.5 text-xs font-medium text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-200">
                    نگرانی غالب: {{ $overview['top_concern'] }}
                </span>
            @endif
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2" data-tour="analysis-charts">
        <div class="saas-card">
            <h2 class="text-lg font-semibold">روند کیفیت مکالمه</h2>
            <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز در بازه فیلتر فعلی</p>
            @if ($hasQualityTrend)
                <div class="mt-4 h-56" wire:key="intel-quality-{{ md5(json_encode($qualityTrend)) }}">
                    <canvas id="intel-quality-trend" data-report-chart data-type="line" data-config='@json($qualityChart)'></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.chart_trend.title') }}" description="{{ __('ui.empty.chart_trend.description') }}" />
                </div>
            @endif
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">حجم تحلیل‌ها</h2>
            <p class="mt-1 text-sm text-zinc-500">تعداد تحلیل‌های انجام‌شده در هر بازه</p>
            @if ($hasVolumeTrend)
                <div class="mt-4 h-56" wire:key="intel-volume-{{ md5(json_encode($volumeTrend)) }}">
                    <canvas id="intel-volume-trend" data-report-chart data-type="bar" data-config='@json($volumeChart)'></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.chart_volume.title') }}" description="{{ __('ui.empty.chart_volume.description') }}" />
                </div>
            @endif
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">احساسات مشتری</h2>
            <p class="mt-1 text-sm text-zinc-500">توزیع احساس در مکالمات</p>
            @if ($hasSentiment)
                <div class="mt-4 h-56" wire:key="intel-sentiment-{{ md5(json_encode($sentimentBreakdown)) }}">
                    <canvas id="intel-sentiment-dist" data-report-chart data-type="doughnut" data-config='@json($sentimentChart)'></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.chart_sentiment.title') }}" description="{{ __('ui.empty.chart_sentiment.description') }}" />
                </div>
            @endif
        </div>

        @if ($hasConcerns)
            <div class="saas-card lg:col-span-2">
                <h2 class="text-lg font-semibold">نگرانی‌های پرتکرار</h2>
                <p class="mt-1 text-sm text-zinc-500">موضوعاتی که بیشتر در مکالمات مطرح شده‌اند</p>
                <div class="mt-4 h-52" wire:key="intel-concerns-{{ md5(json_encode($concerns)) }}">
                    <canvas id="intel-concerns-chart" data-report-chart data-type="bar" data-config='@json($concernChart)'></canvas>
                </div>
            </div>
        @endif
    </div>

    <div class="saas-card saas-analysis-list-panel overflow-hidden p-0" data-tour="analysis-list">
        <div class="saas-list-toolbar flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-4 py-4 dark:border-zinc-800 sm:px-6">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold">لیست تحلیل مکالمات</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ number_format($analyses->total()) }} نتیجه
                    @if ($filter->hasActiveFilters())
                        · فیلتر فعال
                    @endif
                    <span class="hidden sm:inline">· برای جزئیات روی هر ردیف کلیک کنید</span>
                </p>
            </div>
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="جستجو در خلاصه، مشتری یا کارشناس..."
                class="saas-input w-full text-sm sm:max-w-xs"
            >
        </div>

        <div class="min-h-[12rem]">
            @if ($analyses->isEmpty())
                <div class="p-8">
                    @if ($filter->hasActiveFilters())
                        <x-saas.empty-state
                            title="{{ __('ui.empty.no_results_filter.title') }}"
                            description="{{ __('ui.empty.no_results_filter.description') }}"
                        >
                            <button type="button" wire:click="clearFilters" class="saas-btn-primary mt-4">@lang('ui.cta.clear_filters')</button>
                        </x-saas.empty-state>
                    @else
                        <x-saas.empty-state
                            title="{{ __('ui.empty.no_analyses.title') }}"
                            description="{{ __('ui.empty.no_analyses.description') }}"
                        />
                    @endif
                </div>
            @else
                <div class="saas-analysis-list-scroll overflow-x-auto lg:overflow-x-auto">
                    <div class="saas-analysis-list-table min-w-[58rem] lg:min-w-[58rem]">
                        <div class="saas-analysis-list-header saas-analysis-list-grid">
                            <div>
                                <button type="button" wire:click="sortByColumn('analyzed_at')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                    تاریخ و زمان <x-saas.sort-icon :active="$sortBy === 'analyzed_at'" :dir="$sortDir" />
                                </button>
                            </div>
                            <div>
                                <button type="button" wire:click="sortByColumn('agent')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                    کارشناس <x-saas.sort-icon :active="$sortBy === 'agent'" :dir="$sortDir" />
                                </button>
                            </div>
                            <div>خلاصه</div>
                            <div>
                                <button type="button" wire:click="sortByColumn('duration')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                    مدت <x-saas.sort-icon :active="$sortBy === 'duration'" :dir="$sortDir" />
                                </button>
                            </div>
                            <div>
                                <button type="button" wire:click="sortByColumn('status')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                    وضعیت <x-saas.sort-icon :active="$sortBy === 'status'" :dir="$sortDir" />
                                </button>
                            </div>
                            <div>جهت</div>
                            <div class="text-end">
                                <button type="button" wire:click="sortByColumn('score')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                    امتیاز <x-saas.sort-icon :active="$sortBy === 'score'" :dir="$sortDir" />
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 p-3">
                            @foreach ($analyses as $analysis)
                                @php
                                    $status = AnalysisCallPresenter::status($analysis);
                                    $direction = AnalysisCallPresenter::direction($analysis);
                                @endphp
                                <div
                                    wire:key="analysis-{{ $analysis->id }}"
                                    data-row-href="{{ route('employer.intelligence.show', $analysis) }}"
                                    role="link"
                                    tabindex="0"
                                    aria-label="مشاهده جزئیات تحلیل {{ shamsi($analysis->analyzed_at) }}"
                                    class="saas-analysis-row saas-analysis-list-grid group"
                                >
                                    <div class="min-w-0 whitespace-nowrap">
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ shamsi($analysis->analyzed_at) }}</p>
                                        <p class="text-xs text-zinc-500">{{ shamsi($analysis->analyzed_at, 'time') }}</p>
                                    </div>

                                    <div class="min-w-0 whitespace-nowrap">
                                        @if ($analysis->employee)
                                            <button
                                                type="button"
                                                wire:click.stop="filterByAgent({{ $analysis->organization_user_id }})"
                                                data-row-ignore
                                                class="relative z-10 rounded-lg p-1 text-start transition hover:bg-white/90 hover:shadow-sm dark:hover:bg-zinc-800/90"
                                                title="فیلتر بر اساس این کارشناس"
                                            >
                                                <x-saas.user-cell
                                                    :employee="$analysis->employee"
                                                    :subtitle="$analysis->employee->department"
                                                    avatar-size="xs"
                                                />
                                            </button>
                                        @else
                                            <div class="space-y-0.5">
                                                <span class="text-zinc-400">بدون اختصاص</span>
                                                @php
                                                    $unassignedExtension = $analysis->callLog?->raw_payload['resolved_extension']
                                                        ?? $analysis->callLog?->raw_payload['extension']
                                                        ?? $analysis->call?->receiver_number;
                                                @endphp
                                                @if ($analysis->callLog?->source_number || $unassignedExtension)
                                                    <p class="text-xs tabular-nums text-zinc-500" dir="ltr">
                                                        {{ $analysis->callLog?->source_number ?: '—' }}
                                                        →
                                                        {{ $unassignedExtension ?: ($analysis->callLog?->destination_number ?: '—') }}
                                                    </p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <div class="min-w-0">
                                        <p class="line-clamp-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $analysis->summary }}</p>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                            <p class="text-xs text-zinc-400">{{ $analysis->source?->label() ?? 'VoIP' }}</p>
                                            @if ($analysis->needs_attention)
                                                <span class="rounded-md bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">نیازمند توجه</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="whitespace-nowrap tabular-nums text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ AnalysisCallPresenter::durationLabel($analysis) }}
                                    </div>

                                    <div class="whitespace-nowrap">
                                        @if ($status)
                                            <span @class(['saas-badge', AnalysisCallPresenter::statusBadgeClass($status)])>{{ $status->label() }}</span>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </div>

                                    <div class="whitespace-nowrap">
                                        @if ($direction)
                                            <span class="saas-badge bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $direction->label() }}</span>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </div>

                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        <span @class([
                                            'inline-flex h-9 min-w-9 items-center justify-center rounded-full px-2 text-sm font-bold tabular-nums transition-all duration-200',
                                            'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => ! $analysis->isEvaluable(),
                                            'bg-emerald-50 text-emerald-700 group-hover:bg-emerald-100 group-hover:shadow-sm dark:bg-emerald-950/40 dark:text-emerald-300 dark:group-hover:bg-emerald-950/60' => $analysis->isEvaluable() && $analysis->score >= 85,
                                            'bg-amber-50 text-amber-700 group-hover:bg-amber-100 group-hover:shadow-sm dark:bg-amber-950/40 dark:text-amber-300 dark:group-hover:bg-amber-950/60' => $analysis->isEvaluable() && $analysis->score >= 70 && $analysis->score < 85,
                                            'bg-red-50 text-red-700 group-hover:bg-red-100 group-hover:shadow-sm dark:bg-red-950/40 dark:text-red-300 dark:group-hover:bg-red-950/60' => $analysis->isEvaluable() && $analysis->score < 70,
                                        ]) title="{{ $analysis->isEvaluable() ? '' : __('ui.intelligence.not_evaluable') }}">{{ $analysis->isEvaluable() ? $analysis->score : '—' }}</span>
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-500/0 text-indigo-500 transition-all duration-200 group-hover:translate-x-0.5 group-hover:bg-indigo-500/10">
                                            <svg class="h-4 w-4 opacity-0 transition-opacity duration-200 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="border-t border-zinc-200/80 px-6 py-4 dark:border-zinc-800">
                    {{ $analyses->links() }}
                </div>
            @endif
        </div>
    </div>
    </div>
</div>
