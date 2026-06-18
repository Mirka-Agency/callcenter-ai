@php
    $scoreTrend = $cockpit['score_trend'];
    $sentimentTrend = $cockpit['sentiment_trend'];
    $hasScoreTrend = collect($scoreTrend)->contains(fn (array $point) => $point['avg_score'] !== null);
    $hasSentimentTrend = collect($sentimentTrend)->contains(fn (array $point) => $point['satisfaction'] !== null);

    $chartOptions = [
        'plugins' => ['legend' => ['display' => false]],
        'scales' => [
            'x' => ['ticks' => ['maxTicksLimit' => 7, 'autoSkip' => true], 'grid' => ['display' => false]],
            'y' => ['min' => 0, 'max' => 100, 'ticks' => ['stepSize' => 25]],
        ],
    ];

    $scoreChart = [
        'labels' => collect($scoreTrend)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'میانگین امتیاز',
            'data' => collect($scoreTrend)->pluck('avg_score')->all(),
            'borderColor' => 'rgb(99, 102, 241)',
            'backgroundColor' => 'rgba(99, 102, 241, 0.12)',
            'fill' => true,
            'tension' => 0.35,
            'spanGaps' => true,
            'pointRadius' => 3,
            'pointHoverRadius' => 5,
            'borderWidth' => 2,
        ]],
        'options' => $chartOptions,
    ];

    $sentimentChart = [
        'labels' => collect($sentimentTrend)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'رضایت مشتری (%)',
            'data' => collect($sentimentTrend)->pluck('satisfaction')->all(),
            'borderColor' => 'rgb(16, 185, 129)',
            'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
            'fill' => true,
            'tension' => 0.35,
            'spanGaps' => true,
            'pointRadius' => 3,
            'pointHoverRadius' => 5,
            'borderWidth' => 2,
        ]],
        'options' => $chartOptions,
    ];
@endphp

