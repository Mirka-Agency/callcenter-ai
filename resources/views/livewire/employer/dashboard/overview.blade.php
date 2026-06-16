@php
    use App\Support\AgentPerformancePresenter;

    $topCount = collect($agents)->where('tier', 'top')->count();
    $attentionCount = collect($agents)->where('tier', 'attention')->count();

    $qualityChart = [
        'labels' => collect($qualityTrend)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'میانگین امتیاز تیم',
            'data' => collect($qualityTrend)->pluck('avg_score')->all(),
            'borderColor' => 'rgb(99, 102, 241)',
            'backgroundColor' => 'rgba(99, 102, 241, 0.12)',
            'fill' => true,
            'tension' => 0.35,
        ]],
    ];
@endphp

<div class="saas-page">
    <section class="saas-hero">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-wider text-indigo-600 dark:text-indigo-400">داشبورد مدیر</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $organization->title }}</h1>
                <p class="mt-2 max-w-2xl text-zinc-500">
                    نمای کلی عملکرد کارشناسان در ۳۰ روز اخیر
                </p>
            </div>
            <div class="flex items-center gap-6">
                <x-saas.score-ring :score="$teamKpis['average_quality_score']" size="lg" label="میانگین امتیاز تیم" />
                <div class="hidden gap-4 sm:grid sm:grid-cols-2">
                    <div class="saas-inline-stat">
                        <p class="text-xs text-zinc-500">کارشناسان فعال</p>
                        <p class="text-2xl font-bold tabular-nums">{{ $teamKpis['active_employees'] }}</p>
                    </div>
                    <div class="saas-inline-stat">
                        <p class="text-xs text-zinc-500">تماس تحلیل‌شده</p>
                        <p class="text-2xl font-bold tabular-nums">{{ $teamKpis['total_analyzed'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <x-saas.smart-call-intelligence-card />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-saas.stat-card label="تماس‌های امروز" :value="$cockpit['calls_today']" />
        <x-saas.stat-card label="میانگین کیفیت لید" :value="$teamKpis['average_lead_score'] ?: '—'" />
        <x-saas.stat-card label="رضایت مشتری" :value="$teamKpis['average_sentiment'] ? $teamKpis['average_sentiment'].'%' : '—'" />
    </div>

    <section class="saas-section" x-data="{ filter: 'all' }">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="saas-section-title">عملکرد کارشناسان</h2>
                <p class="saas-section-subtitle">{{ count($agents) }} کارشناس با فعالیت در ۳۰ روز اخیر</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800'" class="rounded-lg px-3 py-1.5 text-sm font-medium transition">همه ({{ count($agents) }})</button>
                <button type="button" @click="filter = 'top'" :class="filter === 'top' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30'" class="rounded-lg px-3 py-1.5 text-sm font-medium transition">برترین‌ها ({{ $topCount }})</button>
                <button type="button" @click="filter = 'attention'" :class="filter === 'attention' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/30'" class="rounded-lg px-3 py-1.5 text-sm font-medium transition">نیازمند توجه ({{ $attentionCount }})</button>
                <a href="{{ route('employer.intelligence.performance') }}" class="saas-btn-secondary text-sm">گزارش کامل</a>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @forelse ($agents as $agent)
                <div x-show="filter === 'all' || filter === '{{ $agent['tier'] }}'" x-cloak>
                    <x-saas.agent-performance-card
                        :agent="$agent"
                        :href="route('employer.intelligence.performance.show', $agent['id'])"
                    />
                </div>
            @empty
                <div class="col-span-full">
                    <x-saas.empty-state
                        title="هنوز داده عملکردی ثبت نشده"
                        description="پس از تحلیل تماس‌های کارشناسان، کارت‌های عملکرد اینجا نمایش داده می‌شوند."
                    />
                </div>
            @endforelse
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="saas-card lg:col-span-2">
            <h2 class="text-lg font-semibold">روند کیفیت تیم</h2>
            <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز مکالمه در بازه ۳۰ روز اخیر</p>
            <div class="mt-4 h-56" wire:ignore>
                <canvas id="dashboard-quality-trend" data-report-chart data-type="line" data-config='@json($qualityChart)'></canvas>
            </div>
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">فعالیت اخیر</h2>
            <div class="mt-4 space-y-3">
                @forelse ($activityFeed as $activity)
                    <div class="border-b border-zinc-100 pb-3 last:border-0 dark:border-zinc-800">
                        <p class="text-sm font-medium">{{ $activity['title'] }}</p>
                        <p class="mt-0.5 text-xs text-zinc-500">{{ $activity['description'] }}</p>
                        <p class="mt-1 text-[11px] text-zinc-400">{{ $activity['time'] }}</p>
                    </div>
                @empty
                    <x-saas.empty-state title="فعالیتی ثبت نشده" />
                @endforelse
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('employer.intelligence.index') }}" class="saas-btn-secondary">تحلیل تماس‌ها</a>
        <a href="{{ route('employer.intelligence.performance') }}" class="saas-btn-primary">عملکرد کارشناسان</a>
        <a href="{{ route('employer.reports.index') }}" class="saas-btn-secondary">گزارش‌های مدیریتی</a>
    </div>
</div>
