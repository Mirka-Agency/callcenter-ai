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
    <section class="saas-hero saas-hero--welcome" data-tour="dashboard-hero">
        <div class="saas-hero-welcome">
            <div class="saas-hero-welcome__brand">
                <img
                    src="{{ asset('images/dashboard/hero-brand.jpg') }}"
                    alt="آوایار — شنیدن، درک کردن، فرصت ساختن"
                    width="1024"
                    height="768"
                    decoding="async"
                >
            </div>

            <div class="saas-hero-welcome__stats">
                <p class="saas-hero-welcome__org">
                    <x-saas.icon name="building" class="saas-hero-welcome__org-icon h-4 w-4 shrink-0" />
                    <span>سازمان: {{ $organization->title }}</span>
                </p>
                <p class="saas-hero-welcome__metric-label">تعداد تماس‌های امروز</p>
                <p class="saas-hero-welcome__metric-value tabular-nums">
                    {{ \App\Support\PersianNumber::format($cockpit['calls_today'], 0) ?? '۰' }}
                </p>
                <div class="saas-hero-welcome__meta">
                    <span class="saas-hero-welcome__live">
                        <span class="saas-hero-welcome__live-dot" aria-hidden="true"></span>
                        به‌روزرسانی لحظه‌ای
                    </span>
                    <span class="saas-hero-welcome__meta-sep" aria-hidden="true"></span>
                    <span class="saas-hero-welcome__today">
                        <x-saas.icon name="calendar" class="h-3.5 w-3.5 shrink-0" />
                        امروز
                    </span>
                </div>
            </div>

            <div class="saas-hero-welcome__visual" aria-hidden="true">
                <div class="saas-hero-welcome__orbit">
                    <span class="saas-hero-welcome__ring saas-hero-welcome__ring--outer"></span>
                    <span class="saas-hero-welcome__ring saas-hero-welcome__ring--mid"></span>
                    <span class="saas-hero-welcome__orb">
                        <x-saas.icon name="phone" class="h-7 w-7 text-white sm:h-8 sm:w-8" />
                    </span>
                    <span class="saas-hero-welcome__dot saas-hero-welcome__dot--a"></span>
                    <span class="saas-hero-welcome__dot saas-hero-welcome__dot--b"></span>
                    <span class="saas-hero-welcome__dot saas-hero-welcome__dot--c"></span>
                </div>
            </div>

            <div class="saas-hero-welcome__copy">
                <p class="saas-hero-welcome__greet">سلام 👋</p>
                <h1 class="saas-hero-welcome__title">به داشبورد آوایار خوش آمدید</h1>
                <p class="saas-hero-welcome__subtitle">
                    امروز هم یک قدم به ارتباطات بهتر و مشتریان رضایت‌مندتر نزدیک‌تر هستیم.
                </p>
            </div>
        </div>
    </section>

    @php
        $analysisBasis = fn (int $count): string => 'براساس '.\App\Support\PersianNumber::format($count, 0).' تماس در ۳۰ روز گذشته محاسبه شد';
    @endphp

    <div data-tour="dashboard-stats">
        <div class="saas-stat-row">
            <x-saas.stat-card class="saas-stat--compact" label="کارشناسان فعال" :value="$teamKpis['active_employees']" />
            <x-saas.stat-card class="saas-stat--compact" label="تماس‌های تحلیل‌شده" :value="$teamKpis['total_analyzed']" />
            <x-saas.stat-card class="saas-stat--compact" label="میانگین امتیاز تیم" :value="$teamKpis['average_quality_score'] ?: '—'" :comparison="$teamKpis['average_quality_score'] ? $monthComparisons['average_quality_score'] : null" :tone="\App\Support\MetricTone::fromScore($teamKpis['average_quality_score'])" :tooltip="$analysisBasis($teamKpis['quality_sample_count'])" />
            <x-saas.stat-card class="saas-stat--compact" label="میانگین کیفیت لید" :value="$teamKpis['average_lead_score'] ?: '—'" :comparison="$teamKpis['average_lead_score'] ? $monthComparisons['average_lead_score'] : null" :tone="\App\Support\MetricTone::fromScore($teamKpis['average_lead_score'])" :tooltip="$analysisBasis($teamKpis['lead_sample_count'])" />
            <x-saas.stat-card class="saas-stat--compact" label="رضایت مشتری" :value="$teamKpis['average_sentiment'] ? $teamKpis['average_sentiment'].'%' : '—'" :comparison="$teamKpis['average_sentiment'] ? $monthComparisons['average_sentiment'] : null" comparison-unit="٪" :tone="\App\Support\MetricTone::fromScore($teamKpis['average_sentiment'])" :tooltip="$analysisBasis($teamKpis['sentiment_sample_count'])" />
        </div>
    </div>

    @include('livewire.employer.partials.today-summary')

    <div @class([
        'grid items-stretch gap-6',
        'lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]' => ! empty($teamWeaknesses),
    ])>
        @include('livewire.employer.partials.team-weaknesses-card', [
            'teamWeaknesses' => $teamWeaknesses,
            'selectedTeamWeakness' => $selectedTeamWeakness,
            'teamWeaknessCalls' => $teamWeaknessCalls,
            'variant' => 'table',
            'cardClass' => 'h-full',
        ])

        <div
            class="saas-card h-full min-w-0"
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

    @include('livewire.employer.partials.trading-opportunities-card', [
        'tradingOpportunities' => $tradingOpportunities,
    ])

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
        @include('livewire.employer.partials.sentiment-customers-card', [
            'sentimentCustomers' => $sentimentCustomers,
        ])

        @include('livewire.employer.partials.forgotten-follow-ups-card', [
            'forgottenFollowUps' => $forgottenFollowUps,
        ])
    </div>
</div>
