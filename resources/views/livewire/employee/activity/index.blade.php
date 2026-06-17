@php
    use App\Support\AgentPerformancePresenter;

    $filterLoadingTargets = 'setPeriod,setType,updatedSearch';

    $volumeChart = [
        'labels' => collect($volumeTrend)->pluck('label')->all(),
        'datasets' => [
            [
                'label' => 'تحلیل‌ها',
                'data' => collect($volumeTrend)->pluck('analyses')->all(),
                'backgroundColor' => 'rgba(99, 102, 241, 0.75)',
                'borderRadius' => 5,
                'stack' => 'activity',
            ],
            [
                'label' => 'بارگذاری‌ها',
                'data' => collect($volumeTrend)->pluck('uploads')->all(),
                'backgroundColor' => 'rgba(14, 165, 233, 0.75)',
                'borderRadius' => 5,
                'stack' => 'activity',
            ],
        ],
        'options' => [
            'plugins' => ['legend' => ['display' => true, 'position' => 'top']],
            'scales' => [
                'x' => ['ticks' => ['maxTicksLimit' => 8, 'autoSkip' => true], 'grid' => ['display' => false]],
                'y' => ['min' => 0, 'ticks' => ['stepSize' => 1]],
            ],
        ],
    ];

    $hasVolumeTrend = collect($volumeTrend)->contains(fn (array $p) => $p['total'] > 0);
    $analyzedDeltaLabel = ($summary['analyzed_delta'] >= 0 ? '+' : '') . $summary['analyzed_delta'] . ' نسبت به دوره قبل';
@endphp

