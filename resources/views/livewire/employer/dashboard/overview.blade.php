@php
    $qualityChart = [
        'labels' => collect($qualityTrend)->pluck('label')->all(),
        'tooltipTitles' => collect($qualityTrend)->pluck('tooltip_label')->all(),
        'tooltipBodies' => collect($qualityTrend)->pluck('tooltip_body')->all(),
        'datasets' => [[
            'label' => 'میانگین امتیاز تیم',
            'data' => collect($qualityTrend)->pluck('avg_score')->all(),
            'borderColor' => 'rgb(99, 102, 241)',
            'backgroundColor' => 'rgba(99, 102, 241, 0.12)',
            'pointRadius' => 0,
            'pointHoverRadius' => 6,
            'pointHitRadius' => 20,
            'fill' => true,
            'tension' => 0.35,
            'spanGaps' => true,
        ]],
    ];
@endphp

<div class="saas-page">
    <section class="saas-hero" data-tour="dashboard-hero">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-wider text-indigo-600 dark:text-indigo-400">داشبورد مدیر</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">{{ $organization->title }}</h1>
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

    <div class="flex justify-center" data-tour="dashboard-stats">
        <div class="saas-stat-row">
            <x-saas.stat-card class="saas-stat--compact" label="تماس‌های امروز" :value="$cockpit['calls_today']" />
            <x-saas.stat-card class="saas-stat--compact" label="میانگین کیفیت لید" :value="$teamKpis['average_lead_score'] ?: '—'" :tone="\App\Support\MetricTone::fromScore($teamKpis['average_lead_score'])" />
            <x-saas.stat-card class="saas-stat--compact" label="رضایت مشتری" :value="$teamKpis['average_sentiment'] ? $teamKpis['average_sentiment'].'%' : '—'" :tone="\App\Support\MetricTone::fromScore($teamKpis['average_sentiment'])" />
        </div>
    </div>

    @include('livewire.employer.partials.trading-opportunities-card', [
        'tradingOpportunities' => $tradingOpportunities,
    ])

    @include('livewire.employer.partials.sentiment-customers-card', [
        'sentimentCustomers' => $sentimentCustomers,
    ])

    @include('livewire.employer.partials.team-weaknesses-card', [
        'teamWeaknesses' => $teamWeaknesses,
        'selectedTeamWeakness' => $selectedTeamWeakness,
        'teamWeaknessCalls' => $teamWeaknessCalls,
    ])

    @include('livewire.employer.partials.agent-performance-cards', [
        'title' => 'عملکرد کارشناسان',
        'subtitle' => $agentCardFeed['counts']['all'].' کارشناس با فعالیت در ۳۰ روز اخیر',
        'agentCardFeed' => $agentCardFeed,
        'agentCardFilter' => $agentCardFilter,
        'agentProfileUrl' => fn (array $agent) => route('employer.intelligence.performance.show', $agent['id']),
        'emptyTitle' => __('ui.empty.no_team_performance.title'),
        'emptyDescription' => __('ui.empty.no_team_performance.description'),
        'sectionTour' => 'dashboard-agents',
        'showPerformanceLink' => true,
    ])

    @include('livewire.employer.partials.forgotten-follow-ups-card', [
        'forgottenFollowUps' => $forgottenFollowUps,
    ])

    <div
        class="saas-card"
        data-tour="dashboard-quality"
        data-quality-trend-card
        x-data="qualityTrendCard({{ \Illuminate\Support\Js::from($qualityTrendInsights ?? []) }}, {{ \Illuminate\Support\Js::from($agentProfileBase ?? '') }})"
        @quality-trend-select="select($event.detail.period)"
    >
            <h2 class="text-lg font-semibold">روند کیفیت تیم</h2>
            <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز مکالمه در بازه ۳۰ روز اخیر. برای دیدن دلیل تغییر، روی یک نقطه کلیک کنید.</p>
            <div data-drilldown-selected="{{ $selectedQualityTrendPeriod ?? '' }}" :data-drilldown-selected="selected">
                <div class="relative mt-4 h-56" wire:ignore>
                    <canvas
                        id="dashboard-quality-trend"
                        class="cursor-pointer"
                        draggable="false"
                        data-report-chart
                        data-type="line"
                        data-config='@json($qualityChart)'
                        data-drilldown="period"
                        data-drilldown-values='@json(collect($qualityTrend)->pluck('period')->all())'
                    ></canvas>
                </div>
            </div>
            @include('livewire.employer.partials.quality-trend-insight-client')
            @if (! empty($qualityTrendInsight))
                <div x-show="!insight">
                    @include('livewire.employer.partials.quality-trend-insight', [
                        'qualityTrendInsight' => $qualityTrendInsight,
                    ])
                </div>
            @endif
    </div>
</div>
