@php
    use App\Support\AnalysisInsightPresenter;
    use App\Support\CustomerCompanyPresenter;
    use App\Support\CustomerPresenter;

    $scoreSeries = $analytics['score_series'] ?? [];
    $hasScoreSeries = count($scoreSeries) >= 1;
    $sentimentBreakdown = $analytics['sentiment_breakdown'] ?? [];
    $hasSentiment = count($sentimentBreakdown) > 0;
    $concerns = $analytics['concerns'] ?? [];
    $hasConcerns = count($concerns) > 0;

    $chartOptions = [
        'plugins' => ['legend' => ['display' => true, 'position' => 'bottom', 'rtl' => true]],
        'scales' => [
            'x' => ['ticks' => ['maxTicksLimit' => 8, 'autoSkip' => true], 'grid' => ['display' => false]],
            'y' => ['min' => 0, 'max' => 100, 'ticks' => ['stepSize' => 25]],
        ],
    ];

    $scoreChart = [
        'labels' => collect($scoreSeries)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'امتیاز تماس',
            'data' => collect($scoreSeries)->pluck('score')->all(),
            'borderColor' => 'rgb(99, 102, 241)',
            'backgroundColor' => 'rgba(99, 102, 241, 0.08)',
            'fill' => true,
            'tension' => 0.35,
            'pointRadius' => 4,
            'borderWidth' => 2,
        ]],
        'options' => $chartOptions,
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
            'borderWidth' => 0,
        ]],
        'options' => ['plugins' => ['legend' => ['position' => 'bottom', 'rtl' => true]]],
    ];

    $concernChart = [
        'labels' => collect($concerns)->pluck('label')->all(),
        'datasets' => [[
            'label' => 'تعداد',
            'data' => collect($concerns)->pluck('count')->all(),
            'backgroundColor' => 'rgba(99, 102, 241, 0.75)',
            'borderRadius' => 6,
        ]],
        'options' => [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['stepSize' => 1]],
                'y' => ['grid' => ['display' => false]],
            ],
        ],
    ];
@endphp

