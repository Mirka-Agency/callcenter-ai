@php
    $satisfied = $sentimentCustomers['satisfied'] ?? [];
    $dissatisfied = $sentimentCustomers['dissatisfied'] ?? [];
    $tabs = [
        'satisfied' => [
            'label' => 'مشتریان راضی',
            'hint' => 'بر اساس مکالمه‌های با احساس مثبت در ۳۰ روز اخیر',
            'customers' => $satisfied,
            'empty' => 'no_satisfied_customers',
            'countClass' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
            'itemClass' => '',
        ],
        'dissatisfied' => [
            'label' => 'مشتریان ناراضی',
            'hint' => 'بر اساس مکالمه‌های با احساس منفی در ۳۰ روز اخیر',
            'customers' => $dissatisfied,
            'empty' => 'no_dissatisfied_customers',
            'countClass' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
            'itemClass' => 'saas-sentiment-item--warning',
        ],
    ];
@endphp

<section
    class="saas-card w-full overflow-hidden p-0 lg:w-1/2"
    data-tour="dashboard-sentiment-customers"
    x-data="{ tab: 'satisfied' }"
>
    <div class="saas-sentiment-tabs px-4 pt-4 sm:px-6 sm:pt-6" role="tablist" aria-label="مشتریان راضی و ناراضی">
        @foreach ($tabs as $key => $panel)
            <button
                type="button"
                role="tab"
                id="sentiment-tab-{{ $key }}"
                aria-controls="sentiment-panel-{{ $key }}"
                :aria-selected="tab === '{{ $key }}'"
                @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'saas-sentiment-tab--active' : 'saas-sentiment-tab--idle'"
                class="saas-sentiment-tab"
            >
                <span>{{ $panel['label'] }}</span>
                @if (! empty($panel['customers']))
                    <span class="tabular-nums opacity-90">({{ count($panel['customers']) }})</span>
                @endif
            </button>
        @endforeach
    </div>

    @foreach ($tabs as $key => $panel)
        <div
            id="sentiment-panel-{{ $key }}"
            role="tabpanel"
            aria-labelledby="sentiment-tab-{{ $key }}"
            x-show="tab === '{{ $key }}'"
            @if ($key !== 'satisfied') x-cloak @endif
        >
            <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-6">
                <p class="text-sm text-zinc-500">{{ $panel['hint'] }}</p>
                @if (! empty($panel['customers']))
                    <span @class(['rounded-lg px-2.5 py-1 text-sm font-medium tabular-nums', $panel['countClass']])>
                        {{ count($panel['customers']) }} مشتری
                    </span>
                @endif
            </div>

            @if (empty($panel['customers']))
                <div class="px-4 pb-6 sm:px-6">
                    <x-saas.empty-state
                        title="{{ __('ui.empty.'.$panel['empty'].'.title') }}"
                        description="{{ __('ui.empty.'.$panel['empty'].'.description') }}"
                        class="py-10"
                    />
                </div>
            @else
                <div class="saas-sentiment-list">
                    @foreach ($panel['customers'] as $customer)
                        <a
                            href="{{ route('employer.intelligence.show', $customer['analysis_id']) }}"
                            wire:key="{{ $key }}-customer-{{ $customer['analysis_id'] }}"
                            @class(['saas-sentiment-item', $panel['itemClass']])
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $customer['customer'] }}</p>
                                    <span class="text-xs text-zinc-400">{{ $customer['call_date'] }}</span>
                                </div>
                                @if ($customer['company'] && $customer['company'] !== $customer['customer'])
                                    <p class="mt-0.5 truncate text-xs text-zinc-500">{{ $customer['company'] }}</p>
                                @endif
                                <p class="mt-1 text-sm text-zinc-500">{{ $customer['employee'] }}@if ($customer['phone']) <span dir="ltr">{{ $customer['phone'] }}</span>@endif</p>
                                <p class="mt-1 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $customer['highlight'] ?? $customer['summary'] ?? '—' }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</section>
