@php
    use App\Support\AnalysisCallPresenter;
    use App\Support\AnalysisInsightPresenter;

    $qualityTrend = $charts['quality_trend'] ?? [];
    $volumeTrend = $charts['volume_trend'] ?? [];
    $leadDist = $charts['lead_distribution'] ?? [];
    $sentimentBreakdown = $charts['sentiment_breakdown'] ?? [];
    $concerns = $charts['concerns'] ?? [];

    $hasQualityTrend = collect($qualityTrend)->isNotEmpty();
    $hasVolumeTrend = collect($volumeTrend)->isNotEmpty();
    $hasLeadDist = ($leadDist['total'] ?? 0) > 0;
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
            'scales' => ['y' => ['min' => 0, 'max' => 100, 'ticks' => ['stepSize' => 25]]],
        ],
    ];

    $volumeChart = [
        'labels' => collect($volumeTrend)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'تعداد تحلیل',
            'data' => collect($volumeTrend)->pluck('count')->all(),
            'backgroundColor' => 'rgba(14, 165, 233, 0.85)',
            'borderRadius' => 6,
        ]],
        'options' => ['plugins' => ['legend' => ['display' => false]]],
    ];

    $leadChart = [
        'labels' => ['بالا', 'متوسط', 'پایین'],
        'datasets' => [[
            'data' => [$leadDist['high'] ?? 0, $leadDist['medium'] ?? 0, $leadDist['low'] ?? 0],
            'backgroundColor' => ['rgb(16, 185, 129)', 'rgb(245, 158, 11)', 'rgb(244, 63, 94)'],
        ]],
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

    $filterLoadingTargets = 'search,datePreset,customFrom,customTo,applyCustomDateRange,callStatus,directionFilter,durationMin,durationMax,applyQuickFilter,setDatePreset,closeCustomDateRangePanel,clearDateFilter,clearFilters,sortByColumn';
    $overlayLoadingTargets = 'datePreset,customFrom,customTo,applyCustomDateRange,callStatus,directionFilter,durationMin,durationMax,applyQuickFilter,setDatePreset,closeCustomDateRangePanel,clearDateFilter,clearFilters,sortByColumn';
@endphp

<div class="saas-page space-y-6" wire:loading.class="opacity-60" wire:target="{{ $filterLoadingTargets }}">
    <x-saas.filter-loading-overlay :target="$overlayLoadingTargets" />

    <x-saas.page-header
        title="تماس‌های من"
        description="بینش‌های عمیق از تماس‌های تحلیل‌شده — با فیلتر و نمودار، عملکرد خود را بسنجید."
        data-tour="page-header"
    >
        <x-slot:actions>
            <a href="{{ route('employee.performance') }}" class="saas-btn-secondary text-sm">عملکرد من</a>
            <a href="{{ route('employee.uploads') }}" class="saas-btn-primary text-sm">@lang('ui.cta.upload_first_call')</a>
        </x-slot:actions>
    </x-saas.page-header>

    @include('livewire.employee.calls.partials.call-filters', [
        'primaryDatePresets' => $primaryDatePresets,
        'moreDatePresets' => $moreDatePresets,
        'callStatuses' => $callStatuses,
        'directions' => $directions,
        'filter' => $filter,
    ])

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6" data-tour="calls-stats">
        <x-saas.stat-card label="تحلیل‌های فیلترشده" :value="number_format($overview['total'])" />
        <x-saas.stat-card label="میانگین امتیاز" :value="$overview['average_score'] ?: '—'" hint="کیفیت مکالمه" />
        <x-saas.stat-card label="میانگین سرنخ" :value="$overview['average_lead_score'] ?: '—'" :hint="$overview['high_lead_count'] ? $overview['high_lead_count'].' سرنخ با کیفیت بالا' : null" />
        <x-saas.stat-card label="رضایت مشتری" :value="$overview['average_sentiment'] ? $overview['average_sentiment'].'%' : '—'" :hint="$overview['dominant_sentiment']" />
        <x-saas.stat-card label="میانگین مدت تماس" :value="$overview['average_duration_label']" />
        <x-saas.stat-card
            label="تماس از دست رفته"
            :value="number_format($overview['missed_count'])"
            :hint="'ورودی '.number_format($overview['inbound_count']).' · خروجی '.number_format($overview['outbound_count'])"
        />
    </div>

    @if ($overview['top_concern'])
        <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center gap-2 rounded-full border border-amber-200/80 bg-amber-50/80 px-3 py-1.5 text-xs font-medium text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-200">
                نگرانی پرتکرار: {{ $overview['top_concern'] }}
            </span>
        </div>
    @endif

    @if ($overview['total'] > 0)
        <div class="grid gap-6 lg:grid-cols-2" data-tour="calls-charts">
            <div class="saas-card">
                <h2 class="text-lg font-semibold">روند کیفیت مکالمه</h2>
                <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز در بازه فیلتر فعلی</p>
                @if ($hasQualityTrend)
                    <div class="mt-4 h-56" wire:key="employee-call-quality-{{ md5(json_encode($qualityTrend)) }}">
                        <canvas id="employee-call-quality-trend" data-report-chart data-type="line" data-config='@json($qualityChart)'></canvas>
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
                <h2 class="text-lg font-semibold">حجم تحلیل‌ها</h2>
                <p class="mt-1 text-sm text-zinc-500">تعداد تحلیل‌های انجام‌شده در هر بازه</p>
                @if ($hasVolumeTrend)
                    <div class="mt-4 h-56" wire:key="employee-call-volume-{{ md5(json_encode($volumeTrend)) }}">
                        <canvas id="employee-call-volume-trend" data-report-chart data-type="bar" data-config='@json($volumeChart)'></canvas>
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
                <h2 class="text-lg font-semibold">توزیع کیفیت سرنخ</h2>
                <p class="mt-1 text-sm text-zinc-500">سطح سرنخ در تماس‌های تحلیل‌شده</p>
                @if ($hasLeadDist)
                    <div class="mt-4 mx-auto h-56 max-w-xs" wire:key="employee-call-lead-{{ md5(json_encode($leadDist)) }}">
                        <canvas id="employee-call-lead-dist" data-report-chart data-type="doughnut" data-config='@json($leadChart)'></canvas>
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

            <div class="saas-card">
                <h2 class="text-lg font-semibold">احساسات مشتری</h2>
                <p class="mt-1 text-sm text-zinc-500">توزیع احساس در مکالمات شما</p>
                @if ($hasSentiment)
                    <div class="mt-4 mx-auto h-56 max-w-xs" wire:key="employee-call-sentiment-{{ md5(json_encode($sentimentBreakdown)) }}">
                        <canvas id="employee-call-sentiment-dist" data-report-chart data-type="doughnut" data-config='@json($sentimentChart)'></canvas>
                    </div>
                @else
                    <div class="mt-4">
                        <x-saas.empty-state
                            title="{{ __('ui.empty.chart_sentiment.title') }}"
                            description="{{ __('ui.empty.chart_sentiment.description') }}"
                        />
                    </div>
                @endif
            </div>

            @if ($hasConcerns)
                <div class="saas-card lg:col-span-2">
                    <h2 class="text-lg font-semibold">نگرانی‌های پرتکرار</h2>
                    <p class="mt-1 text-sm text-zinc-500">موضوعاتی که بیشتر در مکالمات شما مطرح شده‌اند</p>
                    <div class="mt-4 h-52" wire:key="employee-call-concerns-{{ md5(json_encode($concerns)) }}">
                        <canvas id="employee-call-concerns-chart" data-report-chart data-type="bar" data-config='@json($concernChart)'></canvas>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="saas-card overflow-hidden p-0" data-tour="calls-list">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-6 py-4 dark:border-zinc-800">
            <div>
                <h2 class="text-lg font-semibold">لیست تماس‌های تحلیل‌شده</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ number_format($analyses->total()) }} نتیجه
                    @if ($filter->hasUserFilters())
                        · فیلتر فعال
                    @endif
                    · برای جزئیات روی هر ردیف کلیک کنید
                </p>
            </div>
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="جستجو در خلاصه یا نام مشتری..."
                class="saas-input max-w-xs text-sm"
            >
        </div>

        <div wire:loading.remove wire:target="{{ $filterLoadingTargets }}">
            @if ($analyses->isEmpty())
                <div class="p-8">
                    @if ($filter->hasUserFilters())
                        <x-saas.empty-state
                            title="{{ __('ui.empty.no_results_filter.title') }}"
                            description="{{ __('ui.empty.no_results_filter.description') }}"
                        >
                            <button type="button" wire:click="clearFilters" class="saas-btn-primary mt-4">@lang('ui.cta.clear_filters')</button>
                        </x-saas.empty-state>
                    @else
                        <x-saas.empty-state
                            title="{{ __('ui.empty.no_calls.title') }}"
                            description="{{ __('ui.empty.no_calls.description') }}"
                        >
                            <div class="mt-6 flex flex-wrap justify-center gap-2">
                                <a href="{{ route('employee.uploads') }}" class="saas-btn-primary text-sm">@lang('ui.cta.upload_first_call')</a>
                                <a href="{{ route('employee.performance') }}" class="saas-btn-secondary text-sm">عملکرد من</a>
                            </div>
                        </x-saas.empty-state>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <div class="min-w-[52rem]">
                        @php
                            $sortIcon = fn (string $column) => $sortBy === $column
                                ? ($sortDir === 'asc' ? '↑' : '↓')
                                : '↕';
                        @endphp

                        <div class="saas-analysis-list-header saas-employee-call-list-grid">
                            <div>
                                <button type="button" wire:click="sortByColumn('analyzed_at')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                    تاریخ <span class="text-[10px] text-zinc-400">{{ $sortIcon('analyzed_at') }}</span>
                                </button>
                            </div>
                            <div>مشتری</div>
                            <div>خلاصه</div>
                            <div>
                                <button type="button" wire:click="sortByColumn('duration')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                    مدت <span class="text-[10px] text-zinc-400">{{ $sortIcon('duration') }}</span>
                                </button>
                            </div>
                            <div>
                                <button type="button" wire:click="sortByColumn('status')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                    وضعیت <span class="text-[10px] text-zinc-400">{{ $sortIcon('status') }}</span>
                                </button>
                            </div>
                            <div>جهت</div>
                            <div>سرنخ</div>
                            <div class="text-end">
                                <button type="button" wire:click="sortByColumn('score')" class="inline-flex items-center gap-1 transition hover:text-zinc-900 dark:hover:text-white">
                                    امتیاز <span class="text-[10px] text-zinc-400">{{ $sortIcon('score') }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 p-3">
                            @foreach ($analyses as $analysis)
                                @php
                                    $status = AnalysisCallPresenter::status($analysis);
                                    $direction = AnalysisCallPresenter::direction($analysis);
                                    $customer = AnalysisInsightPresenter::customerName($analysis)
                                        ?? $analysis->call?->caller_number
                                        ?? 'مشتری نامشخص';
                                    $lead = $analysis->lead_quality_json ?? [];
                                @endphp
                                <div
                                    wire:key="employee-call-{{ $analysis->id }}"
                                    data-row-href="{{ route('employee.calls.show', $analysis) }}"
                                    role="link"
                                    tabindex="0"
                                    aria-label="مشاهده تحلیل تماس {{ shamsi($analysis->analyzed_at) }}"
                                    class="saas-analysis-row saas-employee-call-list-grid group"
                                >
                                    <div class="min-w-0 whitespace-nowrap">
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ shamsi($analysis->analyzed_at) }}</p>
                                        <p class="text-xs text-zinc-500">{{ shamsi($analysis->analyzed_at, 'time') }}</p>
                                    </div>

                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $customer }}</p>
                                        <p class="text-xs text-zinc-400">{{ $analysis->source?->label() ?? 'VoIP' }}</p>
                                    </div>

                                    <div class="min-w-0">
                                        <p class="line-clamp-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $analysis->summary ?: '—' }}</p>
                                        @if ($analysis->sentiment)
                                            <span @class(['mt-1 inline-flex saas-badge', AnalysisInsightPresenter::sentimentBadgeClass($analysis->sentiment)])>
                                                {{ $analysis->sentiment->label() }}
                                            </span>
                                        @endif
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

                                    <div class="whitespace-nowrap text-sm">
                                        @if ($lead['score'] ?? null)
                                            <span @class(['font-semibold tabular-nums', AnalysisInsightPresenter::leadLevelClass($lead['level'] ?? null)])>
                                                {{ $lead['score'] }}
                                            </span>
                                            <span class="text-xs text-zinc-400">{{ AnalysisInsightPresenter::leadLevelLabel($lead['level'] ?? null) }}</span>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </div>

                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        <span @class([
                                            'inline-flex h-9 min-w-9 items-center justify-center rounded-full px-2 text-sm font-bold tabular-nums transition-all duration-200',
                                            'bg-emerald-50 text-emerald-700 group-hover:bg-emerald-100 group-hover:shadow-sm dark:bg-emerald-950/40 dark:text-emerald-300 dark:group-hover:bg-emerald-950/60' => $analysis->score >= 85,
                                            'bg-amber-50 text-amber-700 group-hover:bg-amber-100 group-hover:shadow-sm dark:bg-amber-950/40 dark:text-amber-300 dark:group-hover:bg-amber-950/60' => $analysis->score >= 70 && $analysis->score < 85,
                                            'bg-red-50 text-red-700 group-hover:bg-red-100 group-hover:shadow-sm dark:bg-red-950/40 dark:text-red-300 dark:group-hover:bg-red-950/60' => $analysis->score < 70,
                                        ])>{{ $analysis->score }}</span>
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full text-indigo-500 transition-all duration-200 group-hover:translate-x-0.5 group-hover:bg-indigo-500/10">
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
