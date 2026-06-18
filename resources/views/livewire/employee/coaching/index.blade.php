@php
    use App\Support\AgentPerformancePresenter;
    use App\Support\AnalysisInsightPresenter;

    $coaching = $coaching ?? [];
    $dimensions = $dimensions ?? [];
    $maxWeakness = collect($weaknessesRanked)->max('count') ?: 1;
    $maxStrength = collect($strengthsRanked)->max('count') ?: 1;
    $totalStrengthMentions = collect($strengthsRanked)->sum('count');
    $hasStrengths = count($strengthsRanked) > 0;
    $strengthChartHeight = min(380, max(220, count($strengthsRanked) * 52 + 48));

    $strengthsChart = [
        'labels' => collect($strengthsRanked)->pluck('item')->map(fn (string $item) => \Illuminate\Support\Str::limit($item, 40))->values()->all(),
        'datasets' => [[
            'label' => 'تکرار',
            'data' => collect($strengthsRanked)->pluck('count')->all(),
            'backgroundColor' => collect($strengthsRanked)->map(function (array $row) use ($maxStrength) {
                $ratio = $maxStrength > 0 ? $row['count'] / $maxStrength : 0;
                $alpha = number_format(0.5 + ($ratio * 0.45), 2);

                return "rgba(16, 185, 129, {$alpha})";
            })->all(),
            'hoverBackgroundColor' => collect($strengthsRanked)->map(fn () => 'rgba(5, 150, 105, 0.95)')->all(),
            'borderColor' => 'rgba(5, 150, 105, 0.25)',
            'borderWidth' => 1,
        ]],
        'options' => [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => ['stepSize' => 1, 'precision' => 0],
                    'grid' => ['display' => true, 'drawTicks' => false],
                ],
                'y' => [
                    'grid' => ['display' => false],
                    'ticks' => [
                        'font' => ['size' => 11, 'weight' => '600'],
                        'padding' => 8,
                    ],
                ],
            ],
        ],
    ];

    $filterActionTargets = 'setPeriod';
@endphp

