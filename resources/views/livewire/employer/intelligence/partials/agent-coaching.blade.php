@php
    use App\Support\AgentPerformancePresenter;
    use App\Support\CoachingPresenter;

    $coaching = $agentCoaching;
    $trendChart = [
        'labels' => $coaching['trend']['labels'] ?? [],
        'datasets' => $coaching['trend']['datasets'] ?? [],
    ];
@endphp

<section class="space-y-6" data-coaching-section>
    <div>
        <h2 class="text-xl font-semibold">ارزیابی مهارت</h2>
        <p class="mt-1 text-sm text-zinc-500">جمع‌بندی مهارت‌ها از تماس‌های تحلیل‌شده همین بازه</p>
    </div>

    @if (($coaching['status'] ?? 'empty') !== 'ready')
        <x-saas.empty-state
            :title="$coaching['title'] ?? ''"
            :description="$coaching['description'] ?? null"
        />
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-saas.stat-card
                label="امتیاز مهارت"
                :value="$coaching['overall_score']"
                :hint="$coaching['score_change_label']"
            />
            <x-saas.stat-card label="تماس‌های تحلیل‌شده" :value="$coaching['analyzed_calls']" />
            <x-saas.stat-card label="مهارت نیازمند بهبود" :value="$coaching['skills_needing_improvement']" />
            <x-saas.stat-card label="مهارت قوی" :value="$coaching['strong_skill_count']" />
        </div>

        @if ($coaching['coverage_note'])
            <p class="text-sm text-zinc-500">{{ $coaching['coverage_note'] }}</p>
        @endif

        <div class="saas-card">
            <h3 class="text-lg font-semibold">عملکرد مهارت‌ها</h3>
            <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($coaching['skills'] as $skill)
                    <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="font-medium text-zinc-800 dark:text-zinc-100">{{ $skill['label'] }}</p>
                        <div class="flex items-center gap-3">
                            <span @class(['text-lg font-bold tabular-nums', AgentPerformancePresenter::scoreTextClass($skill['score'])])>
                                {{ $skill['score'] }}
                            </span>
                            <span @class(['rounded-md px-2.5 py-1 text-xs font-medium', CoachingPresenter::statusBadgeClass($skill['status'])])>
                                {{ $skill['status_label'] }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="saas-card">
                <h3 class="text-lg font-semibold">نقاط قوت</h3>
                <div class="mt-4 space-y-4">
                    @forelse ($coaching['strengths'] as $item)
                        <article class="rounded-lg border border-emerald-200/70 bg-emerald-50/40 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="font-semibold">{{ $item['title'] }}</h4>
                                <span class="text-sm font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ $item['score'] }}</span>
                            </div>
                            @if ($item['explanation'] !== '')
                                <p class="mt-2 text-sm leading-7 text-zinc-600 dark:text-zinc-300">{{ $item['explanation'] }}</p>
                            @endif
                        </article>
                    @empty
                        <p class="text-sm text-zinc-500">@lang('ui.empty.agent_coaching.no_strengths')</p>
                    @endforelse
                </div>
            </div>

            <div class="saas-card">
                <h3 class="text-lg font-semibold">زمینه‌های بهبود</h3>
                <div class="mt-4 space-y-4">
                    @forelse ($coaching['areas_for_improvement'] as $item)
                        <article class="rounded-lg border border-amber-200/70 bg-amber-50/40 p-4 dark:border-amber-900/40 dark:bg-amber-950/20">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="font-semibold">{{ $item['title'] }}</h4>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold tabular-nums">{{ $item['score'] }}</span>
                                    <span @class(['rounded-md px-2 py-0.5 text-xs font-medium', CoachingPresenter::severityBadgeClass($item['severity'])])>
                                        شدت {{ $item['severity_label'] }}
                                    </span>
                                </div>
                            </div>
                            <p class="mt-2 text-sm leading-7 text-zinc-600 dark:text-zinc-300">{{ $item['frequency'] }}</p>
                            @if ($item['explanation'] !== '')
                                <p class="mt-2 text-sm leading-7 text-zinc-700 dark:text-zinc-200">{{ $item['explanation'] }}</p>
                            @endif
                            @if ($item['recommendation'] !== '')
                                <p class="mt-2 text-sm leading-7 text-zinc-600 dark:text-zinc-300">
                                    <span class="font-medium text-zinc-800 dark:text-zinc-100">پیشنهاد بهبود: </span>{{ $item['recommendation'] }}
                                </p>
                            @endif
                        </article>
                    @empty
                        <p class="text-sm text-zinc-500">@lang('ui.empty.agent_coaching.no_weaknesses')</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="saas-card">
            <h3 class="text-lg font-semibold">شاهد از تماس‌ها</h3>
            <div class="mt-4 space-y-3">
                @forelse ($coaching['evidence'] as $item)
                    <article class="rounded-lg border border-zinc-200/80 p-4 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-medium">{{ $item['call_label'] }}</p>
                            <div class="flex items-center gap-3 text-sm">
                                @if ($item['time_label'])
                                    <span class="tabular-nums text-zinc-500">{{ $item['time_label'] }}</span>
                                @endif
                                <a href="{{ route('employer.intelligence.show', $item['analysis_id']) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    @lang('ui.empty.agent_coaching.view_call')
                                </a>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-zinc-500">{{ $item['skill_label'] }}</p>
                        @if ($item['description'] !== '')
                            <p class="mt-2 text-sm leading-7 text-zinc-700 dark:text-zinc-200">{{ $item['description'] }}</p>
                        @endif
                        @if ($item['quote'] !== '')
                            <p class="mt-2 text-sm leading-7 text-zinc-500">«{{ $item['quote'] }}»</p>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-zinc-500">@lang('ui.empty.agent_coaching.no_evidence')</p>
                @endforelse
            </div>
        </div>

        <div class="saas-card">
            <h3 class="text-lg font-semibold">پیشنهادهای بهبود</h3>
            <ol class="mt-4 space-y-3">
                @forelse ($coaching['recommendations'] as $item)
                    <li class="rounded-lg border border-indigo-200/60 bg-indigo-50/30 p-4 dark:border-indigo-900/40 dark:bg-indigo-950/20">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">اولویت {{ $item['priority'] }}</span>
                            <span @class(['rounded-md px-2 py-0.5 text-xs font-medium', CoachingPresenter::severityBadgeClass($item['level'])])>
                                {{ $item['level_label'] }}
                            </span>
                        </div>
                        <h4 class="mt-2 font-semibold">{{ $item['title'] }}</h4>
                        <p class="mt-2 text-sm leading-7 text-zinc-700 dark:text-zinc-200">{{ $item['suggested_action'] }}</p>
                    </li>
                @empty
                    <li class="text-sm text-zinc-500">@lang('ui.empty.agent_coaching.no_recommendations')</li>
                @endforelse
            </ol>
        </div>

        <div class="saas-card">
            <h3 class="text-lg font-semibold">روند مهارت‌ها</h3>
            @if ($coaching['trend']['has_data'] ?? false)
                <div class="mt-4 h-56" wire:ignore>
                    <canvas id="agent-coaching-trend" data-report-chart data-type="line" data-config='@json($trendChart)'></canvas>
                </div>
            @else
                <p class="mt-4 text-sm text-zinc-500">@lang('ui.empty.agent_coaching.trend_empty')</p>
            @endif
        </div>
    @endif
</section>
