@php
    $forgottenCount = count($forgottenFollowUps);
    $groups = [
        'urgent' => [
            'label' => 'فوری',
            'hint' => 'سه روز یا بیشتر از موعد گذشته است.',
            'items' => array_values(array_filter(
                $forgottenFollowUps,
                fn (array $followUp): bool => (int) $followUp['days_overdue'] >= 3,
            )),
        ],
        'delayed' => [
            'label' => 'در حال تأخیر',
            'hint' => 'کمتر از سه روز از موعد گذشته است.',
            'items' => array_values(array_filter(
                $forgottenFollowUps,
                fn (array $followUp): bool => (int) $followUp['days_overdue'] < 3,
            )),
        ],
    ];
    $badgeClass = [
        'urgent' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
        'delayed' => 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
    ];
@endphp

<div
    class="saas-card min-w-0 overflow-hidden p-0"
    data-tour="dashboard-forgotten-followups"
    @if ($forgottenCount > 0)
        x-data="{ filter: 'all' }"
    @endif
>
    <div class="px-4 py-4 sm:px-6 sm:pt-6">
        <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold">پیگیری‌های فراموش‌شده</h2>
            @if ($forgottenCount > 0)
                <span class="rounded-lg bg-red-50 px-2.5 py-1 text-sm font-medium tabular-nums text-red-700 dark:bg-red-500/10 dark:text-red-400">
                    {{ $forgottenCount }} پیگیری معوق
                </span>
            @endif
        </div>
        <p class="mt-1 text-sm leading-6 text-zinc-500">تماسی که باید گرفته می‌شد و هنوز گرفته نشده.</p>
    </div>

    @if ($forgottenCount === 0)
        <div class="px-4 pb-6 sm:px-6">
            <x-saas.empty-state
                title="{{ __('ui.empty.no_forgotten_followups.title') }}"
                description="{{ __('ui.empty.no_forgotten_followups.description') }}"
            />
        </div>
    @else
        <div class="saas-forgotten-filters px-4 pb-3 sm:px-6" role="tablist" aria-label="دسته‌بندی پیگیری‌ها">
            <button
                type="button"
                role="tab"
                class="saas-forgotten-filter"
                x-bind:class="{ 'is-active': filter === 'all' }"
                x-bind:aria-selected="(filter === 'all').toString()"
                x-on:click="filter = 'all'"
            >
                همه
                <span class="tabular-nums">{{ $forgottenCount }}</span>
            </button>
            @foreach ($groups as $key => $group)
                <button
                    type="button"
                    role="tab"
                    @class(['saas-forgotten-filter', 'is-'.$key])
                    x-bind:class="{ 'is-active': filter === '{{ $key }}' }"
                    x-bind:aria-selected="(filter === '{{ $key }}').toString()"
                    x-on:click="filter = '{{ $key }}'"
                >
                    {{ $group['label'] }}
                    <span class="tabular-nums">{{ count($group['items']) }}</span>
                </button>
            @endforeach
        </div>

        <div class="saas-forgotten-list">
            @foreach ($groups as $key => $group)
                @if ($group['items'] !== [])
                    <section
                        data-forgotten-group="{{ $key }}"
                        x-show="filter === 'all' || filter === '{{ $key }}'"
                    >
                        <div class="mb-2 px-0.5">
                            <h3 class="text-xs font-semibold text-zinc-700 dark:text-zinc-200">{{ $group['label'] }}</h3>
                            <p class="mt-0.5 text-xs text-zinc-500">{{ $group['hint'] }}</p>
                        </div>
                        <div class="flex flex-col gap-2">
                            @foreach ($group['items'] as $followUp)
                                @php
                                    $actions = $followUp['forgotten_actions'] ?? [];
                                    $extraActions = count($actions) > 1 ? array_slice($actions, 1) : [];
                                    $canExpand = $extraActions !== [] || ! empty($followUp['summary']);
                                    $detailId = 'forgotten-detail-'.$followUp['analysis_id'];
                                @endphp
                                <article
                                    @class(['saas-forgotten-item', 'saas-forgotten-item--'.$key])
                                    wire:key="forgotten-follow-up-{{ $followUp['analysis_id'] }}"
                                    @if ($canExpand) x-data="{ open: {{ $forgottenCount === 1 ? 'true' : 'false' }} }" @endif
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $followUp['customer'] }}</p>
                                            @if ($followUp['company'] && $followUp['company'] !== $followUp['customer'])
                                                <p class="mt-0.5 truncate text-xs text-zinc-500">{{ $followUp['company'] }}</p>
                                            @endif
                                        </div>
                                        <span @class(['shrink-0 rounded-full px-2 py-1 text-xs font-semibold', $badgeClass[$key]])>
                                            {{ $group['label'] }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm font-medium leading-6 text-zinc-800 dark:text-zinc-200">{{ $followUp['forgotten_action'] }}</p>
                                    @if ($extraActions !== [])
                                        <p class="mt-1 text-xs text-zinc-500">و {{ count($extraActions) === 1 ? 'یک' : count($extraActions) }} اقدام دیگر</p>
                                    @endif

                                    <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-zinc-500">
                                        <span>{{ $followUp['employee'] }}</span>
                                        @if ($followUp['phone'])
                                            <span class="text-zinc-400 dark:text-zinc-500" aria-hidden="true">·</span>
                                            <a href="tel:{{ $followUp['phone'] }}" dir="ltr" class="tabular-nums text-zinc-700 underline-offset-2 hover:underline dark:text-zinc-200">{{ $followUp['phone'] }}</a>
                                        @endif
                                        <span class="text-zinc-400 dark:text-zinc-500" aria-hidden="true">·</span>
                                        <span class="tabular-nums">{{ $followUp['days_overdue'] }} روز تأخیر</span>
                                        <span class="text-zinc-400 dark:text-zinc-500" aria-hidden="true">·</span>
                                        <span class="tabular-nums">{{ $followUp['call_date'] }}</span>
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a
                                                href="{{ route('employer.intelligence.show', $followUp['analysis_id']) }}"
                                                @class(['saas-forgotten-link', 'saas-forgotten-link--urgent' => $key === 'urgent'])
                                            >
                                                نمایش تحلیل
                                            </a>
                                        </div>
                                        @if ($canExpand)
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200"
                                                x-on:click="open = !open"
                                                x-bind:aria-expanded="open.toString()"
                                                aria-controls="{{ $detailId }}"
                                            >
                                                <span x-text="open ? 'بستن جزئیات' : 'جزئیات'">{{ $forgottenCount === 1 ? 'بستن جزئیات' : 'جزئیات' }}</span>
                                                <svg class="h-4 w-4 transition" x-bind:class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                        @endif
                                    </div>

                                    @if ($canExpand)
                                        <div id="{{ $detailId }}" x-show="open" x-cloak x-collapse class="mt-3 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                                            @if ($extraActions !== [])
                                                <p class="text-xs font-semibold text-zinc-500">سایر اقدام‌ها</p>
                                                <ul class="mt-2 space-y-1.5 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                                                    @foreach ($extraActions as $action)
                                                        <li class="flex items-start gap-2">
                                                            <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-red-500"></span>
                                                            <span>{{ $action }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            @if (! empty($followUp['summary']))
                                                <div @class(['mt-3' => $extraActions !== []])>
                                                    <p class="text-xs font-semibold text-zinc-500">خلاصه تماس</p>
                                                    <p class="mt-1 text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $followUp['summary'] }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </section>
                @else
                    <p
                        class="px-1 py-6 text-center text-sm text-zinc-500"
                        x-show="filter === '{{ $key }}'"
                        x-cloak
                    >
                        در دسته {{ $group['label'] }} پیگیری‌ای نیست.
                    </p>
                @endif
            @endforeach
        </div>
    @endif
</div>
