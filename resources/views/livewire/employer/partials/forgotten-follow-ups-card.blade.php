<div class="saas-card overflow-hidden p-0" data-tour="dashboard-forgotten-followups">
    <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-6 sm:pt-6">
        <div>
            <h2 class="text-lg font-semibold">پیگیری‌های فراموش‌شده</h2>
            <p class="mt-1 text-sm text-zinc-500">پیگیری‌های پیشنهادی هوش مصنوعی که موعدشان گذشته و کارشناس هنوز اقدامی نکرده است</p>
        </div>
        @if (! empty($forgottenFollowUps))
            <span class="rounded-lg bg-red-50 px-2.5 py-1 text-sm font-medium tabular-nums text-red-700 dark:bg-red-500/10 dark:text-red-400">
                {{ count($forgottenFollowUps) }} پیگیری معوق
            </span>
        @endif
    </div>

    @if (empty($forgottenFollowUps))
        <div class="px-4 pb-6 sm:px-6">
            <x-saas.empty-state
                title="{{ __('ui.empty.no_forgotten_followups.title') }}"
                description="{{ __('ui.empty.no_forgotten_followups.description') }}"
            />
        </div>
    @else
        <div
            class="saas-opportunity-table-wrap"
            x-data="dashboardTableSort({
                column: 'due_date',
                dir: 'asc',
                allowed: ['due_date'],
                defaultDir: 'asc',
            })"
        >
            <table class="saas-opportunity-table saas-forgotten-table" x-ref="table">
                <colgroup>
                    <col class="saas-opportunity-col-toggle">
                    <col class="saas-opportunity-col-name">
                    <col class="saas-opportunity-col-phone">
                    <col class="saas-opportunity-col-agent">
                    <col class="saas-opportunity-col-forgotten">
                    <col class="saas-opportunity-col-due">
                </colgroup>
                <thead>
                    <tr>
                        <th></th>
                        <th>نام شرکت یا مشتری</th>
                        <th>شماره تماس</th>
                        <th>نام کارشناس</th>
                        <th>اقدام فراموش‌شده</th>
                        <x-saas.sort-header column="due_date" label="تاریخ پیگیری" />
                    </tr>
                </thead>
                @foreach ($forgottenFollowUps as $followUp)
                    <tbody
                        wire:key="forgotten-follow-up-{{ $followUp['analysis_id'] }}"
                        data-sort-row
                        data-sort-due-date="{{ $followUp['sort_due_date'] ?? 0 }}"
                        x-data="{ open: false }"
                    >
                        <tr
                            class="saas-opportunity-row"
                            x-on:click="open = !open"
                            x-bind:aria-expanded="open.toString()"
                            role="button"
                            tabindex="0"
                            x-on:keydown.enter.prevent="open = !open"
                            x-on:keydown.space.prevent="open = !open"
                            aria-label="جزئیات پیگیری فراموش‌شده {{ $followUp['customer'] }}"
                        >
                            <td>
                                <svg class="mx-auto h-4 w-4 text-zinc-400 transition" x-bind:class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </td>
                            <td>
                                <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $followUp['customer'] }}</p>
                                @if ($followUp['company'] && $followUp['company'] !== $followUp['customer'])
                                    <p class="truncate text-xs text-zinc-500">{{ $followUp['company'] }}</p>
                                @endif
                            </td>
                            <td><span dir="ltr">{{ $followUp['phone'] ?? '—' }}</span></td>
                            <td class="truncate">{{ $followUp['employee'] }}</td>
                            <td class="truncate">{{ $followUp['forgotten_action'] }}</td>
                            <td>
                                <p class="truncate tabular-nums">{{ $followUp['due_date'] }}</p>
                                <p class="truncate text-xs text-red-600 dark:text-red-400">{{ $followUp['days_overdue'] }} روز تأخیر</p>
                            </td>
                        </tr>
                        <tr x-show="open" x-cloak class="saas-opportunity-detail-row">
                            <td colspan="6">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 flex-1 space-y-4">
                                        <div>
                                            <p class="text-xs font-semibold text-zinc-500">اقدام‌های موعدگذشته</p>
                                            @if (! empty($followUp['forgotten_actions']))
                                                <ul class="mt-2 space-y-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                                                    @foreach ($followUp['forgotten_actions'] as $action)
                                                        <li class="flex items-start gap-2">
                                                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-red-500"></span>
                                                            <span>{{ $action }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="mt-2 text-sm text-zinc-400">اقدام پیگیری ثبت نشده است.</p>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-zinc-500">خلاصه تماس</p>
                                            @if (! empty($followUp['summary']))
                                                <p class="mt-2 text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $followUp['summary'] }}</p>
                                            @else
                                                <p class="mt-2 text-sm text-zinc-400">خلاصه‌ای برای این تماس ثبت نشده است.</p>
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ route('employer.intelligence.show', $followUp['analysis_id']) }}" class="saas-btn-secondary shrink-0 self-start" x-on:click.stop>
                                        نمایش تحلیل
                                        <svg class="h-4 w-4 rtl:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @endforeach
            </table>
        </div>
    @endif
</div>
