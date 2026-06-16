@php
    $kpis = $dashboard['kpis'];
    $deltas = $dashboard['kpis_delta'];
    $callTrend = $dashboard['call_activity_trend'];
    $qualityTrend = $dashboard['quality_trend'];
    $leadDist = $dashboard['lead_distribution'];
    $concerns = $dashboard['concerns_breakdown'];
    $employees = $dashboard['employee_comparison'];
    $leaderboards = $dashboard['leaderboards'];
    $aiTrend = $dashboard['ai_usage_trend'];

    $hasCallTrend = collect($callTrend)->isNotEmpty();
    $hasQualityTrend = collect($qualityTrend)->isNotEmpty();
    $hasLeadDist = ($leadDist['total'] ?? 0) > 0;
    $hasConcerns = count($concerns) > 0;
    $hasEmployees = count($employees) > 0;
    $hasAiTrend = collect($aiTrend)->isNotEmpty();

    $topConcern = $concerns[0]['label'] ?? null;

    $callChart = [
        'labels' => collect($callTrend)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'تماس‌ها',
            'data' => collect($callTrend)->pluck('count')->all(),
            'backgroundColor' => 'rgba(14, 165, 233, 0.85)',
            'borderRadius' => 6,
        ]],
        'options' => ['plugins' => ['legend' => ['display' => false]]],
    ];

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

    $leadChart = [
        'labels' => ['بالا', 'متوسط', 'پایین'],
        'datasets' => [[
            'data' => [$leadDist['high'] ?? 0, $leadDist['medium'] ?? 0, $leadDist['low'] ?? 0],
            'backgroundColor' => ['rgb(16, 185, 129)', 'rgb(245, 158, 11)', 'rgb(244, 63, 94)'],
        ]],
    ];

    $concernChart = [
        'labels' => collect($concerns)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'نگرانی‌ها',
            'data' => collect($concerns)->pluck('count')->all(),
            'backgroundColor' => 'rgba(245, 158, 11, 0.85)',
        ]],
        'options' => [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
        ],
    ];

    $employeeChart = [
        'labels' => collect($employees)->pluck('name')->all(),
        'datasets' => [
            [
                'label' => 'کیفیت تماس',
                'data' => collect($employees)->pluck('average_score')->all(),
                'backgroundColor' => 'rgba(99, 102, 241, 0.85)',
                'borderRadius' => 6,
            ],
            [
                'label' => 'کیفیت لید',
                'data' => collect($employees)->pluck('average_lead_score')->all(),
                'backgroundColor' => 'rgba(16, 185, 129, 0.75)',
                'borderRadius' => 6,
            ],
        ],
        'options' => ['plugins' => ['legend' => ['position' => 'bottom']]],
    ];

    $aiChart = [
        'labels' => collect($aiTrend)->pluck('label')->all(),
        'datasets' => [
            [
                'label' => 'تحلیل‌ها',
                'data' => collect($aiTrend)->pluck('analyses')->all(),
                'borderColor' => 'rgb(99, 102, 241)',
                'fill' => true,
                'tension' => 0.35,
                'yAxisID' => 'y',
            ],
            [
                'label' => 'هزینه',
                'data' => collect($aiTrend)->pluck('cost')->all(),
                'borderColor' => 'rgb(245, 158, 11)',
                'fill' => false,
                'tension' => 0.35,
                'yAxisID' => 'y1',
            ],
        ],
        'options' => [
            'scales' => [
                'y' => ['type' => 'linear', 'position' => 'left', 'beginAtZero' => true],
                'y1' => ['type' => 'linear', 'position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false]],
            ],
        ],
    ];

    $rankingMeta = [
        'best_quality' => [
            'title' => 'بهترین کیفیت تماس',
            'subtitle' => 'میانگین امتیاز مکالمه',
            'accent' => 'indigo',
            'metric' => fn (array $row) => $row['average_score'] ?? '—',
        ],
        'most_analyzed' => [
            'title' => 'بیشترین تحلیل',
            'subtitle' => 'حجم فعالیت',
            'accent' => 'sky',
            'metric' => fn (array $row) => $row['total_analyzed'] ?? '—',
        ],
        'highest_lead' => [
            'title' => 'بالاترین کیفیت لید',
            'subtitle' => 'میانگین امتیاز لید',
            'accent' => 'emerald',
            'metric' => fn (array $row) => $row['average_lead_score'] ?? '—',
        ],
        'overall' => [
            'title' => 'عملکرد کلی',
            'subtitle' => 'امتیاز ترکیبی',
            'accent' => 'amber',
            'metric' => fn (array $row) => $row['composite_score'] ?? '—',
        ],
    ];

    $filterLoadingTargets = 'datePreset,customFrom,customTo,applyCustomDateRange,selectedEmployeeIds,compareMode,setDatePreset,closeCustomDateRangePanel,clearDateFilter,clearFilters,clearEmployeeFilter,toggleEmployee';
    $overlayLoadingTargets = 'datePreset,customFrom,customTo,applyCustomDateRange,selectedEmployeeIds,compareMode,setDatePreset,closeCustomDateRangePanel,clearDateFilter,clearFilters,clearEmployeeFilter,toggleEmployee';
@endphp

<div class="saas-page space-y-6">
    <x-saas.filter-loading-overlay :target="$overlayLoadingTargets" />

    <x-saas.page-header
        data-tour="page-header"
        title="گزارش‌های مدیریتی"
        description="داشبورد تصمیم‌گیری برای مدیران — عملکرد تیم، لیدها، نگرانی‌ها و مصرف هوش مصنوعی."
    >
        <x-slot:actions>
            <a href="{{ route('employer.intelligence.index') }}" class="saas-btn-secondary text-sm">تحلیل تماس‌ها</a>
            <button type="button" wire:click="export('csv')" class="saas-btn-secondary text-sm">CSV</button>
            <button type="button" wire:click="export('xlsx')" class="saas-btn-secondary text-sm">Excel</button>
            <button type="button" wire:click="export('pdf')" class="saas-btn-secondary text-sm">PDF</button>
        </x-slot:actions>
    </x-saas.page-header>

    @include('livewire.employer.reports.partials.report-filters', [
        'primaryDatePresets' => $primaryDatePresets,
        'moreDatePresets' => $moreDatePresets,
        'filterEmployees' => $filterEmployees,
    ])

    <section class="saas-hero saas-hero--accent" data-tour="report-summary">
        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">خلاصه مدیریتی</p>
        <p class="mt-3 text-base leading-8 text-zinc-700 dark:text-zinc-200">{{ $dashboard['executive_summary'] }}</p>
    </section>

    @if ($kpis['top_employee'] !== '—' || $topConcern)
        <div class="flex flex-wrap gap-2">
            @if ($kpis['top_employee'] !== '—')
                <span class="inline-flex items-center gap-2 rounded-full border border-indigo-200/80 bg-indigo-50/80 px-3 py-1.5 text-xs font-medium text-indigo-800 dark:border-indigo-500/30 dark:bg-indigo-950/30 dark:text-indigo-300">
                    برترین کارشناس: {{ $kpis['top_employee'] }} ({{ $kpis['top_employee_score'] }})
                </span>
            @endif
            @if ($topConcern)
                <span class="inline-flex items-center gap-2 rounded-full border border-amber-200/80 bg-amber-50/80 px-3 py-1.5 text-xs font-medium text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-200">
                    نگرانی غالب: {{ $topConcern }}
                </span>
            @endif
        </div>
    @endif

    <div data-tour="report-kpis" class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-saas.stat-card label="کل تماس‌ها" :value="number_format($kpis['total_calls'])" />
        <x-saas.stat-card label="تحلیل‌شده" :value="number_format($kpis['total_analyzed'])" :trend="$deltas['total_analyzed']" />
        <x-saas.stat-card label="میانگین کیفیت" :value="$kpis['average_quality_score'] ?: '—'" :trend="$deltas['average_quality_score']" hint="امتیاز مکالمه" />
        <x-saas.stat-card label="میانگین کیفیت لید" :value="$kpis['average_lead_quality_score'] ?: '—'" :trend="$deltas['average_lead_quality_score']" />
        <x-saas.stat-card label="لیدهای با کیفیت" :value="number_format($kpis['high_quality_leads'])" :trend="$deltas['high_quality_leads']" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-saas.stat-card label="کل لیدها" :value="number_format($kpis['total_leads'])" />
        <x-saas.stat-card label="نگرانی‌های ثبت‌شده" :value="number_format($kpis['total_concerns'])" />
        <x-saas.stat-card label="میانگین مدت تماس" :value="$kpis['average_call_duration_label']" />
        <x-saas.stat-card label="هزینه AI" :value="$kpis['total_ai_cost']" :hint="number_format($kpis['total_tokens']).' توکن'" />
        <x-saas.stat-card label="برترین کارشناس" :value="$kpis['top_employee']" :hint="'امتیاز: '.$kpis['top_employee_score']" />
    </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2" data-tour="report-charts">
        <div class="saas-card">
            <h2 class="text-lg font-semibold">روند فعالیت تماس</h2>
            <p class="mt-1 text-sm text-zinc-500">حجم تماس‌ها در بازه انتخاب‌شده — برای جزئیات روی هر میله کلیک کنید</p>
            @if ($hasCallTrend)
                <div class="mt-4 h-56" wire:key="reports-call-{{ md5(json_encode($callTrend)) }}">
                    <canvas
                        id="chart-call-activity"
                        data-report-chart
                        data-type="bar"
                        data-drilldown="period"
                        data-drilldown-values='@json(collect($callTrend)->pluck('period')->all())'
                        data-config='@json($callChart)'
                    ></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="داده فعالیتی وجود ندارد" description="پس از ثبت تماس در این بازه، نمودار فعالیت پر می‌شود." />
                </div>
            @endif
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">روند کیفیت تماس</h2>
            <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز کیفیت مکالمه در طول زمان</p>
            @if ($hasQualityTrend)
                <div class="mt-4 h-56" wire:key="reports-quality-{{ md5(json_encode($qualityTrend)) }}">
                    <canvas
                        id="chart-quality-trend"
                        data-report-chart
                        data-type="line"
                        data-drilldown="period"
                        data-drilldown-values='@json(collect($qualityTrend)->pluck('period')->all())'
                        data-config='@json($qualityChart)'
                    ></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="داده کیفیتی وجود ندارد" description="با تحلیل تماس‌های بیشتر، روند کیفیت اینجا نمایش داده می‌شود." />
                </div>
            @endif
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">توزیع کیفیت لید</h2>
            <p class="mt-1 text-sm text-zinc-500">سطح لید در تماس‌های تحلیل‌شده</p>
            @if ($hasLeadDist)
                <div class="mt-4 mx-auto h-56 max-w-xs" wire:key="reports-lead-{{ md5(json_encode($leadDist)) }}">
                    <canvas
                        id="chart-lead-dist"
                        data-report-chart
                        data-type="doughnut"
                        data-drilldown="lead_level"
                        data-drilldown-values='["high","medium","low"]'
                        data-config='@json($leadChart)'
                    ></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="داده لید وجود ندارد" description="اطلاعات لید پس از تحلیل تماس‌ها ثبت می‌شود." />
                </div>
            @endif
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">نگرانی‌های پرتکرار</h2>
            <p class="mt-1 text-sm text-zinc-500">موضوعاتی که بیشتر در مکالمات مطرح شده‌اند</p>
            @if ($hasConcerns)
                <div class="mt-4 h-56" wire:key="reports-concerns-{{ md5(json_encode($concerns)) }}">
                    <canvas
                        id="chart-concerns"
                        data-report-chart
                        data-type="bar"
                        data-drilldown="concern_type"
                        data-drilldown-values='@json(collect($concerns)->pluck('type')->all())'
                        data-config='@json($concernChart)'
                    ></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="نگرانی ثبت نشده" description="نگرانی‌های مشتریان پس از تحلیل تماس‌ها اینجا نمایش داده می‌شوند." />
                </div>
            @endif
        </div>
    </div>

    @if ($compareMode && $hasEmployees)
        <div class="saas-card">
            <h2 class="text-lg font-semibold">مقایسه عملکرد کارشناسان</h2>
            <p class="mt-1 text-sm text-zinc-500">مقایسه کیفیت تماس و لید برای کارشناسان انتخاب‌شده</p>
            <div class="mt-4 h-72" wire:key="reports-employees-{{ md5(json_encode($employees)) }}">
                <canvas
                    id="chart-employees"
                    data-report-chart
                    data-type="bar"
                    data-drilldown="employee"
                    data-drilldown-values='@json(collect($employees)->pluck('id')->all())'
                    data-config='@json($employeeChart)'
                ></canvas>
            </div>
        </div>
    @elseif ($hasEmployees)
        <div class="saas-card">
            <h2 class="text-lg font-semibold">مقایسه عملکرد کارشناسان</h2>
            <p class="mt-1 text-sm text-zinc-500">برای مشاهده نمودار مقایسه‌ای، حالت مقایسه را در فیلترها فعال کنید</p>
            <div class="mt-4 rounded-lg border border-dashed border-zinc-200 px-6 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700">
                {{ count($employees) }} کارشناس در این بازه فعالیت داشته‌اند.
            </div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2" data-tour="report-rankings">
        @foreach ($rankingMeta as $key => $meta)
            @php
                $accentClasses = match ($meta['accent']) {
                    'emerald' => 'from-emerald-500/10 to-emerald-100/30 border-emerald-200/70 dark:from-emerald-500/15 dark:to-emerald-950/20 dark:border-emerald-500/20',
                    'amber' => 'from-amber-500/10 to-amber-100/30 border-amber-200/70 dark:from-amber-500/15 dark:to-amber-950/20 dark:border-amber-500/20',
                    'sky' => 'from-sky-500/10 to-sky-100/30 border-sky-200/70 dark:from-sky-500/15 dark:to-sky-950/20 dark:border-sky-500/20',
                    default => 'from-indigo-500/10 to-indigo-100/30 border-indigo-200/70 dark:from-indigo-500/15 dark:to-indigo-950/20 dark:border-indigo-500/20',
                };
            @endphp
            <div @class(['saas-widget border bg-gradient-to-b', $accentClasses])>
                <div>
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $meta['title'] }}</h3>
                    <p class="mt-0.5 text-[11px] text-zinc-500">{{ $meta['subtitle'] }}</p>
                </div>

                <ol class="mt-3 space-y-1.5">
                    @forelse (array_slice($leaderboards[$key] ?? [], 0, 5) as $i => $row)
                        <li>
                            <x-saas.agent-rank-row
                                :row="$row"
                                :employee="$employeesById->get($row['id'])"
                                :rank="$i + 1"
                                :value="$meta['metric']($row)"
                                wire:click="drilldown('employee', '{{ $row['id'] }}')"
                                class="w-full cursor-pointer text-start"
                            />
                        </li>
                    @empty
                        <li class="rounded-lg border border-dashed border-zinc-200 px-3 py-4 text-center text-xs text-zinc-500 dark:border-zinc-700">
                            داده کافی برای رتبه‌بندی وجود ندارد.
                        </li>
                    @endforelse
                </ol>
            </div>
        @endforeach
    </div>

    <div class="saas-card">
        <h2 class="text-lg font-semibold">مصرف هوش مصنوعی</h2>
        <p class="mt-1 text-sm text-zinc-500">تعداد تحلیل‌ها و هزینه مصرف AI در بازه انتخاب‌شده</p>
        @if ($hasAiTrend)
            <div class="mt-4 h-64" wire:key="reports-ai-{{ md5(json_encode($aiTrend)) }}">
                <canvas id="chart-ai-usage" data-report-chart data-type="line" data-config='@json($aiChart)'></canvas>
            </div>
        @else
            <div class="mt-4">
                <x-saas.empty-state title="داده مصرف AI وجود ندارد" description="پس از تحلیل تماس‌ها، روند مصرف هوش مصنوعی اینجا نمایش داده می‌شود." />
            </div>
        @endif
    </div>

    @if ($showDrilldown && $drilldownAnalyses)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/50 p-4 backdrop-blur-sm sm:items-center" wire:click.self="closeDrilldown">
            <div class="max-h-[85vh] w-full max-w-3xl overflow-hidden rounded-xl border border-zinc-200/80 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200/80 px-6 py-4 dark:border-zinc-800">
                    <div>
                        <h2 class="text-lg font-semibold">جزئیات تحلیل‌ها</h2>
                        <p class="mt-1 text-sm text-zinc-500">حداکثر ۲۰ مورد اخیر — برای مشاهده کامل به تحلیل تماس‌ها بروید</p>
                    </div>
                    <button type="button" wire:click="closeDrilldown" class="flex h-9 w-9 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800">
                        <span class="sr-only">بستن</span>
                        <span aria-hidden="true" class="text-2xl leading-none">&times;</span>
                    </button>
                </div>

                <div class="overflow-x-auto p-4 sm:p-6">
                    @if ($drilldownAnalyses->isEmpty())
                        <x-saas.empty-state title="رکوردی یافت نشد" description="در این بخش تحلیلی برای نمایش وجود ندارد." />
                    @else
                        <table class="saas-table min-w-[36rem]">
                            <thead>
                                <tr>
                                    <th>تاریخ</th>
                                    <th>کارشناس</th>
                                    <th>امتیاز</th>
                                    <th>لید</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($drilldownAnalyses as $analysis)
                                    <tr wire:key="drill-{{ $analysis->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                        <td class="whitespace-nowrap">{{ shamsi($analysis->analyzed_at, 'datetime') }}</td>
                                        <td>
                                            @if ($analysis->employee)
                                                <x-saas.user-cell :employee="$analysis->employee" avatar-size="xs" />
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            <span class="font-semibold tabular-nums">{{ $analysis->score }}</span>
                                        </td>
                                        <td>{{ $analysis->lead_quality_json['level'] ?? '—' }}</td>
                                        <td>
                                            <a href="{{ route('employer.intelligence.show', $analysis) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">مشاهده</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                @if ($drilldownAnalyses->count() >= 20)
                    <div class="border-t border-zinc-200/80 px-6 py-4 text-center dark:border-zinc-800">
                        <a href="{{ route('employer.intelligence.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                            مشاهده همه در تحلیل تماس‌ها
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

@script
<script>
    $wire.on('report-drilldown', ({ dimension, value }) => {
        $wire.drilldown(dimension, value);
    });
</script>
@endscript
