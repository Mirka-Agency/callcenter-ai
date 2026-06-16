@php
    use App\Support\AnalysisCallPresenter;
    use App\Support\AnalysisInsightPresenter;

    $visibilityMode = $visibilityMode ?? 'full';
    $showEmployeePerformance = $visibilityMode === 'full';
    $backUrl = $backUrl ?? null;
    $leadQuality = $analysis->lead_quality_json ?? null;
    $callStatus = AnalysisCallPresenter::status($analysis);
    $callDirection = AnalysisCallPresenter::direction($analysis);
    $customerName = AnalysisInsightPresenter::customerName($analysis);
@endphp

<div class="space-y-6" data-tour="analysis-detail">
    @if ($backUrl)
        <a href="{{ $backUrl }}" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
            <svg class="h-4 w-4 rtl:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
            </svg>
            بازگشت به لیست تحلیل‌ها
        </a>
    @endif

    <section class="saas-hero">
        <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-start lg:justify-between lg:p-8">
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium uppercase tracking-wider text-indigo-600 dark:text-indigo-400">تحلیل مکالمه</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">
                    {{ $customerName ?? $analysis->call?->displayTitle() ?? 'جزئیات تماس' }}
                </h1>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-500">
                    <span>{{ shamsi($analysis->analyzed_at, 'datetime') }}</span>
                    @if ($analysis->employee)
                        <x-saas.user-cell
                            :employee="$analysis->employee"
                            :subtitle="$analysis->employee->department"
                            avatar-size="xs"
                            class="inline-flex"
                        />
                    @endif
                    <span class="hidden text-zinc-300 sm:inline" aria-hidden="true">|</span>
                    <span>{{ $analysis->source?->label() ?? 'VoIP' }}</span>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span @class(['saas-badge', AnalysisInsightPresenter::sentimentBadgeClass($analysis->sentiment)])>
                        احساس: {{ $analysis->sentiment->label() }}
                    </span>
                    @if ($callStatus)
                        <span @class(['saas-badge', AnalysisCallPresenter::statusBadgeClass($callStatus)])>{{ $callStatus->label() }}</span>
                    @endif
                    @if ($callDirection)
                        <span class="saas-badge bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $callDirection->label() }}</span>
                    @endif
                    @if (AnalysisCallPresenter::durationSeconds($analysis))
                        <span class="saas-badge bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ AnalysisCallPresenter::durationLabel($analysis) }}</span>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-4 lg:justify-end">
                @if ($showEmployeePerformance)
                    <x-saas.score-ring :score="$analysis->score" size="md" label="امتیاز مکالمه" />
                @endif
                @if ($leadQuality)
                    <div class="saas-inline-stat text-center">
                        <p class="text-xs font-medium text-zinc-500">کیفیت لید</p>
                        <p @class(['mt-1 text-3xl font-bold tabular-nums', AnalysisInsightPresenter::leadLevelClass($leadQuality['level'] ?? null)])>
                            {{ $leadQuality['score'] ?? '—' }}
                        </p>
                        <p class="mt-1 text-xs text-zinc-500">{{ AnalysisInsightPresenter::leadLevelLabel($leadQuality['level'] ?? null) }}</p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @if (($recordingUrl ?? null) || ($recordingExpired ?? false))
                <div class="saas-card">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">ضبط مکالمه</h2>
                    @include('livewire.shared.recording-player', [
                        'recordingUrl' => $recordingUrl ?? null,
                        'recordingExpired' => $recordingExpired ?? false,
                        'embedded' => true,
                    ])
                </div>
            @endif

            <div class="saas-card">
                <h2 class="text-lg font-semibold">خلاصه مکالمه</h2>
                <p class="mt-4 whitespace-pre-wrap text-base leading-8 text-zinc-700 dark:text-zinc-300">{{ $analysis->summary }}</p>
                @if ($showEmployeePerformance && $analysis->overall_evaluation)
                    <div class="mt-6 rounded-lg border border-indigo-200/60 bg-indigo-50/50 p-4 dark:border-indigo-500/20 dark:bg-indigo-950/20">
                        <h3 class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">ارزیابی کلی</h3>
                        <p class="mt-2 text-sm leading-relaxed text-indigo-950/90 dark:text-indigo-100/90">{{ $analysis->overall_evaluation }}</p>
                    </div>
                @endif
            </div>

            @if ($showEmployeePerformance && $analysis->performance_dimensions_json)
                <div class="saas-card">
                    <h2 class="text-lg font-semibold">ابعاد عملکرد</h2>
                    <div class="mt-5 space-y-4">
                        @foreach ($analysis->performance_dimensions_json as $dimension => $value)
                            @php $score = AnalysisInsightPresenter::dimensionScore($value); @endphp
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ AnalysisInsightPresenter::dimensionLabel($dimension) }}</span>
                                    <span @class(['font-bold tabular-nums', AnalysisInsightPresenter::scoreTextClass($score)])>{{ $score }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                    <div
                                        class="h-2 rounded-full bg-gradient-to-l from-indigo-500 to-violet-500 transition-all"
                                        style="width: {{ min(100, max(0, $score)) }}%"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($showEmployeePerformance)
                <div class="grid gap-4 md:grid-cols-3">
                    <x-saas.analysis-insight-list
                        title="نقاط قوت"
                        :items="$analysis->strengths_json ?? []"
                        tone="positive"
                    />
                    <x-saas.analysis-insight-list
                        title="نقاط قابل بهبود"
                        :items="$analysis->weaknesses_json ?? []"
                        tone="warning"
                    />
                    <x-saas.analysis-insight-list
                        title="اقدامات پیشنهادی"
                        :items="$analysis->next_actions_json ?? []"
                        tone="action"
                    />
                </div>
            @else
                <x-saas.analysis-insight-list
                    title="اقدامات بعدی"
                    :items="$analysis->next_actions_json ?? []"
                    tone="action"
                    class="saas-card !border-zinc-200/80 !bg-white dark:!bg-zinc-900"
                />
            @endif

            @include('livewire.shared.analysis-lead-and-concerns')

            @if ($showEmployeePerformance && $analysis->transcript)
                <div class="saas-card" x-data="{ open: false }">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 text-start"
                        x-on:click="open = !open"
                    >
                        <h2 class="text-lg font-semibold">رونوشت کامل</h2>
                        <svg class="h-5 w-5 text-zinc-400 transition" x-bind:class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="mt-4 max-h-[28rem] overflow-y-auto whitespace-pre-wrap rounded-lg bg-zinc-50 p-4 text-sm leading-relaxed text-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">
                        {{ $analysis->transcript }}
                    </div>
                </div>
            @endif

            @if ($showEmployeePerformance && $analysis->operational_insights_json)
                @php
                    $sectionLabels = [
                        'missed_opportunities' => 'فرصت‌های از دست رفته',
                        'escalation_risks' => 'ریسک‌های تشدید',
                        'compliance_issues' => 'مسائل انطباق',
                        'follow_up_suggestions' => 'پیشنهادهای پیگیری',
                    ];
                @endphp
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach (['missed_opportunities', 'escalation_risks', 'compliance_issues', 'follow_up_suggestions'] as $section)
                        @if (! empty($analysis->operational_insights_json[$section]))
                            <x-saas.analysis-insight-list
                                :title="$sectionLabels[$section]"
                                :items="$analysis->operational_insights_json[$section]"
                            />
                        @endif
                    @endforeach
                </div>
            @elseif (! $showEmployeePerformance && ! empty($analysis->operational_insights_json['follow_up_suggestions']))
                <x-saas.analysis-insight-list
                    title="پیشنهادهای پیگیری"
                    :items="$analysis->operational_insights_json['follow_up_suggestions']"
                    tone="action"
                    class="saas-card !border-zinc-200/80 !bg-white dark:!bg-zinc-900"
                />
            @endif
        </div>

        <aside class="space-y-6 xl:sticky xl:top-6 xl:self-start">
            @if ($analysis->call)
                @include('livewire.shared.call-processing-status', [
                    'call' => $analysis->call,
                    'queueUrl' => $queueUrl ?? null,
                ])
            @endif

            @include('livewire.shared.analysis-customer-identity')

            @if ($analysis->customer_insights_json)
                <div class="saas-card">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">بینش مشتری</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        @foreach ($analysis->customer_insights_json as $key => $value)
                            @if (! is_array($value) && filled($value))
                                <div class="flex items-start justify-between gap-3">
                                    <dt class="text-zinc-500">{{ AnalysisInsightPresenter::customerInsightLabel($key) }}</dt>
                                    <dd @class([
                                        'text-end font-medium',
                                        'text-emerald-600 dark:text-emerald-400' => $key === 'purchase_probability' && (float) $value >= 70,
                                        'text-amber-600 dark:text-amber-400' => $key === 'purchase_probability' && (float) $value >= 40 && (float) $value < 70,
                                        'text-zinc-900 dark:text-white' => $key !== 'purchase_probability' || (float) $value < 40,
                                    ])>{{ AnalysisInsightPresenter::customerInsightValue($key, $value) }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </div>
            @endif

            <div class="saas-card space-y-4">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">جزئیات تماس</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-zinc-500">کارشناس</dt>
                        <dd class="text-end">
                            @if ($analysis->employee)
                                <x-saas.user-cell
                                    :employee="$analysis->employee"
                                    avatar-size="xs"
                                    class="justify-end"
                                />
                            @else
                                <span class="font-medium">—</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-zinc-500">منبع</dt>
                        <dd class="text-end font-medium">{{ $analysis->source?->label() ?? 'VoIP' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-zinc-500">مدت تماس</dt>
                        <dd class="text-end font-medium tabular-nums">{{ AnalysisCallPresenter::durationLabel($analysis) }}</dd>
                    </div>
                    @if ($showEmployeePerformance)
                        @include('livewire.shared.analysis-ai-infrastructure', ['analysis' => $analysis])
                        <div class="flex items-start justify-between gap-3 border-t border-zinc-200/80 pt-3 dark:border-zinc-800">
                            <dt class="text-zinc-500">هزینه تحلیل</dt>
                            <dd class="text-end font-medium">{{ \App\Models\PlatformAiSettings::formatMoney($analysis->cost) }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-zinc-500">توکن‌ها</dt>
                            <dd class="text-end font-medium tabular-nums">{{ number_format($analysis->total_tokens) }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if ($showEmployeePerformance && ($analysis->crmSyncs ?? collect())->isNotEmpty())
                <div class="saas-card">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">اقدامات CRM</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($analysis->crmSyncs as $sync)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200/80 px-3 py-2.5 dark:border-zinc-800">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ $sync->crmConnection?->provider?->name ?? $sync->provider_code }}</p>
                                    <p class="text-xs text-zinc-500">{{ str($sync->action_type)->replace('_', ' ')->title() }}</p>
                                </div>
                                <span @class([
                                    'shrink-0 text-xs font-medium',
                                    'text-emerald-600' => $sync->status === 'completed',
                                    'text-red-600' => $sync->status === 'failed',
                                    'text-zinc-500' => ! in_array($sync->status, ['completed', 'failed'], true),
                                ])>{{ match($sync->status) { 'completed' => 'تکمیل', 'failed' => 'ناموفق', default => ucfirst($sync->status) } }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>
    </div>
</div>