<div class="saas-page space-y-8">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <nav class="text-sm text-zinc-500">
        <a href="{{ $hubRoute ?? $indexRoute }}" class="hover:text-indigo-600" wire:navigate>مشتریان</a>
        <span class="mx-2">/</span>
        <a href="{{ $indexRoute }}" class="hover:text-indigo-600" wire:navigate>سازمان‌ها</a>
        <span class="mx-2">/</span>
        <span class="text-zinc-800 dark:text-zinc-200">{{ $company->displayName() }}</span>
    </nav>

    @include('livewire.shared.customers.partials.section-nav', ['portal' => $portal, 'active' => 'companies'])

    <section class="saas-hero saas-hero--accent" data-tour="company-profile">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-5">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-2xl font-bold text-white shadow-lg shadow-indigo-500/20">
                    {{ mb_substr($company->displayName(), 0, 1) }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-indigo-600 dark:text-indigo-400">سازمان مشتری</p>
                    <h1 class="text-3xl font-semibold tracking-tight">{{ $company->displayName() }}</h1>
                    <p class="mt-1 text-zinc-500">{{ CustomerCompanyPresenter::subtitle($company) }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($company->conversation_trend)
                            <span @class(['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CustomerCompanyPresenter::trendBadgeClass($company->conversation_trend)])>
                                {{ CustomerCompanyPresenter::trendLabel($company->conversation_trend) }}
                            </span>
                        @endif
                        @if ($company->latest_lead_level)
                            <span @class(['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CustomerCompanyPresenter::leadBadgeClass($company->latest_lead_level)])>
                                لید {{ AnalysisInsightPresenter::leadLevelLabel($company->latest_lead_level) }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $companyEditRoute }}" class="saas-btn-primary text-sm" wire:navigate>ویرایش سازمان</a>
                <a href="{{ $indexRoute }}" class="saas-btn-secondary text-sm" wire:navigate>بازگشت</a>
            </div>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-tour="company-stats">
        <x-saas.stat-card label="مخاطبان" :value="$summary['contacts']" hint="افراد متصل به این سازمان" />
        <x-saas.stat-card label="کل تماس‌ها" :value="$summary['total_calls']" />
        <x-saas.stat-card label="تماس‌های تحلیل‌شده" :value="$analytics['analyzed_calls']" />
        <x-saas.stat-card label="میانگین امتیاز" :value="$analytics['average_score'] ?: '—'" />
    </div>

    <section class="space-y-4" data-tour="company-contacts">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">مخاطبان سازمان</h2>
                <p class="mt-1 text-sm text-zinc-500">افراد مرتبط با این سازمان — برای جزئیات روی هر کارت کلیک کنید</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($company->contacts as $contact)
                <x-saas.customer-card
                    :customer="$contact"
                    :href="route($contactShowRouteName, $contact)"
                    wire:key="contact-{{ $contact->id }}"
                />
            @empty
                <div class="col-span-full">
                    <x-saas.empty-state
                        title="هنوز مخاطبی ثبت نشده"
                        description="با تحلیل تماس‌ها یا ویرایش مخاطبان، افراد به این سازمان متصل می‌شوند."
                    />
                </div>
            @endforelse
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2" data-tour="company-analytics">
        <div class="saas-card">
            <h2 class="text-lg font-semibold">روند امتیاز سازمان</h2>
            <p class="mt-1 text-sm text-zinc-500">میانگین امتیاز تماس‌های همه مخاطبان</p>
            @if ($hasScoreSeries)
                <div class="mt-4 h-56" wire:ignore>
                    <canvas id="company-score-trend" data-report-chart data-type="line" data-config='@json($scoreChart)'></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.chart_trend.title') }}" description="{{ __('ui.empty.chart_trend.description') }}" />
                </div>
            @endif
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">توزیع احساسات</h2>
            @if ($hasSentiment)
                <div class="mt-4 h-56" wire:ignore>
                    <canvas id="company-sentiment-chart" data-report-chart data-type="doughnut" data-config='@json($sentimentChart)'></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.chart_sentiment.title') }}" description="{{ __('ui.empty.chart_sentiment.description') }}" />
                </div>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($hasConcerns)
                <div class="saas-card">
                    <h2 class="text-lg font-semibold">نگرانی‌های پرتکرار</h2>
                    <div class="mt-4 h-48" wire:ignore>
                        <canvas id="company-concerns-chart" data-report-chart data-type="bar" data-config='@json($concernChart)'></canvas>
                    </div>
                </div>
            @endif

            @if (! empty($nextActions))
                <x-saas.analysis-insight-list title="اقدامات پیشنهادی" :items="$nextActions" tone="warning" />
            @endif
        </div>

        <div class="space-y-6">
            <div class="saas-card">
                <h2 class="text-lg font-semibold">اطلاعات سازمان</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">تلفن</dt><dd class="font-medium">{{ $company->phone ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">ایمیل</dt><dd class="font-medium">{{ $company->email ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">وب‌سایت</dt><dd class="font-medium">{{ $company->website ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">آدرس</dt><dd class="font-medium text-left">{{ $company->address ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">آخرین تماس</dt><dd class="font-medium">{{ shamsi($company->last_contact_at) }}</dd></div>
                </dl>
                @if ($company->notes)
                    <p class="mt-4 rounded-lg bg-zinc-50 p-3 text-sm leading-7 text-zinc-600 dark:bg-zinc-900/60 dark:text-zinc-300">{{ $company->notes }}</p>
                @endif
            </div>

            <div class="saas-card">
                <h2 class="text-lg font-semibold">کارشناسان مرتبط</h2>
                <ul class="mt-4 space-y-3">
                    @forelse ($employees as $employee)
                        <li><x-saas.user-cell :employee="$employee" :subtitle="$employee->department" avatar-size="xs" /></li>
                    @empty
                        <li class="text-sm text-zinc-500">هنوز کارشناسی ثبت نشده</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="saas-card">
        <h2 class="text-lg font-semibold">تاریخچه تماس‌های سازمان</h2>
        <p class="mt-1 text-sm text-zinc-500">{{ count($timeline) }} تماس از همه مخاطبان</p>
        <div class="mt-6 space-y-4">
            @forelse ($timeline as $item)
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800" wire:key="company-timeline-{{ $item['call_id'] }}">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-medium">{{ $item['customer_name'] }}</p>
                            <p class="text-sm text-zinc-500">{{ $item['date'] }} · {{ $item['employee_name'] }}</p>
                        </div>
                        @if ($item['score'])
                            <span class="saas-badge bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">امتیاز {{ $item['score'] }}</span>
                        @endif
                    </div>
                    @if ($item['summary'])
                        <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ \Illuminate\Support\Str::limit($item['summary'], 220) }}</p>
                    @endif
                    @if ($item['analysis_id'])
                        <a href="{{ route($analysisShowRoute, $item['analysis_id']) }}" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:underline">مشاهده تحلیل</a>
                    @endif
                </div>
            @empty
                <x-saas.empty-state title="{{ __('ui.empty.no_calls.title') }}" description="تماس‌های این سازمان پس از تحلیل اینجا نمایش داده می‌شوند." />
            @endforelse
        </div>
    </div>
</div>
