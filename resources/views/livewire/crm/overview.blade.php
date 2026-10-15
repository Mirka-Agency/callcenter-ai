@php
    use App\Application\Crm\Deals\DealMoney;
    use App\Support\PersianNumber;

    $stages = $stats['stages'];
    $stageMax = max(1, ...(array_column($stages, 'amount_rials') ?: [0]));
    $trend = $stats['trend'];
    $trendMax = max(1, ...array_map(
        fn (array $day): int => $day['new_count'] + $day['won_count'],
        $trend ?: [['new_count' => 0, 'won_count' => 0]],
    ));
@endphp

<div class="saas-page space-y-6">
    <nav class="text-sm text-zinc-500" aria-label="مسیر">
        <span>CRM</span>
        <span class="mx-2">/</span>
        <span class="text-zinc-800 dark:text-zinc-200">آمار</span>
    </nav>

    <x-saas.page-header
        title="آمار و تحلیل CRM"
        description="سلامت کاریز، نرخ برد، فاکتورها و یادآورها — برای اقدام سریع به کاریز یا یادآورها بروید."
    >
        <x-slot:actions>
            <a href="{{ route($portal.'.crm.deals.list') }}" class="saas-btn-secondary text-sm" wire:navigate>فرصت‌ها</a>
            <a href="{{ route($portal.'.crm.invoices.index') }}" class="saas-btn-secondary text-sm" wire:navigate>اسناد فروش</a>
        </x-slot:actions>
    </x-saas.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-saas.stat-card
            label="فرصت‌های باز"
            :value="PersianNumber::format($stats['open_count'])"
            :hint="DealMoney::formatRials($stats['open_amount_rials'])"
        />
        <x-saas.stat-card
            label="نرخ برد ۷ روز"
            :value="$stats['win_rate_7d'] === null ? '—' : PersianNumber::format($stats['win_rate_7d'], 1).'٪'"
            :hint="PersianNumber::format($stats['won_7d_count']).' برد · '.PersianNumber::format($stats['lost_7d_count']).' باخت'"
        />
        <x-saas.stat-card
            label="ارزش برد ۷ روز"
            :value="DealMoney::formatRials($stats['won_7d_amount_rials'])"
            :hint="PersianNumber::format($stats['new_deals_7d']).' فرصت جدید'"
        />
        <x-saas.stat-card
            label="فاکتور صادر ۷ روز"
            :value="DealMoney::formatRials($stats['issued_7d_amount_rials'])"
            :hint="PersianNumber::format($stats['issued_7d_invoice_count']).' فاکتور · '.PersianNumber::format($stats['issued_7d_proforma_count']).' پیش‌فاکتور'"
        />

        <x-saas.stat-card
            label="یادآور سررسید گذشته"
            :value="PersianNumber::format($stats['overdue_reminders'])"
            :hint="PersianNumber::format($stats['reminders_due_24h']).' تا ۲۴ ساعت · '.PersianNumber::format($stats['reminders_completed_7d']).' انجام‌شده ۷ روز'"
            :tone="$stats['overdue_reminders'] > 0 ? 'bad' : null"
        />
        <x-saas.stat-card
            label="بسته شدن سررسید گذشته"
            :value="PersianNumber::format($stats['overdue_close_count'])"
            hint="فرصت باز با تاریخ بسته شدن گذشته"
            :tone="$stats['overdue_close_count'] > 0 ? 'medium' : null"
        />
        <x-saas.stat-card
            label="مخاطب جدید ۷ روز"
            :value="PersianNumber::format($stats['new_contacts_7d'])"
            :hint="'امروز: '.PersianNumber::format($stats['new_contacts_today'])"
        />
        <x-saas.stat-card
            label="خلاصه تماس در CRM"
            :value="PersianNumber::format($stats['crm_call_summaries_7d'])"
            hint="یادداشت‌های متصل به تماس در ۷ روز"
        />

        <x-saas.stat-card
            label="پیش‌نویس اسناد"
            :value="PersianNumber::format($stats['draft_documents'])"
        />
        <x-saas.stat-card
            label="پیش‌فاکتور صادر (باز)"
            :value="PersianNumber::format($stats['open_issued_proformas'])"
        />
        <x-saas.stat-card
            label="کاتالوگ فعال"
            :value="PersianNumber::format($stats['catalog_active']).' / '.PersianNumber::format($stats['catalog_total'])"
            :hint="PersianNumber::format($stats['catalog_products']).' محصول · '.PersianNumber::format($stats['catalog_services']).' خدمت'"
        />
        <x-saas.stat-card
            label="برد امروز"
            :value="DealMoney::formatRials($stats['won_today_amount_rials'])"
            :hint="PersianNumber::format($stats['won_today_count']).' برد · '.PersianNumber::format($stats['new_deals_today']).' جدید'"
        />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="saas-card space-y-4 p-5">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">روند ۷ روزه فرصت‌ها</h2>
                <p class="mt-1 text-sm text-zinc-500">فرصت جدید و برد در هر روز</p>
            </div>
            <ul class="space-y-3">
                @forelse ($trend as $day)
                    @php
                        $activity = $day['new_count'] + $day['won_count'];
                        $width = $trendMax > 0
                            ? max(8, (int) round(($activity / $trendMax) * 100))
                            : 8;
                    @endphp
                    <li class="space-y-1.5" wire:key="trend-{{ $day['date'] }}">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="tabular-nums text-zinc-600 dark:text-zinc-300">{{ $day['date'] }}</span>
                            <span class="text-zinc-500">
                                {{ PersianNumber::format($day['new_count']) }} جدید ·
                                {{ PersianNumber::format($day['won_count']) }} برد ·
                                {{ DealMoney::formatRials($day['invoice_amount_rials']) }} فاکتور
                            </span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-2 rounded-full bg-indigo-500" style="width: {{ $width }}%"></div>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-zinc-500">هنوز داده‌ای برای نمایش نیست.</li>
                @endforelse
            </ul>
        </section>

        <section class="saas-card flex flex-col space-y-4 p-5">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                    ارزش مراحل کاریز@if ($stats['pipeline']) — {{ $stats['pipeline']->name }}@endif
                </h2>
                <p class="mt-1 text-sm text-zinc-500">فرصت‌های باز به تفکیک مرحله</p>
            </div>

            @if (empty($stages))
                <p class="text-sm text-zinc-500">هنوز پایپ‌لاینی نیست. پس از ساخت کاریز، ارزش مراحل اینجا دیده می‌شود.</p>
            @else
                <ul class="space-y-4">
                    @foreach ($stages as $stage)
                        @php
                            $percent = $stageMax > 0
                                ? max($stage['amount_rials'] > 0 ? 6 : 0, (int) round(($stage['amount_rials'] / $stageMax) * 100))
                                : 0;
                        @endphp
                        <li class="space-y-1.5" wire:key="stage-stat-{{ $stage['id'] }}">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="flex items-center gap-2 font-medium text-zinc-800 dark:text-zinc-100">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $stage['color'] }}"></span>
                                    {{ $stage['name'] }}
                                </span>
                                <span class="tabular-nums text-zinc-600 dark:text-zinc-300">{{ DealMoney::formatRials($stage['amount_rials']) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div
                                    class="h-2 rounded-full transition-all"
                                    style="width: {{ $percent }}%; background: {{ $stage['color'] }}"
                                ></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-auto pt-2">
                <a href="{{ route($portal.'.crm.deals.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400" wire:navigate>
                    مشاهده کاریز
                </a>
            </div>
        </section>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="saas-card space-y-4 p-5">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">اقلام پرفروش فاکتور</h2>
                <p class="text-sm text-zinc-500">بر اساس مبلغ خطوط فاکتور صادرشده در ۷ روز</p>
            </div>

            @if (empty($stats['top_invoice_items']))
                <p class="py-6 text-sm text-zinc-500">هنوز فاکتوری صادر نشده است.</p>
            @else
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($stats['top_invoice_items'] as $item)
                        <li class="flex items-center justify-between gap-3 py-3 text-sm" wire:key="invoice-item-{{ $loop->index }}">
                            <span class="text-zinc-800 dark:text-zinc-100">{{ $item['label'] }}</span>
                            <span class="tabular-nums text-zinc-600 dark:text-zinc-300">{{ DealMoney::formatRials($item['amount_rials']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="saas-card space-y-4 p-5">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">بینش تماس‌های متصل به CRM</h2>
                <p class="text-sm text-zinc-500">علت تماس و احساس از خلاصه‌های تماس در ۷ روز</p>
            </div>

            @if ($stats['crm_call_summaries_7d'] === 0)
                <p class="py-6 text-sm text-zinc-500">در ۷ روز اخیر تماس متصل به CRM ثبت نشده است.</p>
            @else
                <div class="space-y-5">
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-zinc-700 dark:text-zinc-200">علت‌های تماس پرتکرار</h3>
                        <ul class="space-y-2">
                            @forelse ($stats['call_reasons'] as $reason)
                                <li class="flex items-center justify-between gap-3 text-sm" wire:key="call-reason-{{ $loop->index }}">
                                    <span class="text-zinc-700 dark:text-zinc-300">{{ $reason['label'] }}</span>
                                    <span class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ PersianNumber::format($reason['count']) }}</span>
                                </li>
                            @empty
                                <li class="text-sm text-zinc-500">علتی ثبت نشده است.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-zinc-700 dark:text-zinc-200">احساس</h3>
                        <ul class="space-y-2">
                            @forelse ($stats['call_sentiments'] as $sentiment)
                                <li class="flex items-center justify-between gap-3 text-sm" wire:key="call-sentiment-{{ $loop->index }}">
                                    <span class="text-zinc-700 dark:text-zinc-300">{{ $sentiment['label'] }}</span>
                                    <span class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ PersianNumber::format($sentiment['count']) }}</span>
                                </li>
                            @empty
                                <li class="text-sm text-zinc-500">احساسی ثبت نشده است.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>
