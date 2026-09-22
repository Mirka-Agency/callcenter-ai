@php
    $satisfied = $sentimentCustomers['satisfied'] ?? [];
    $dissatisfied = $sentimentCustomers['dissatisfied'] ?? [];
@endphp

<div class="grid items-start gap-6 lg:grid-cols-2" data-tour="dashboard-sentiment-customers">
    <section class="saas-card overflow-hidden p-0">
        <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-6 sm:pt-6">
            <div>
                <h2 class="text-lg font-semibold">مشتریان راضی</h2>
                <p class="mt-1 text-sm text-zinc-500">بر اساس مکالمه‌های با احساس مثبت در ۳۰ روز اخیر</p>
            </div>
            @if (! empty($satisfied))
                <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-sm font-medium tabular-nums text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                    {{ count($satisfied) }} مشتری
                </span>
            @endif
        </div>

        @if (empty($satisfied))
            <div class="px-4 pb-6 sm:px-6">
                <x-saas.empty-state
                    title="{{ __('ui.empty.no_satisfied_customers.title') }}"
                    description="{{ __('ui.empty.no_satisfied_customers.description') }}"
                    class="py-10"
                />
            </div>
        @else
            <div class="saas-sentiment-list">
                @foreach ($satisfied as $customer)
                    <a
                        href="{{ route('employer.intelligence.show', $customer['analysis_id']) }}"
                        wire:key="satisfied-customer-{{ $customer['analysis_id'] }}"
                        class="saas-sentiment-item"
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
    </section>

    <section class="saas-card overflow-hidden p-0">
        <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-6 sm:pt-6">
            <div>
                <h2 class="text-lg font-semibold">مشتریان ناراضی</h2>
                <p class="mt-1 text-sm text-zinc-500">بر اساس مکالمه‌های با احساس منفی در ۳۰ روز اخیر</p>
            </div>
            @if (! empty($dissatisfied))
                <span class="rounded-lg bg-red-50 px-2.5 py-1 text-sm font-medium tabular-nums text-red-700 dark:bg-red-500/10 dark:text-red-400">
                    {{ count($dissatisfied) }} مشتری
                </span>
            @endif
        </div>

        @if (empty($dissatisfied))
            <div class="px-4 pb-6 sm:px-6">
                <x-saas.empty-state
                    title="{{ __('ui.empty.no_dissatisfied_customers.title') }}"
                    description="{{ __('ui.empty.no_dissatisfied_customers.description') }}"
                    class="py-10"
                />
            </div>
        @else
            <div class="saas-sentiment-list">
                @foreach ($dissatisfied as $customer)
                    <a
                        href="{{ route('employer.intelligence.show', $customer['analysis_id']) }}"
                        wire:key="dissatisfied-customer-{{ $customer['analysis_id'] }}"
                        class="saas-sentiment-item saas-sentiment-item--warning"
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
    </section>
</div>
