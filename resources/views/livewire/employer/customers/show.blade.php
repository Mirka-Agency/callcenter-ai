@php
    use App\Support\AnalysisInsightPresenter;
    use App\Support\CustomerPresenter;

    $analysisShowRoute = $analysisShowRoute ?? 'employer.intelligence.show';
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
        'datasets' => array_values(array_filter([
            [
                'label' => 'امتیاز تماس',
                'data' => collect($scoreSeries)->pluck('score')->all(),
                'borderColor' => 'rgb(99, 102, 241)',
                'backgroundColor' => 'rgba(99, 102, 241, 0.08)',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 4,
                'pointHoverRadius' => 6,
                'borderWidth' => 2,
            ],
            collect($scoreSeries)->contains(fn (array $point) => $point['lead_score'] !== null) ? [
                'label' => 'امتیاز لید',
                'data' => collect($scoreSeries)->pluck('lead_score')->all(),
                'borderColor' => 'rgb(16, 185, 129)',
                'backgroundColor' => 'transparent',
                'fill' => false,
                'tension' => 0.35,
                'pointRadius' => 3,
                'pointHoverRadius' => 5,
                'borderWidth' => 2,
                'borderDash' => [4, 4],
                'spanGaps' => true,
            ] : null,
        ])),
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
            'borderSkipped' => false,
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
    <section class="saas-hero">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-5">
                <x-saas.avatar :name="$customer->displayName()" size="xl" ring />
                <div class="min-w-0">
                    <p class="text-sm font-medium text-indigo-600">پروفایل مشتری</p>
                    <h1 class="text-3xl font-semibold tracking-tight">{{ $customer->displayName() }}</h1>
                    <p class="mt-1 text-zinc-500">{{ CustomerPresenter::subtitle($customer) }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($customer->conversation_trend)
                            <span @class(['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CustomerPresenter::trendBadgeClass($customer->conversation_trend)])>
                                {{ CustomerPresenter::trendLabel($customer->conversation_trend) }}
                            </span>
                        @endif
                        @if ($customer->latest_lead_level)
                            <span @class(['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CustomerPresenter::leadBadgeClass($customer->latest_lead_level)])>
                                لید {{ AnalysisInsightPresenter::leadLevelLabel($customer->latest_lead_level) }}
                            </span>
                        @endif
                        @if ($customer->purchase_intent)
                            <span class="inline-flex rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300">
                                تمایل: {{ $customer->purchase_intent }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center lg:flex-col lg:items-end">
                @if ($customer->latest_lead_score)
                    <x-saas.dimension-ring
                        label="امتیاز لید"
                        :score="$customer->latest_lead_score"
                        size="lg"
                    />
                @endif
                <a href="{{ $isEmployer ? route('employer.customers.index') : route('employee.customers.index') }}" class="saas-btn-secondary text-sm">بازگشت به لیست</a>
            </div>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-saas.stat-card label="کل تماس‌ها" :value="$customer->total_calls" :hint="$customer->total_answered_calls.' پاسخ‌داده'" />
        <x-saas.stat-card label="تماس‌های تحلیل‌شده" :value="$analytics['analyzed_calls']" hint="بر اساس هوش مکالمه" />
        <x-saas.stat-card label="میانگین امتیاز" :value="$analytics['average_score'] ?: '—'" hint="از تماس‌های تحلیل‌شده" />
        <x-saas.stat-card
            label="نرخ پاسخ"
            :value="$analytics['answer_rate'] !== null ? $analytics['answer_rate'].'%' : '—'"
            :hint="$analytics['average_duration_label'] !== '—' ? 'میانگین مدت: '.$analytics['average_duration_label'] : null"
        />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="saas-card">
            <h2 class="text-lg font-semibold">روند امتیاز تماس‌ها</h2>
            <p class="mt-1 text-sm text-zinc-500">امتیاز هر تماس تحلیل‌شده به ترتیب زمان</p>
            @if ($hasScoreSeries)
                <div class="mt-4 h-56" wire:ignore>
                    <canvas id="customer-score-trend" data-report-chart data-type="line" data-config='@json($scoreChart)'></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="هنوز داده روندی وجود ندارد" description="پس از تحلیل تماس‌ها، نمودار امتیاز اینجا نمایش داده می‌شود." />
                </div>
            @endif
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">توزیع احساسات</h2>
            <p class="mt-1 text-sm text-zinc-500">احساس غالب در تماس‌های تحلیل‌شده</p>
            @if ($hasSentiment)
                <div class="mt-4 h-56" wire:ignore>
                    <canvas id="customer-sentiment-chart" data-report-chart data-type="doughnut" data-config='@json($sentimentChart)'></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="داده احساسات وجود ندارد" description="احساسات مشتری پس از تحلیل تماس‌ها ثبت می‌شود." />
                </div>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($hasConcerns)
                <div class="saas-card">
                    <h2 class="text-lg font-semibold">نگرانی‌های پرتکرار</h2>
                    <p class="mt-1 text-sm text-zinc-500">موضوعاتی که بیشترین تکرار را در مکالمات داشته‌اند</p>
                    <div class="mt-4 h-48" wire:ignore>
                        <canvas id="customer-concerns-chart" data-report-chart data-type="bar" data-config='@json($concernChart)'></canvas>
                    </div>
                </div>
            @endif

            @if ($customer->recommended_next_action)
                <x-saas.analysis-insight-list
                    title="اقدام پیشنهادی بعدی"
                    :items="[$customer->recommended_next_action]"
                    tone="action"
                />
            @endif

            @if (! empty($nextActions))
                <x-saas.analysis-insight-list
                    title="اقدامات بعدی (جمع‌بندی)"
                    :items="$nextActions"
                    tone="warning"
                />
            @endif
        </div>

        <div class="space-y-6">
            <div class="saas-card">
                <h2 class="text-lg font-semibold">اطلاعات تماس</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">شماره</dt>
                        <dd class="font-medium text-left">{{ $customer->phone_number ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">ایمیل</dt>
                        <dd class="font-medium text-left">{{ $customer->email ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">سمت</dt>
                        <dd class="font-medium text-left">{{ $customer->job_title ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">اولین تماس</dt>
                        <dd class="font-medium text-left">{{ shamsi($customer->first_contact_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">آخرین تماس</dt>
                        <dd class="font-medium text-left">{{ shamsi($customer->last_contact_at) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="saas-card">
                <h2 class="text-lg font-semibold">کارشناسان مرتبط</h2>
                <ul class="mt-4 space-y-3">
                    @forelse ($employees as $employee)
                        <li>
                            <x-saas.user-cell :employee="$employee" :subtitle="$employee->department" avatar-size="xs" />
                        </li>
                    @empty
                        <li class="text-sm text-zinc-500">هنوز کارشناسی اختصاص نیافته</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="saas-card">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold">تاریخچه تماس‌ها</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ count($timeline) }} تماس ثبت‌شده</p>
            </div>
        </div>
        <div class="mt-6 space-y-4">
            @forelse ($timeline as $item)
                @php
                    $canViewPerformance = $isEmployer || ($viewerMembershipId && (int) $item['employee_id'] === (int) $viewerMembershipId);
                @endphp
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800" wire:key="timeline-{{ $item['call_id'] }}">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-medium">{{ $item['date'] ?? '—' }}</p>
                            <p class="text-sm text-zinc-500">{{ $item['employee_name'] }} · {{ $item['duration_label'] }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($canViewPerformance && $item['score'])
                                <span class="saas-badge bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                                    امتیاز {{ $item['score'] }}
                                </span>
                            @endif
                            @if ($item['lead_level'])
                                <span @class(['saas-badge', CustomerPresenter::leadBadgeClass($item['lead_level'])])>
                                    لید {{ AnalysisInsightPresenter::leadLevelLabel($item['lead_level']) }}
                                </span>
                            @endif
                            @if ($item['sentiment'])
                                <span class="saas-badge">{{ $item['sentiment'] }}</span>
                            @endif
                        </div>
                    </div>
                    @if ($item['summary'])
                        <p class="mt-3 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ \Illuminate\Support\Str::limit($item['summary'], 280) }}</p>
                    @endif
                    @if (! empty($item['concerns']))
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($item['concerns'] as $concern)
                                <span class="saas-badge">{{ is_array($concern) ? ($concern['text'] ?? '') : $concern }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if (! empty($item['next_actions']))
                        <div class="mt-3 rounded-md bg-zinc-50 p-3 dark:bg-zinc-900/60">
                            <p class="text-xs font-medium text-zinc-500">اقدامات بعدی</p>
                            <ul class="mt-1 space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                                @foreach ($item['next_actions'] as $action)
                                    <li>• {{ is_string($action) ? $action : ($action['action'] ?? '') }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($item['analysis_id'])
                        <div class="mt-4">
                            <a href="{{ route($analysisShowRoute, $item['analysis_id']) }}" class="text-sm font-medium text-indigo-600 hover:underline">
                                {{ $canViewPerformance ? 'مشاهده تحلیل کامل' : 'مشاهده جزئیات تماس' }}
                            </a>
                        </div>
                    @endif
                </div>
            @empty
                <x-saas.empty-state title="تماسی ثبت نشده" description="تماس‌های این مشتری پس از تحلیل اینجا نمایش داده می‌شوند." />
            @endforelse
        </div>
    </div>
</div>
