<div
    x-cloak
    x-show="insight"
    x-transition.opacity.duration.150ms
    class="mt-5 border-t border-zinc-200/80 pt-4 dark:border-zinc-800"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p
                class="text-sm font-semibold"
                :class="{
                    'text-emerald-700 dark:text-emerald-300': insight?.direction === 'up',
                    'text-red-700 dark:text-red-300': insight?.direction === 'down',
                    'text-zinc-700 dark:text-zinc-200': insight && insight.direction !== 'up' && insight.direction !== 'down',
                }"
                x-text="insight ? `${insight.headline} · ${insight.label}` : ''"
            ></p>
            <p class="mt-1 text-xs text-zinc-500">
                <template x-if="insight && insight.previous_score !== null">
                    <span>
                        <span x-text="insight.previous_score"></span>
                        →
                        <span x-text="insight.current_score"></span>
                        <span
                            class="font-medium"
                            :class="{
                                'text-emerald-600 dark:text-emerald-400': insight.direction === 'up',
                                'text-red-600 dark:text-red-400': insight.direction === 'down',
                            }"
                            x-show="signedDelta(insight.score_delta)"
                            x-text="`(${signedDelta(insight.score_delta)})`"
                        ></span>
                    </span>
                </template>
                <template x-if="insight && insight.previous_score === null">
                    <span>میانگین امتیاز: <span x-text="insight.current_score"></span></span>
                </template>
                · <span x-text="insight?.analyzed_count"></span> تماس تحلیل‌شده
            </p>
        </div>
        <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="close()">
            بستن
        </button>
    </div>

    <p class="mt-3 text-sm leading-7 text-zinc-700 dark:text-zinc-200" x-text="insight?.reason"></p>

    <div class="mt-3 flex flex-wrap gap-2" x-show="insight?.factors?.length">
        <template x-for="factor in (insight?.factors || [])" :key="factor.item">
            <span
                class="rounded-md px-2.5 py-1 text-xs font-medium"
                :class="insight?.direction === 'down'
                    ? 'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-300'
                    : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300'"
                x-text="`${factor.item} (${factor.count})`"
            ></span>
        </template>
    </div>

    <div class="mt-4">
        <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100" x-text="agentsTitle()"></h3>

        <div class="mt-3 space-y-2">
            <template x-if="insight?.agents?.length">
                <div class="space-y-2">
                    <template x-for="agent in insight.agents" :key="agent.id">
                        <a
                            :href="agentUrl(agent.id)"
                            wire:navigate
                            class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200/80 bg-white px-3 py-2.5 transition hover:border-indigo-300 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-800"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white" x-text="agent.name"></p>
                                <p class="mt-0.5 text-xs text-zinc-500">
                                    <span x-text="agent.analyzed_count"></span> تماس
                                    <span x-show="agent.highlight" x-text="agent.highlight ? ` · ${agent.highlight}` : ''"></span>
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2 text-sm">
                                <span class="font-bold tabular-nums" :class="scoreClass(agent.score)" x-text="agent.score"></span>
                                <span
                                    x-show="agent.score_delta !== null"
                                    class="rounded-md px-1.5 py-0.5 text-xs font-medium tabular-nums"
                                    :class="deltaClass(agent.score_delta)"
                                    x-text="signedDelta(agent.score_delta)"
                                ></span>
                            </div>
                        </a>
                    </template>
                </div>
            </template>
            <p
                x-show="insight && ! insight.agents?.length"
                class="rounded-lg border border-dashed border-zinc-200 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700"
            >
                کارشناسی با تماس تحلیل‌شده در این نقطه پیدا نشد.
            </p>
        </div>
    </div>
</div>
