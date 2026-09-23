@php
    use App\Support\AgentPerformancePresenter;
@endphp

@if (! empty($attentionEmployees))
    <section class="saas-section min-w-0" data-tour="performance-attention">
        <div>
            <h2 class="saas-section-title">کارشناسان نیازمند توجه</h2>
            <p class="saas-section-subtitle">
                کارشناسانی که چند نقطه ضعف دارند و همان ضعف‌ها در تماس‌هایشان تکرار شده است.
            </p>
        </div>

        <div class="min-w-0 snap-x snap-mandatory overflow-x-auto overscroll-x-contain pb-1 pt-0.5">
            <div class="flex w-max min-w-full gap-4">
                @foreach ($attentionEmployees as $agent)
                    <a
                        href="{{ $agentProfileUrl($agent) }}"
                        wire:key="attention-agent-{{ $agent['id'] }}"
                        wire:navigate
                        class="saas-widget group w-[min(18.5rem,calc(100vw-4.5rem))] shrink-0 snap-start border border-red-100/80 bg-gradient-to-b from-red-50/50 to-white transition hover:-translate-y-0.5 hover:border-red-200 hover:shadow-sm sm:w-80 dark:border-red-500/10 dark:from-red-500/[0.06] dark:to-zinc-900 dark:hover:border-red-400/20"
                    >
                        <div class="flex items-start gap-3">
                            <x-saas.avatar
                                :name="$agent['name']"
                                :url="$agent['avatar_url'] ?? null"
                                :gender="$agent['gender'] ?? null"
                                agent
                                size="sm"
                                class="shrink-0"
                            />

                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-sm font-semibold text-zinc-900 transition group-hover:text-red-700 dark:text-white dark:group-hover:text-red-300">
                                    {{ $agent['name'] }}
                                </h3>
                                <p class="mt-0.5 text-[11px] text-zinc-500">
                                    {{ $agent['repeated_weakness_count'] }} ضعف پرتکرار
                                    · {{ $agent['total_analyzed'] }} تماس تحلیل‌شده
                                </p>
                            </div>

                            <span @class([
                                'shrink-0 rounded-md px-2 py-1 text-xs font-bold tabular-nums',
                                AgentPerformancePresenter::scoreBoxClass($agent['average_score'] ?? null),
                                AgentPerformancePresenter::scoreTextClass($agent['average_score'] ?? null),
                            ])>
                                {{ $agent['average_score'] ?: '—' }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($agent['repeated_weaknesses'] as $weakness)
                                <span class="rounded-md bg-red-50 px-2 py-0.5 text-[11px] font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                    {{ $weakness['item'] }} ({{ $weakness['count'] }})
                                </span>
                            @endforeach
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
