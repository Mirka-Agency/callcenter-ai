<div
    class="saas-card min-w-0 overflow-hidden p-0"
    data-tour="dashboard-forgotten-followups"
    @if (! empty($forgottenFollowUps))
        x-data="{
            ...dashboardTableSort({
                column: 'call_date',
                dir: 'asc',
                allowed: ['call_date'],
                defaultDir: 'asc',
            }),
            index: 0,
            total: {{ count($forgottenFollowUps) }},
            timer: null,
            init() {
                this.start()
            },
            start() {
                if (this.timer || this.total < 2) {
                    return
                }

                this.timer = setInterval(() => {
                    this.index = (this.index + 1) % this.total
                }, 5000)
            },
            stop() {
                clearInterval(this.timer)
                this.timer = null
            },
            resume() {
                if (this.$el.matches(':hover') || this.$el.contains(document.activeElement)) {
                    return
                }

                this.start()
            },
            destroy() {
                this.stop()
            },
            trackStyle() {
                const rtl = document.documentElement.getAttribute('dir') === 'rtl'
                const sign = rtl ? 1 : -1

                return `transform: translateX(calc(${sign * this.index} * (100% + var(--forgotten-gap))))`
            },
        }"
        x-on:mouseenter="stop()"
        x-on:mouseleave="resume()"
        x-on:focusin="stop()"
        x-on:focusout="resume()"
    @endif
>
    <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-6 sm:pt-6">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold">پیگیری‌های فراموش‌شده</h2>
            <p class="mt-1 text-sm leading-6 text-zinc-500">درخواستی که باید پیگیری می‌شد و هنوز تماسی گرفته نشده است.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (! empty($forgottenFollowUps))
                <span class="rounded-lg bg-red-50 px-2.5 py-1 text-sm font-medium tabular-nums text-red-700 dark:bg-red-500/10 dark:text-red-400">
                    {{ count($forgottenFollowUps) }} پیگیری معوق
                </span>
                <button
                    type="button"
                    class="saas-forgotten-sort"
                    x-on:click="sortBy('call_date')"
                    x-bind:aria-sort="ariaSort('call_date')"
                >
                    تاریخ تماس
                    <x-saas.sort-icon x-bind:class="sortIconClass('call_date')" />
                </button>
            @endif
        </div>
    </div>

    @if (empty($forgottenFollowUps))
        <div class="px-4 pb-6 sm:px-6">
            <x-saas.empty-state
                title="{{ __('ui.empty.no_forgotten_followups.title') }}"
                description="{{ __('ui.empty.no_forgotten_followups.description') }}"
            />
        </div>
    @else
        <div class="saas-forgotten-carousel">
            <div class="saas-forgotten-track" x-ref="list" x-bind:style="trackStyle()">
            @foreach ($forgottenFollowUps as $followUp)
                <div
                    class="saas-forgotten-slide"
                    wire:key="forgotten-follow-up-{{ $followUp['analysis_id'] }}"
                    data-sort-row
                    data-sort-call-date="{{ $followUp['sort_call_date'] ?? 0 }}"
                    :aria-hidden="index !== {{ $loop->index }} ? 'true' : 'false'"
                >
                <article class="saas-forgotten-item">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $followUp['customer'] }}</p>
                            @if ($followUp['company'] && $followUp['company'] !== $followUp['customer'])
                                <p class="mt-0.5 truncate text-xs text-zinc-500">{{ $followUp['company'] }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 rounded-md bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-400">
                            {{ $followUp['days_overdue'] }} روز تأخیر
                        </span>
                    </div>

                    @if (count($followUp['forgotten_actions'] ?? []) > 1)
                        <ul class="mt-3 space-y-1.5 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                            @foreach ($followUp['forgotten_actions'] as $action)
                                <li class="flex items-start gap-2">
                                    <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-red-500"></span>
                                    <span>{{ $action }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-2 text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $followUp['forgotten_action'] }}</p>
                    @endif

                    @if (! empty($followUp['summary']))
                        <div class="mt-3">
                            <p class="text-xs font-semibold text-zinc-500">خلاصه تماس</p>
                            <p class="mt-1 text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $followUp['summary'] }}</p>
                        </div>
                    @endif

                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                        <span>{{ $followUp['employee'] }}</span>
                        @if ($followUp['phone'])
                            <span dir="ltr" class="tabular-nums">{{ $followUp['phone'] }}</span>
                        @endif
                        <span class="tabular-nums">{{ $followUp['call_date'] }}</span>
                    </div>

                    <a href="{{ route('employer.intelligence.show', $followUp['analysis_id']) }}" class="mt-3 inline-flex text-xs font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white">
                        نمایش تحلیل
                    </a>
                </article>
                </div>
            @endforeach
            </div>
            @if (count($forgottenFollowUps) > 1)
                <p class="mt-3 text-center text-xs tabular-nums text-zinc-400" aria-live="off">
                    <span x-text="index + 1"></span>
                    از
                    {{ count($forgottenFollowUps) }}
                </p>
            @endif
        </div>
    @endif
</div>
