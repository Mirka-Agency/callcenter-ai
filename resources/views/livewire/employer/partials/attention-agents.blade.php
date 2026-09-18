@php
    use App\Support\AgentPerformancePresenter;
@endphp

@if (! empty($attentionEmployees))
    <section class="saas-section" data-tour="performance-attention">
        <div>
            <h2 class="saas-section-title">کارشناسان نیازمند توجه</h2>
            <p class="saas-section-subtitle">
                کارشناسانی که چند نقطه ضعف دارند و همان ضعف‌ها در تماس‌هایشان تکرار شده است.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($attentionEmployees as $agent)
                <a
                    href="{{ $agentProfileUrl($agent) }}"
                    wire:key="attention-agent-{{ $agent['id'] }}"
                    wire:navigate
                    class="saas-widget group border bg-gradient-to-b from-red-500/10 to-red-100/30 border-red-200/70 transition hover:-translate-y-0.5 hover:border-red-300 hover:shadow-sm dark:from-red-500/15 dark:to-red-950/20 dark:border-red-500/20 dark:hover:border-red-400/40"
                >
                    <div class="flex items-start gap-3">
                        <x-saas.avatar
                            :name="$agent['name']"
                            :url="$agent['avatar_url'] ?? null"
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
    </section>
@endif
