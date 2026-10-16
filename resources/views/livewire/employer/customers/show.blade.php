@php
    use App\Domain\Customer\CustomerLifecycleStage;
    use App\Support\AnalysisInsightPresenter;
    use App\Support\JalaliDate;
    use App\Support\CustomerFieldProvenance;
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
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <nav class="text-sm text-zinc-500">
        <a href="{{ $customersHubRoute }}" class="hover:text-indigo-600" wire:navigate>مشتریان</a>
        <span class="mx-2">/</span>
        <a href="{{ $customersIndexRoute }}" class="hover:text-indigo-600" wire:navigate>لیست اشخاص</a>
        @if ($customer->company)
            <span class="mx-2">/</span>
            <a href="{{ route($companyShowRouteName, $customer->company) }}" class="hover:text-indigo-600" wire:navigate>{{ $customer->company->displayName() }}</a>
        @endif
        <span class="mx-2">/</span>
        <span class="text-zinc-800 dark:text-zinc-200">{{ $customer->displayName() }}</span>
    </nav>

    <section class="saas-hero" data-tour="customer-profile">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-5">
                <x-saas.avatar :name="$customer->displayName()" size="xl" ring />
                <div class="min-w-0">
                    <p class="text-sm font-medium text-indigo-600">شخص{{ $customer->company ? ' · '.$customer->company->displayName() : '' }}</p>
                    <h1 class="text-3xl font-semibold tracking-tight">{{ $customer->displayName() }}</h1>
                    @unless ($customer->name)
                        <p class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-300">نام تأیید نشده</p>
                    @endunless
                    @if (CustomerFieldProvenance::hasHumanLock($customer->identity_field_sources))
                        <p class="mt-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">هویت تأییدشده توسط کاربر؛ پیشنهاد هوش مصنوعی این فیلدها را عوض نمی‌کند.</p>
                    @endif
                    <p class="mt-1 text-zinc-500">{{ CustomerPresenter::subtitle($customer) }}</p>
                    <div class="mt-4 max-w-sm space-y-2">
                        <p class="text-xs font-medium text-zinc-500">مسئول مشتری</p>
                        @if ($canAssignOwner ?? false)
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <select wire:model="ownerOrganizationUserId" class="saas-input">
                                    <option value="">بدون مسئول</option>
                                    @foreach ($ownerChoices as $member)
                                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="saveOwner" class="saas-btn-secondary shrink-0 text-sm">ثبت مسئول</button>
                            </div>
                            @error('owner')
                                <p class="text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        @elseif ($customer->owner)
                            <p class="text-sm text-zinc-800">{{ $customer->owner->full_name }}</p>
                        @else
                            <p class="text-sm font-medium text-amber-700">بدون مسئول</p>
                        @endif
                    </div>
                    <div class="mt-4 max-w-md space-y-2">
                        <p class="text-xs font-medium text-zinc-500">مرحله چرخه عمر</p>
                        @if ($canChangeLifecycle ?? false)
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <select wire:model.live="lifecycleStage" class="saas-input">
                                    <option value="">{{ CustomerLifecycleStage::unknownLabel() }}</option>
                                    @foreach (CustomerLifecycleStage::cases() as $stage)
                                        <option value="{{ $stage->value }}">{{ $stage->label() }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="saveLifecycle" class="saas-btn-secondary shrink-0 text-sm">ثبت مرحله</button>
                            </div>
                            @if ($lifecycleStage === 'customer')
                                <label class="flex items-start gap-2 text-sm text-zinc-700">
                                    <input wire:model="confirmCustomerStage" type="checkbox" class="mt-1 rounded border-zinc-300 text-indigo-600">
                                    <span>تبدیل واقعی این شخص به مشتری را تأیید می‌کنم. امتیاز لید به‌تنهایی این مرحله را عوض نمی‌کند.</span>
                                </label>
                            @endif
                            @error('lifecycle')
                                <p class="text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        @else
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                $customer->lifecycle_stage?->badgeClass() ?? CustomerLifecycleStage::unknownBadgeClass(),
                            ])>
                                مرحله: {{ $customer->lifecycle_stage?->label() ?? CustomerLifecycleStage::unknownLabel() }}
                            </span>
                        @endif
                        @if (($lifecycleEvents ?? collect())->isNotEmpty())
                            <ul class="space-y-1 text-xs text-zinc-500">
                                @foreach ($lifecycleEvents as $event)
                                    <li>
                                        {{ CustomerLifecycleStage::tryFrom((string) $event->from_stage)?->label() ?? CustomerLifecycleStage::unknownLabel() }}
                                        به
                                        {{ CustomerLifecycleStage::tryFrom((string) $event->to_stage)?->label() ?? CustomerLifecycleStage::unknownLabel() }}
                                        · {{ JalaliDate::datetime($event->changed_at) }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($customer->conversation_trend)
                            <span @class(['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CustomerPresenter::trendBadgeClass($customer->conversation_trend)])>
                                {{ CustomerPresenter::trendLabel($customer->conversation_trend) }}
                            </span>
                        @endif
                        @if ($customer->latest_lead_level)
                            <span @class(['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', CustomerPresenter::leadBadgeClass($customer->latest_lead_level)])>
                                کیفیت لید: {{ AnalysisInsightPresenter::leadLevelLabel($customer->latest_lead_level) }}
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
            <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center lg:flex-col lg:items-end">
                @if ($customer->latest_lead_score)
                    <x-saas.dimension-ring
                        label="امتیاز لید"
                        :score="$customer->latest_lead_score"
                        size="lg"
                    />
                @endif
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route($customerEditRouteName, $customer) }}" class="saas-btn-primary text-sm" wire:navigate>ویرایش شخص</a>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-saas.stat-card label="کل تماس‌ها" :value="$analytics['total_calls']" :hint="$analytics['answered_calls'].' پاسخ‌داده'" />
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
                    <x-saas.empty-state title="{{ __('ui.empty.chart_trend.title') }}" description="{{ __('ui.empty.chart_trend.description') }}" />
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
                    title="اقدامات بعدی"
                    :items="$nextActions"
                    tone="warning"
                />
            @endif
        </div>

        <div class="space-y-6">
            <div class="saas-card space-y-4">
                <livewire:customers.contact-methods :customer-id="$customer->id" :key="'contact-methods-'.$customer->id" />
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">سمت</dt>
                        <dd class="font-medium text-left">
                            {{ $customer->job_title ?: '—' }}
                            @if (CustomerFieldProvenance::isHumanLocked($customer->identity_field_sources, 'job_title'))
                                <span class="ms-1 text-xs font-normal text-emerald-700 dark:text-emerald-300">دستی</span>
                            @endif
                        </dd>
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

    @isset($dealShowRouteName)
        <section class="saas-card p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">معاملات این شخص</h2>
                @if (! empty($dealsIndexRoute))
                    <a href="{{ $dealsIndexRoute }}" class="text-sm font-medium text-indigo-600" wire:navigate>همه معاملات</a>
                @endif
            </div>
            <ul class="mt-4 space-y-2 text-sm">
                @forelse ($relatedDeals ?? [] as $deal)
                    <li class="flex items-center justify-between gap-3" wire:key="customer-deal-{{ $deal->id }}">
                        <a href="{{ route($dealShowRouteName, $deal) }}" class="font-medium text-indigo-700" wire:navigate>{{ $deal->title }}</a>
                        <span class="text-zinc-500">{{ $deal->stage?->name }} · {{ $deal->status->label() }}</span>
                    </li>
                @empty
                    <li class="text-zinc-500">معامله‌ای برای این شخص ثبت نشده است.</li>
                @endforelse
            </ul>
        </section>

        <section class="saas-card p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">وظایف این شخص</h2>
                @if (! empty($tasksIndexRoute))
                    <a href="{{ $tasksIndexRoute }}" class="text-sm font-medium text-indigo-600" wire:navigate>همه وظایف</a>
                @endif
            </div>
            <ul class="mt-4 space-y-2 text-sm">
                @forelse ($relatedTasks ?? [] as $task)
                    <li class="flex items-center justify-between gap-3" wire:key="customer-task-{{ $task->id }}">
                        <a href="{{ route($taskShowRouteName, $task) }}" class="font-medium text-indigo-700" wire:navigate>{{ $task->title }}</a>
                        <span class="text-zinc-500">{{ $task->status->label() }}@if($task->isOverdue()) · عقب‌افتاده@endif</span>
                    </li>
                @empty
                    <li class="text-zinc-500">وظیفه‌ای برای این شخص ثبت نشده است.</li>
                @endforelse
            </ul>
        </section>
    @endisset

    <div class="saas-card" data-tour="customer-timeline">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold">تاریخچه تماس‌ها</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    @if (($timelineTotal ?? count($timeline)) > count($timeline))
                        {{ count($timeline) }} تماس اخیر از {{ $timelineTotal }}
                    @else
                        {{ count($timeline) }} تماس ثبت‌شده
                    @endif
                </p>
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
                <x-saas.empty-state title="{{ __('ui.empty.no_calls.title') }}" description="تماس‌های این مشتری پس از تحلیل، اینجا نمایش داده می‌شوند." />
            @endforelse
        </div>
    </div>
</div>