<div class="saas-page">
    <section class="saas-hero" data-tour="dashboard-hero">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:gap-5">
                <x-saas.avatar :employee="$membership" size="xl" ring />
                <div class="min-w-0">
                    <p class="text-sm font-medium uppercase tracking-wider text-indigo-600">داشبورد عملکرد</p>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">خوش آمدید، {{ $membership->first_name }}</h1>
                    <p class="mt-2 text-zinc-500">مرکز بینش شخصی شما — روند عملکرد، نقاط قوت و اقدامات پیشنهادی در یک نگاه.</p>
                </div>
            </div>
            <div class="flex w-full items-center justify-between gap-3 rounded-md bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-3 text-white shadow-sm sm:w-auto sm:justify-start">
            <div>
                <p class="text-xs uppercase opacity-80">امتیاز عملکرد</p>
                <p class="text-2xl font-bold sm:text-3xl">{{ $cockpit['performance_score'] ?: '—' }}</p>
            </div>
            @if ($cockpit['weekly_delta'] > 0)
                <span class="rounded-md bg-white/20 px-2 py-1 text-xs">+{{ $cockpit['weekly_delta'] }} این هفته</span>
            @endif
        </div>
        </div>
    </section>

    <x-saas.smart-call-intelligence-card :href="route('employee.uploads')" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-tour="dashboard-stats">
        <x-saas.stat-card label="پیشرفت هفتگی" :value="$cockpit['weekly_progress'] ?: '—'" :hint="($cockpit['weekly_delta'] >= 0 ? '+' : '').$cockpit['weekly_delta'].' نسبت به هفته قبل'" />
        <x-saas.stat-card label="پیشرفت ماهانه" :value="$cockpit['monthly_progress'] ?: '—'" :hint="($cockpit['monthly_delta'] >= 0 ? '+' : '').$cockpit['monthly_delta'].' نسبت به ماه قبل'" />
        <x-saas.stat-card label="تعداد تماس" :value="$cockpit['call_count']" :hint="$cockpit['analyzed_count'].' تحلیل‌شده'" />
        <x-saas.stat-card label="میانگین امتیاز تماس" :value="$cockpit['average_call_score'] ?: '—'" />
        <x-saas.stat-card label="رضایت مشتری" :value="$cockpit['customer_satisfaction'] ? $cockpit['customer_satisfaction'].'%' : '—'" />
        <x-saas.stat-card label="روند بهبود" :value="($cockpit['monthly_delta'] >= 0 ? '+' : '').$cockpit['monthly_delta']" hint="تغییر امتیاز ماهانه" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2" data-tour="dashboard-trends">
        <div class="saas-card">
            <h2 class="text-lg font-semibold">روند عملکرد</h2>
            <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز روزانه در ۳۰ روز اخیر</p>
            @if ($hasScoreTrend)
                <div class="mt-4 h-56" wire:ignore>
                    <canvas id="employee-score-trend" data-report-chart data-type="line" data-config='@json($scoreChart)'></canvas>
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
            <h2 class="text-lg font-semibold">روند رضایت مشتری</h2>
            <p class="mt-1 text-sm text-zinc-500">شاخص رضایت روزانه در ۳۰ روز اخیر</p>
            @if ($hasSentimentTrend)
                <div class="mt-4 h-56" wire:ignore>
                    <canvas id="employee-sentiment-trend" data-report-chart data-type="line" data-config='@json($sentimentChart)'></canvas>
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
    </div>

    <div class="grid gap-6 lg:grid-cols-3" data-tour="dashboard-summary">
        <div class="saas-card lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">عملکرد من</h2>
                <a href="{{ route('employee.performance') }}" class="text-sm text-indigo-600 hover:underline">مشاهده جزئیات</a>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-900">
                    <p class="text-sm text-zinc-500">بهترین اخیر</p>
                    <p class="text-2xl font-bold">{{ collect($scoreTrend)->pluck('avg_score')->filter()->max() ?: '—' }}</p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-900">
                    <p class="text-sm text-zinc-500">تماس‌های تحلیل‌شده</p>
                    <p class="text-2xl font-bold">{{ $cockpit['analyzed_count'] }}</p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-900">
                    <p class="text-sm text-zinc-500">توکن مصرف‌شده</p>
                    <p class="text-2xl font-bold">{{ number_format($cockpit['token_usage']) }}</p>
                </div>
            </div>
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">دستاوردهای من</h2>
            <div class="mt-4 space-y-3">
                @foreach ($achievements as $badge)
                    <div class="flex items-start gap-3 rounded-lg border border-amber-100 bg-amber-50/50 px-3 py-2 dark:border-amber-900/30 dark:bg-amber-950/20">
                        <span class="text-lg">🏆</span>
                        <div>
                            <p class="font-medium text-sm">{{ $badge['title'] }}</p>
                            <p class="text-xs text-zinc-500">{{ $badge['description'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2" data-tour="dashboard-strengths">
        <div class="saas-card">
            <h2 class="text-lg font-semibold">نقاط قوت پرتکرار</h2>
            <p class="mt-1 text-sm text-zinc-500">نکاتی که در تماس‌های اخیر شما بیشترین تکرار را داشته‌اند</p>
            <ul class="mt-4 space-y-3">
                @forelse ($topStrengths as $item)
                    <li class="flex items-start gap-2 rounded-lg bg-emerald-50/60 px-3 py-2 text-sm dark:bg-emerald-950/20">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                        <span class="flex-1">{{ $item['item'] }}</span>
                        <span class="shrink-0 text-xs text-zinc-500">{{ $item['count'] }} بار</span>
                    </li>
                @empty
                    <x-saas.empty-state
                        title="{{ __('ui.empty.no_strengths.title') }}"
                        description="{{ __('ui.empty.no_strengths.description') }}"
                    />
                @endforelse
            </ul>
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">نقاط قابل بهبود</h2>
            <p class="mt-1 text-sm text-zinc-500">
                @if ($weaknessesDerived ?? false)
                    بر اساس ابعاد عملکرد، نگرانی‌ها و فرصت‌های ازدست‌رفته در تماس‌های اخیر
                @else
                    مواردی که بیشتر در تحلیل‌ها نیازمند توجه بوده‌اند
                @endif
            </p>
            <ul class="mt-4 space-y-3">
                @forelse ($topWeaknesses as $item)
                    <li class="flex items-start gap-2 rounded-lg bg-amber-50/60 px-3 py-2 text-sm dark:bg-amber-950/20">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-500"></span>
                        <span class="flex-1">{{ $item['item'] }}</span>
                        <span class="shrink-0 text-xs text-zinc-500">{{ $item['count'] }} بار</span>
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

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="saas-card">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">تماس‌های من</h2>
                <a href="{{ route('employee.calls') }}" class="text-sm text-indigo-600 hover:underline">همه تماس‌ها</a>
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($recentCalls as $call)
                    <a href="{{ route('employee.calls.show', $call['id']) }}" class="block rounded-lg border border-zinc-200 px-4 py-3 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50">
                        <div class="flex justify-between">
                            <span class="font-medium">امتیاز {{ $call['score'] }}</span>
                            <span class="text-sm text-zinc-500">{{ $call['date'] }}</span>
                        </div>
                        <p class="mt-1 text-sm text-zinc-500">{{ Str::limit($call['summary'], 100) }}</p>
                    </a>
                @empty
                    <x-saas.empty-state
                        title="{{ __('ui.empty.no_calls.title') }}"
                        description="{{ __('ui.empty.no_calls.description') }}"
                    />
                @endforelse
            </div>
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">پیگیری‌های من</h2>
            <ul class="mt-4 space-y-2">
                @forelse ($followUps as $item)
                    <li class="flex items-start gap-2 rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-900">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-violet-500"></span>
                        <span>{{ $item['action'] }} <span class="text-zinc-400">· {{ $item['date'] }}</span></span>
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

    <div class="saas-card" data-tour="dashboard-recommendations">
        <p class="mt-1 text-sm text-zinc-500">حوزه‌های تمرکز مربیگری بر اساس گفتگوهای اخیر شما</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($recommendations as $rec)
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <span class="rounded-md px-2 py-0.5 text-xs {{ $rec['priority'] === 'high' ? 'bg-red-100 text-red-700' : ($rec['priority'] === 'medium' ? 'bg-amber-100 text-amber-700' : 'bg-zinc-100 text-zinc-600') }}">{{ match($rec['priority']) { 'high' => 'بالا', 'medium' => 'متوسط', 'low' => 'پایین', default => $rec['priority'] } }}</span>
                    <p class="mt-2 font-medium">{{ $rec['topic'] }}</p>
                    <p class="mt-1 text-sm text-zinc-500">{{ $rec['tip'] }}</p>
                </div>
            @empty
                <x-saas.empty-state
                    title="{{ __('ui.empty.no_recommendations.title') }}"
                    description="{{ __('ui.empty.no_recommendations.description') }}"
                />
            @endforelse
        </div>
    </div>
</div>
