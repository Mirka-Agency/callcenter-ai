<div class="saas-card overflow-hidden p-0" data-tour="dashboard-opportunities">
    <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-6 sm:pt-6">
        <div>
            <h2 class="text-lg font-semibold">فرصت‌های معاملاتی جدید</h2>
            <p class="mt-1 text-sm text-zinc-500">لیدهای باکیفیت اخیر که باید برای بستن فروش پیگیری شوند</p>
        </div>
        @if (! empty($tradingOpportunities))
            <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-sm font-medium tabular-nums text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                {{ count($tradingOpportunities) }} فرصت
            </span>
        @endif
    </div>

    @if (empty($tradingOpportunities))
        <div class="px-4 pb-6 sm:px-6">
            <x-saas.empty-state
                title="{{ __('ui.empty.no_trading_opportunities.title') }}"
                description="{{ __('ui.empty.no_trading_opportunities.description') }}"
            />
        </div>
    @else
        <div class="saas-opportunity-table-wrap">
            <table class="saas-opportunity-table">
                <colgroup>
                    <col class="saas-opportunity-col-toggle">
                    <col class="saas-opportunity-col-name">
                    <col class="saas-opportunity-col-phone">
                    <col class="saas-opportunity-col-agent">
                    <col class="saas-opportunity-col-date">
                    <col class="saas-opportunity-col-product">
                    <col class="saas-opportunity-col-lead">
                    <col class="saas-opportunity-col-prob">
                </colgroup>
                <thead>
                    <tr>
                        <th></th>
                        <th>نام شخص/شرکت</th>
                        <th>شماره تماس</th>
                        <th>نام کارشناس</th>
                        <th>تاریخ</th>
                        <th>محصول/سرویس قابل فروش</th>
                        <th>کیفیت لید</th>
                        <th>احتمال خرید</th>
                    </tr>
                </thead>
                @foreach ($tradingOpportunities as $opportunity)
                    <tbody
                        wire:key="opportunity-{{ $opportunity['analysis_id'] }}"
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
                            aria-label="جزئیات فرصت {{ $opportunity['customer'] }}"
                        >
                            <td>
                                <svg class="mx-auto h-4 w-4 text-zinc-400 transition" x-bind:class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </td>
                            <td>
                                <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $opportunity['customer'] }}</p>
                                @if ($opportunity['company'] && $opportunity['company'] !== $opportunity['customer'])
                                    <p class="truncate text-xs text-zinc-500">{{ $opportunity['company'] }}</p>
                                @endif
                            </td>
                            <td><span dir="ltr">{{ $opportunity['phone'] ?? '—' }}</span></td>
                            <td class="truncate">{{ $opportunity['employee'] }}</td>
                            <td class="truncate">{{ $opportunity['date'] }}</td>
                            <td class="truncate">{{ $opportunity['product'] ?? '—' }}</td>
                            <td>{{ $opportunity['lead_score'] ?? '—' }}</td>
                            <td>{{ $opportunity['purchase_probability'] !== null ? $opportunity['purchase_probability'].'٪' : '—' }}</td>
                        </tr>
                        <tr x-show="open" x-cloak class="saas-opportunity-detail-row">
                            <td colspan="8">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 flex-1 space-y-4">
                                        <div>
                                            <p class="text-xs font-semibold text-zinc-500">پیگیری</p>
                                            @if (! empty($opportunity['next_actions']))
                                                <ul class="mt-2 space-y-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                                                    @foreach ($opportunity['next_actions'] as $action)
                                                        <li class="flex items-start gap-2">
                                                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span>
                                                            <span>{{ $action }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="mt-2 text-sm text-zinc-400">اقدام پیگیری ثبت نشده است.</p>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-zinc-500">تگ‌های پیگیری</p>
                                            @if (! empty($opportunity['follow_up_tags']))
                                                <div class="mt-2 flex flex-wrap gap-1.5">
                                                    @foreach ($opportunity['follow_up_tags'] as $tag)
                                                        <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $tag }}</span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="mt-2 text-sm text-zinc-400">تگی برای پیگیری ثبت نشده است.</p>
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ route('employer.intelligence.show', $opportunity['analysis_id']) }}" class="saas-btn-secondary shrink-0 self-start" x-on:click.stop>
                                        تحلیل بیشتر
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