<div class="saas-page space-y-6" wire:loading.class="opacity-60" wire:target="{{ $filterLoadingTargets }}">
    <x-saas.filter-loading-overlay :target="$filterLoadingTargets" />

    <x-saas.page-header
        title="فعالیت اخیر"
        description="روندی از تماس‌ها، تحلیل‌ها و پیگیری‌های شما — برای دیدن جزئیات هر بازه را انتخاب کنید."
        eyebrow="کارشناس"
        data-tour="page-header"
    >
        <x-slot:actions>
            <a href="{{ route('employee.calls') }}" class="saas-btn-secondary text-sm">تماس‌های من</a>
            <a href="{{ route('employee.performance', ['preset' => $period]) }}" class="saas-btn-primary text-sm">عملکرد من</a>
        </x-slot:actions>
    </x-saas.page-header>

    {{-- Hero --}}
    <section class="saas-hero saas-hero--accent" data-tour="activity-hero">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-5">
                <x-saas.avatar :employee="$membership" size="xl" ring />
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">فعالیت اخیر</p>
                    <h2 class="text-2xl font-bold tracking-tight">خلاصه فعالیت {{ $membership->first_name }}</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        در این بازه
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $summary['total_events'] }}</span>
                        رویداد؛
                        {{ $summary['analyzed_count'] }} تحلیل و {{ $summary['upload_count'] }} بارگذاری.
                    </p>
                    @if ($summary['last_activity'])
                        <p class="mt-1 text-xs text-zinc-500">آخرین فعالیت: {{ $summary['last_activity'] }}</p>
                    @endif
                </div>
            </div>
            <x-saas.score-ring
                :score="$summary['average_score'] ?: null"
                size="lg"
                label="میانگین کیفیت"
            />
        </div>
    </section>

    {{-- Period filters --}}
    @include('livewire.employee.activity.partials.period-filters', [
        'periodPresets' => $periodPresets,
        'activePreset' => $activePreset,
    ])

    {{-- KPI row --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-tour="activity-stats">
        <x-saas.stat-card
            label="تحلیل‌های دریافت‌شده"
            :value="$summary['analyzed_count']"
            :hint="$analyzedDeltaLabel"
            :trend="$summary['analyzed_delta']"
        />
        <x-saas.stat-card
            label="تماس‌های بارگذاری‌شده"
            :value="$summary['upload_count']"
            hint="در بازه انتخاب‌شده"
        />
        <x-saas.stat-card
            label="بازخوردهای هوش مصنوعی"
            :value="$summary['feedback_count']"
            hint="مکالمه با نظر جامع"
        />
        <x-saas.stat-card
            label="میانگین امتیاز"
            :value="$summary['average_score'] ?: '—'"
            hint="کیفیت مکالمات"
        />
    </div>

    {{-- Volume chart --}}
    <div class="saas-card" data-tour="activity-chart">
        <h2 class="text-lg font-semibold">حجم فعالیت روزانه</h2>
        <p class="mt-1 text-sm text-zinc-500">تعداد تحلیل‌ها و بارگذاری‌ها به تفکیک روز</p>
        @if ($hasVolumeTrend)
            <div class="mt-4 h-52" wire:ignore>
                <canvas id="activity-volume-chart" data-report-chart data-type="bar" data-config='@json($volumeChart)'></canvas>
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

    {{-- Type filter + search --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" data-tour="activity-timeline">
        <div class="flex flex-wrap gap-2">
            @foreach ([
                'all' => 'همه رویدادها',
                'analysis' => 'تحلیل‌ها',
                'upload' => 'بارگذاری‌ها',
                'feedback' => 'بازخوردها',
            ] as $value => $label)
                <button
                    type="button"
                    wire:click="setType('{{ $value }}')"
                    @class([
                        'rounded-md px-3 py-1.5 text-xs font-medium transition',
                        'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $activeType === $value,
                        'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300' => $activeType !== $value,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>

        <div class="relative max-w-xs w-full">
            <input
                type="search"
                wire:model.live.debounce.400ms="search"
                placeholder="جستجو در رویدادها..."
                class="w-full rounded-lg border border-zinc-300 bg-white py-2 pr-9 pl-3 text-sm shadow-sm placeholder:text-zinc-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
            />
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-zinc-400">
                <x-saas.icon name="search" class="h-4 w-4" />
            </span>
        </div>
    </div>

    {{-- Timeline --}}
    <section>
        @if (count($timeline) === 0)
            <div class="saas-card">
                <x-saas.empty-state
                    title="{{ __('ui.empty.no_results_filter.title') }}"
                    description="{{ __('ui.empty.no_results_filter.description') }}"
                />
            </div>
        @else
            <div class="relative">
                {{-- vertical guide line --}}
                <div class="absolute right-5 top-0 bottom-0 w-px bg-zinc-200 dark:bg-zinc-800" aria-hidden="true"></div>

                <ul class="space-y-4 pr-14">
                    @foreach ($timeline as $event)
                        @php
                            $typeConfig = match ($event['type']) {
                                'analysis' => [
                                    'dot' => 'bg-indigo-500',
                                    'icon' => 'chart',
                                    'badge' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300',
                                    'label' => 'تحلیل',
                                ],
                                'feedback' => [
                                    'dot' => 'bg-violet-500',
                                    'icon' => 'sparkles',
                                    'badge' => 'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300',
                                    'label' => 'بازخورد',
                                ],
                                'upload' => [
                                    'dot' => 'bg-sky-500',
                                    'icon' => 'upload',
                                    'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
                                    'label' => 'بارگذاری',
                                ],
                                default => [
                                    'dot' => 'bg-zinc-400',
                                    'icon' => 'activity',
                                    'badge' => 'bg-zinc-100 text-zinc-600',
                                    'label' => 'رویداد',
                                ],
                            };
                        @endphp

                        <li class="relative flex gap-4">
                            {{-- dot --}}
                            <div class="absolute -right-14 flex h-10 w-10 items-center justify-center rounded-full border-2 border-white bg-white shadow-sm dark:border-zinc-900 dark:bg-zinc-900">
                                <span @class(['h-3 w-3 rounded-full', $typeConfig['dot']])></span>
                            </div>

                            <a
                                href="{{ $event['url'] }}"
                                class="block w-full rounded-xl border border-zinc-200/80 bg-white p-4 transition hover:border-indigo-300 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-800"
                            >
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span @class(['rounded-md px-2 py-0.5 text-xs font-medium', $typeConfig['badge']])>
                                                {{ $typeConfig['label'] }}
                                            </span>
                                            <p class="font-semibold text-zinc-900 dark:text-white">{{ $event['customer'] }}</p>
                                            <span class="text-xs text-zinc-400">{{ $event['time'] }}</span>
                                        </div>
                                        <p class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $event['title'] }}</p>
                                        @if ($event['description'])
                                            <p class="mt-1 line-clamp-2 text-sm text-zinc-500">{{ $event['description'] }}</p>
                                        @endif
                                    </div>

                                    <div class="flex shrink-0 flex-wrap items-center gap-2 text-sm sm:justify-end">
                                        @if ($event['duration_label'])
                                            <span class="rounded-lg bg-zinc-100 px-2.5 py-1 font-medium dark:bg-zinc-800">{{ $event['duration_label'] }}</span>
                                        @endif
                                        @if ($event['score'] !== null)
                                            <span @class(['rounded-lg px-2.5 py-1 font-bold tabular-nums', AgentPerformancePresenter::scoreTextClass($event['score'])])>
                                                {{ $event['score'] }}
                                            </span>
                                        @endif
                                        @if ($event['sentiment'])
                                            <span class="text-xs text-zinc-400">{{ $event['sentiment'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    {{-- Bottom two-column: feedback + follow-ups --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="saas-card">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">بازخوردهای اخیر</h2>
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($recentFeedback as $item)
                    <a
                        href="{{ route('employee.calls.show', $item['analysis_id']) }}"
                        class="block rounded-lg border border-zinc-200 p-3 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium text-sm truncate">{{ $item['customer'] }}</p>
                            <div class="flex shrink-0 items-center gap-2">
                                @if ($item['score'])
                                    <span @class(['text-xs font-bold', AgentPerformancePresenter::scoreTextClass($item['score'])])>{{ $item['score'] }}</span>
                                @endif
                                <span class="text-xs text-zinc-400">{{ $item['time'] }}</span>
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-zinc-500 line-clamp-2">{{ $item['feedback'] }}</p>
                    </a>
                @empty
                    <x-saas.empty-state
                        title="هنوز بازخوردی دریافت نکرده‌اید"
                        description="پس از تحلیل تماس‌ها، ارزیابی‌های هوش مصنوعی اینجا نمایش داده می‌شود."
                    />
                @endforelse
            </div>
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">پیگیری‌های من</h2>
            <p class="mt-1 text-sm text-zinc-500">اقدامات پیشنهادی هوش مصنوعی از مکالمات اخیر</p>
            <ul class="mt-4 space-y-2">
                @forelse ($followUps as $item)
                    <li class="flex items-start gap-3 rounded-lg border border-zinc-100 bg-zinc-50 px-3 py-2.5 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-violet-500"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-zinc-800 dark:text-zinc-200">{{ $item['action'] }}</p>
                            <p class="mt-0.5 text-xs text-zinc-400">{{ $item['time'] }}</p>
                        </div>
                        <a href="{{ route('employee.calls.show', $item['analysis_id']) }}" class="shrink-0 text-xs text-indigo-600 hover:underline dark:text-indigo-400">مشاهده</a>
                    </li>
                @empty
                    <x-saas.empty-state
                        title="{{ __('ui.empty.no_followups.title') }}"
                        description="{{ __('ui.empty.no_followups.description') }}"
                    />
                @endforelse
            </ul>
        </div>
    </div>
</div>