<div class="saas-page space-y-6">
    <x-saas.filter-loading-overlay :target="$filterActionTargets" />

    <x-saas.page-header
        title="مربیگری فروش"
        description="برنامه رشد شخصی‌سازی‌شده بر اساس تحلیل تماس‌ها — نقاط قوت، حوزه‌های بهبود و اقدامات عملی."
        data-tour="page-header"
    >
        <x-slot:actions>
            <a href="{{ route('employee.calls') }}" class="saas-btn-secondary text-sm">تماس‌های من</a>
            <a href="{{ route('employee.performance', ['preset' => $period]) }}" class="saas-btn-primary text-sm">عملکرد من</a>
        </x-slot:actions>
    </x-saas.page-header>

    <section class="saas-hero saas-hero--accent" data-tour="coaching-hero">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-5">
                <x-saas.avatar :employee="$membership" size="xl" ring />
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">مرکز مربیگری</p>
                    <h2 class="text-2xl font-bold tracking-tight">{{ $membership->first_name }}، برنامه رشد شما</h2>
                    <p class="mt-2 max-w-xl text-sm leading-7 text-zinc-600 dark:text-zinc-300">
                        {{ $profile['executive_summary'] ?? 'با تحلیل تماس‌های اخیر، حوزه‌های تمرکز و اقدامات عملی اینجا پیشنهاد می‌شود.' }}
                    </p>
                </div>
            </div>
            <x-saas.score-ring
                :score="$metrics['average_quality_score'] ?? null"
                size="lg"
                label="میانگین کیفیت"
            />
        </div>
    </section>

    @include('livewire.employee.coaching.partials.period-filters', [
        'periodPresets' => $periodPresets,
        'activePreset' => $activePreset,
    ])

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-tour="coaching-stats">
        <x-saas.stat-card label="تماس‌های تحلیل‌شده" :value="$analyzedCount" hint="در بازه انتخاب‌شده" />
        <x-saas.stat-card label="میانگین امتیاز" :value="$metrics['average_quality_score'] ?: '—'" hint="کیفیت مکالمه" />
        <x-saas.stat-card label="امتیاز سرنخ" :value="$metrics['average_lead_score'] ?: '—'" hint="میانگین کیفیت فروش" />
        <x-saas.stat-card label="رضایت مشتری" :value="$metrics['average_sentiment'] ? $metrics['average_sentiment'].'%' : '—'" />
    </div>

    @if ($analyzedCount === 0)
        <div class="saas-card">
            <x-saas.empty-state
                title="{{ __('ui.empty.no_coaching.title') }}"
                description="{{ __('ui.empty.no_coaching.description') }}"
            >
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('employee.uploads') }}" class="saas-btn-primary text-sm">@lang('ui.cta.upload_first_call')</a>
                    <a href="{{ route('employee.calls') }}" class="saas-btn-secondary text-sm">تماس‌های من</a>
                </div>
            </x-saas.empty-state>
        </div>
    @else
        @if (! empty($coaching['training_areas']) || ! empty($coaching['coaching_plan']))
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="saas-card border border-indigo-200/60 bg-indigo-50/30 dark:border-indigo-900/40 dark:bg-indigo-950/20">
                    <h2 class="text-lg font-semibold">پیشنهادهای آموزشی</h2>
                    <p class="mt-1 text-sm text-zinc-500">بر اساس ضعف‌های پرتکرار در مکالمات شما</p>
                    <ul class="mt-4 space-y-3">
                        @forelse ($coaching['training_areas'] as $area)
                            <li class="flex gap-3 rounded-lg bg-white/70 px-3 py-2.5 text-sm dark:bg-zinc-900/50">
                                <span class="mt-0.5 text-indigo-500">✦</span>
                                <span>{{ $area }}</span>
                            </li>
                        @empty
                            <li class="text-sm text-zinc-500">با تحلیل تماس‌های بیشتر، پیشنهادهای آموزشی اینجا ظاهر می‌شوند.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="saas-card">
                    <h2 class="text-lg font-semibold">برنامه مربیگری هفتگی</h2>
                    <p class="mt-1 text-sm text-zinc-500">گام‌های عملی برای اجرا در هفته جاری</p>
                    <ol class="mt-4 space-y-3">
                        @foreach ($coaching['coaching_plan'] as $i => $step)
                            <li class="flex gap-3 text-sm leading-relaxed">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">{{ $i + 1 }}</span>
                                <span class="pt-0.5">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        @endif

        @if (! empty($dimensions))
            <div class="saas-card">
                <h2 class="text-lg font-semibold">ابعاد نیازمند تمرکز</h2>
                <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز هر بعد — روی پایین‌ترین‌ها تمرکز کنید</p>
                <div class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach (collect($dimensions)->sortBy(fn ($score) => $score) as $key => $score)
                        <x-saas.dimension-ring
                            :label="AnalysisInsightPresenter::dimensionLabel($key)"
                            :score="$score"
                        />
                    @endforeach
                </div>
            </div>
        @endif

        <div class="saas-card" data-tour="coaching-insights">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">نقاط قوت پرتکرار</h2>
                    <p class="mt-1 text-sm text-zinc-500">آنچه در مکالمات موفق شما بیشتر تکرار شده</p>
                </div>
                @if ($hasStrengths)
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                        {{ $totalStrengthMentions }} بار در {{ $analyzedCount }} تماس
                    </span>
                @endif
            </div>

            @if ($hasStrengths)
                <div class="mt-6 grid gap-6 lg:grid-cols-5">
                    <div class="lg:col-span-3">
                        <div
                            class="rounded-xl border border-emerald-100/80 bg-gradient-to-br from-emerald-50/50 to-white p-4 dark:border-emerald-900/30 dark:from-emerald-950/20 dark:to-zinc-900"
                            style="height: {{ $strengthChartHeight }}px"
                            wire:key="strengths-chart-{{ md5(json_encode($strengthsRanked)) }}-{{ $period }}"
                        >
                            <canvas id="coaching-strengths-chart" data-report-chart data-type="bar" data-config='@json($strengthsChart)'></canvas>
                        </div>
                    </div>

                    <ol class="space-y-3 lg:col-span-2">
                        @foreach ($strengthsRanked as $index => $row)
                            @php
                                $share = $totalStrengthMentions > 0 ? round(($row['count'] / $totalStrengthMentions) * 100) : 0;
                                $barWidth = $maxStrength > 0 ? min(100, round(($row['count'] / $maxStrength) * 100)) : 0;
                                $isTop = $index < 3;
                            @endphp
                            <li
                                wire:key="strength-{{ md5($row['item']) }}"
                                @class([
                                    'rounded-xl border p-3 transition',
                                    'border-emerald-200/80 bg-emerald-50/60 dark:border-emerald-800/50 dark:bg-emerald-950/25' => $isTop,
                                    'border-zinc-200/80 bg-zinc-50/50 dark:border-zinc-800 dark:bg-zinc-900/40' => ! $isTop,
                                ])
                            >
                                <div class="flex items-start gap-3">
                                    <span @class([
                                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold tabular-nums',
                                        'bg-emerald-500 text-white shadow-sm' => $index === 0,
                                        'bg-emerald-200 text-emerald-900 dark:bg-emerald-800 dark:text-emerald-100' => $index === 1,
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200' => $index === 2,
                                        'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => $index > 2,
                                    ])>{{ $index + 1 }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-sm font-medium leading-6 text-zinc-800 dark:text-zinc-200">{{ $row['item'] }}</p>
                                            <div class="shrink-0 text-left">
                                                <p class="text-sm font-bold tabular-nums text-emerald-700 dark:text-emerald-400">{{ $row['count'] }}</p>
                                                <p class="text-[11px] text-zinc-500">{{ $share }}٪</p>
                                            </div>
                                        </div>
                                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-emerald-100/80 dark:bg-emerald-950/50">
                                            <div
                                                class="h-full rounded-full bg-gradient-to-l from-emerald-500 to-emerald-400 transition-all duration-700 ease-out"
                                                style="width: {{ $barWidth }}%"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state
                        title="{{ __('ui.empty.no_strengths.title') }}"
                        description="{{ __('ui.empty.no_strengths.description') }}"
                    />
                </div>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="saas-card">
                <h2 class="text-lg font-semibold">نقاط قابل بهبود</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    @if ($weaknessesDerived ?? false)
                        بر اساس ابعاد عملکرد، نگرانی‌ها و فرصت‌های ازدست‌رفته در این بازه
                    @else
                        اولویت‌بندی بر اساس تکرار در تحلیل‌ها
                    @endif
                </p>
                <ul class="mt-5 space-y-4">
                    @forelse ($weaknessesRanked as $row)
                        <li wire:key="weakness-{{ md5($row['item']) }}">
                            <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $row['item'] }}</span>
                                <span class="shrink-0 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">{{ $row['count'] }} بار</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-amber-100 dark:bg-amber-950/40">
                                <div
                                    class="h-full rounded-full bg-amber-500 transition-all"
                                    style="width: {{ min(100, round(($row['count'] / $maxWeakness) * 100)) }}%"
                                ></div>
                            </div>
                        </li>
                    @empty
                        <x-saas.empty-state
                            title="{{ __('ui.empty.no_improvements.title') }}"
                            description="{{ __('ui.empty.no_improvements.description') }}"
                        />
                    @endforelse
                </ul>
            </div>
        </div>

        @if (! empty($recommendations))
            <div class="saas-card">
                <h2 class="text-lg font-semibold">تمرکز مربیگری</h2>
                <p class="mt-1 text-sm text-zinc-500">اقدامات پیشنهادی با اولویت</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($recommendations as $rec)
                        <div class="rounded-xl border border-zinc-200/80 p-4 dark:border-zinc-800">
                            <span @class([
                                'rounded-md px-2 py-0.5 text-xs font-medium',
                                'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $rec['priority'] === 'high',
                                'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' => $rec['priority'] === 'medium',
                                'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => $rec['priority'] === 'low',
                            ])>{{ match($rec['priority']) { 'high' => 'اولویت بالا', 'medium' => 'متوسط', 'low' => 'پایین', default => $rec['priority'] } }}</span>
                            <p class="mt-2 font-medium">{{ $rec['topic'] }}</p>
                            <p class="mt-1 text-sm text-zinc-500">{{ $rec['tip'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="saas-card">
                <h2 class="text-lg font-semibold">اقدامات بعدی تماس‌ها</h2>
                <p class="mt-1 text-sm text-zinc-500">پیگیری‌هایی که هوش مصنوعی از مکالمات استخراج کرده</p>
                <ul class="mt-4 space-y-3">
                    @forelse ($followUps as $item)
                        <li wire:key="followup-{{ $item['analysis_id'] }}-{{ md5($item['action']) }}">
                            <a
                                href="{{ route('employee.calls.show', $item['analysis_id']) }}"
                                class="block rounded-lg border border-zinc-200/80 px-4 py-3 transition hover:border-indigo-300 hover:bg-indigo-50/40 dark:border-zinc-800 dark:hover:border-indigo-800 dark:hover:bg-indigo-950/20"
                            >
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $item['action'] }}</p>
                                <p class="mt-1 text-xs text-zinc-500">
                                    {{ shamsi($item['date'], 'datetime') }}
                                    @if ($item['score'])
                                        · امتیاز {{ $item['score'] }}
                                    @endif
                                </p>
                            </a>
                        </li>
                    @empty
                        <x-saas.empty-state
                            title="{{ __('ui.empty.no_followups.title') }}"
                            description="{{ __('ui.empty.no_followups.description') }}"
                        />
                    @endforelse
                </ul>
            </div>

            <div class="saas-card">
                <h2 class="text-lg font-semibold">مکالمات برای تمرین</h2>
                <p class="mt-1 text-sm text-zinc-500">تماس‌هایی با امتیاز پایین‌تر — برای مرور و یادگیری</p>
                <div class="mt-4 space-y-3">
                    @forelse ($practiceCalls as $call)
                        <a
                            href="{{ route('employee.calls.show', $call['analysis_id']) }}"
                            class="block rounded-lg border border-zinc-200/80 p-4 transition hover:border-amber-300 dark:border-zinc-800 dark:hover:border-amber-800"
                            wire:key="practice-{{ $call['analysis_id'] }}"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ $call['customer'] }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ $call['date'] }}</p>
                                </div>
                                <span @class(['rounded-lg px-2.5 py-1 text-sm font-bold tabular-nums', AgentPerformancePresenter::scoreTextClass($call['quality_score'] ?? null)])>
                                    {{ $call['quality_score'] ?? '—' }}
                                </span>
                            </div>
                            @if ($call['summary'] ?? null)
                                <p class="mt-2 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $call['summary'] }}</p>
                            @endif
                        </a>
                    @empty
                        <x-saas.empty-state
                            title="تماس مناسبی برای تمرین نیست"
                            description="این بازه تماس با امتیاز پایین ندارد — بازه زمانی را گسترده‌تر کنید یا به تحلیل تماس‌های بیشتر ادامه دهید."
                        />
                    @endforelse
                </div>
            </div>
        </div>

        @if (! empty($actionsRanked))
            <x-saas.analysis-insight-list
                title="پیشنهادهای عملی پرتکرار"
                :items="collect($actionsRanked)->map(fn ($row) => $row['item'].' ('.$row['count'].' بار)')->all()"
                tone="action"
            />
        @endif
    @endif
</div>
