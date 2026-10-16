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
    $callReasons = $stats['call_reasons'];
    $reasonMax = max(1, ...(array_column($callReasons, 'count') ?: [0]));
    $callSentiments = $stats['call_sentiments'];
    $sentimentMax = max(1, ...(array_column($callSentiments, 'count') ?: [0]));
    $sentimentColors = [
        'مثبت' => '#059669',
        'خنثی' => '#0EA5E9',
        'منفی' => '#E11D48',
        'ترکیبی' => '#7C25FD',
    ];
    $itemTones = [
        ['bg' => 'bg-violet-50 text-violet-600 dark:bg-violet-950/40 dark:text-violet-300'],
        ['bg' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300'],
        ['bg' => 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300'],
        ['bg' => 'bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300'],
        ['bg' => 'bg-orange-50 text-orange-600 dark:bg-orange-950/40 dark:text-orange-300'],
        ['bg' => 'bg-sky-50 text-sky-600 dark:bg-sky-950/40 dark:text-sky-300'],
        ['bg' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-300'],
        ['bg' => 'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300'],
    ];
@endphp

<div class="saas-page space-y-6">
    <x-saas.page-header
        title="آمار و تحلیل CRM"
        description="سلامت کاریز، نرخ برد، فاکتورها و یادآورها — برای اقدام سریع به کاریز یا یادآورها بروید."
    >
        <x-slot:actions>
            <a href="{{ route($portal.'.crm.invoices.index') }}" class="saas-btn-secondary saas-btn-secondary--soft text-sm" wire:navigate>
                <x-saas.icon name="document" class="h-4 w-4" />
                اسناد فروش
            </a>
            <a href="{{ route($portal.'.crm.deals.list') }}" class="saas-btn-brand text-sm" wire:navigate>
                فرصت‌ها
                <x-saas.icon name="arrow-left" class="h-4 w-4" />
            </a>
        </x-slot:actions>
    </x-saas.page-header>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-saas.stat-card
            label="فرصت‌های باز"
            :value="PersianNumber::format($stats['open_count'])"
            :hint="DealMoney::formatRials($stats['open_amount_rials'])"
            icon-tone="amber"
            tooltip="تعداد و ارزش فرصت‌های باز در کاریز"
        >
            <x-slot:icon><x-saas.icon name="folder" /></x-slot:icon>
        </x-saas.stat-card>
        <x-saas.stat-card
            label="نرخ برد ۷ روز"
            :value="$stats['win_rate_7d'] === null ? '—' : PersianNumber::format($stats['win_rate_7d'], 1).'٪'"
            :hint="PersianNumber::format($stats['won_7d_count']).' برد · '.PersianNumber::format($stats['lost_7d_count']).' باخت'"
            icon-tone="orange"
            tooltip="نسبت برد به کل فرصت‌های بسته‌شده در ۷ روز"
        >
            <x-slot:icon><x-saas.icon name="trophy" /></x-slot:icon>
        </x-saas.stat-card>
        <x-saas.stat-card
            label="ارزش برد ۷ روز"
            :value="DealMoney::formatRials($stats['won_7d_amount_rials'])"
            :hint="PersianNumber::format($stats['new_deals_7d']).' فرصت جدید'"
            icon-tone="green"
            tooltip="مجموع مبلغ فرصت‌های برنده‌شده در ۷ روز"
        >
            <x-slot:icon><x-saas.icon name="trend-up" /></x-slot:icon>
        </x-saas.stat-card>
        <x-saas.stat-card
            label="فاکتور صادر ۷ روز"
            :value="DealMoney::formatRials($stats['issued_7d_amount_rials'])"
            :hint="PersianNumber::format($stats['issued_7d_invoice_count']).' فاکتور · '.PersianNumber::format($stats['issued_7d_proforma_count']).' پیش‌فاکتور'"
            icon-tone="violet"
            tooltip="مبلغ فاکتور و پیش‌فاکتورهای صادرشده در ۷ روز"
        >
            <x-slot:icon><x-saas.icon name="document" /></x-slot:icon>
        </x-saas.stat-card>

        <x-saas.stat-card
            label="یادآور سررسید گذشته"
            :value="PersianNumber::format($stats['overdue_reminders'])"
            :hint="PersianNumber::format($stats['reminders_due_24h']).' تا ۲۴ ساعت · '.PersianNumber::format($stats['reminders_completed_7d']).' انجام‌شده ۷ روز'"
            icon-tone="rose"
            tooltip="یادآورهای باز که از موعدشان گذشته‌اند"
        >
            <x-slot:icon><x-saas.icon name="bell" /></x-slot:icon>
        </x-saas.stat-card>
        <x-saas.stat-card
            label="بسته شدن سررسید گذشته"
            :value="PersianNumber::format($stats['overdue_close_count'])"
            hint="فرصت باز با تاریخ بسته شدن گذشته"
            icon-tone="orange"
            tooltip="فرصت‌های باز با تاریخ بسته‌شدن گذشته"
        >
            <x-slot:icon><x-saas.icon name="clock" /></x-slot:icon>
        </x-saas.stat-card>
        <x-saas.stat-card
            label="مخاطب جدید ۷ روز"
            :value="PersianNumber::format($stats['new_contacts_7d'])"
            :hint="'امروز: '.PersianNumber::format($stats['new_contacts_today'])"
            icon-tone="blue"
            tooltip="مخاطبان تازه‌ایجادشده در ۷ روز اخیر"
        >
            <x-slot:icon><x-saas.icon name="user-plus" /></x-slot:icon>
        </x-saas.stat-card>
        <x-saas.stat-card
            label="خلاصه تماس در CRM"
            :value="PersianNumber::format($stats['crm_call_summaries_7d'])"
            hint="یادداشت‌های متصل به تماس در ۷ روز"
            icon-tone="violet"
            tooltip="خلاصه‌های تماس متصل به فرصت‌ها در ۷ روز"
        >
            <x-slot:icon><x-saas.icon name="phone" /></x-slot:icon>
        </x-saas.stat-card>

        <x-saas.stat-card
            label="پیش‌نویس اسناد"
            :value="PersianNumber::format($stats['draft_documents'])"
            icon-tone="indigo"
            tooltip="اسناد فروشی که هنوز صادر نشده‌اند"
        >
            <x-slot:icon><x-saas.icon name="document" /></x-slot:icon>
        </x-saas.stat-card>
        <x-saas.stat-card
            label="پیش‌فاکتور صادر (باز)"
            :value="PersianNumber::format($stats['open_issued_proformas'])"
            icon-tone="sky"
            tooltip="پیش‌فاکتورهای صادرشدهٔ فعال و باز"
        >
            <x-slot:icon><x-saas.icon name="document" /></x-slot:icon>
        </x-saas.stat-card>
        <x-saas.stat-card
            label="کاتالوگ فعال"
            :value="PersianNumber::format($stats['catalog_active']).' / '.PersianNumber::format($stats['catalog_total'])"
            :hint="PersianNumber::format($stats['catalog_products']).' محصول · '.PersianNumber::format($stats['catalog_services']).' خدمت'"
            icon-tone="green"
            tooltip="محصولات و خدمات فعال در کاتالوگ"
        >
            <x-slot:icon><x-saas.icon name="cube" /></x-slot:icon>
        </x-saas.stat-card>
        <x-saas.stat-card
            label="برد امروز"
            :value="DealMoney::formatRials($stats['won_today_amount_rials'])"
            :hint="PersianNumber::format($stats['won_today_count']).' برد · '.PersianNumber::format($stats['new_deals_today']).' جدید'"
            icon-tone="gold"
            tooltip="مبلغ و تعداد بردهای ثبت‌شده امروز"
        >
            <x-slot:icon><x-saas.icon name="medal" /></x-slot:icon>
        </x-saas.stat-card>
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
                            ? max($activity > 0 ? 8 : 0, (int) round(($activity / $trendMax) * 100))
                            : 0;
                    @endphp
                    <li class="flex items-center gap-3" wire:key="trend-{{ $day['date'] }}">
                        <span class="w-[5.75rem] shrink-0 tabular-nums text-sm text-zinc-600 dark:text-zinc-300">{{ $day['date'] }}</span>
                        <div class="crm-overview-meter crm-overview-meter--brand min-w-0 flex-1">
                            <span style="width: {{ $width }}%"></span>
                        </div>
                        <span class="min-w-0 shrink-0 text-end text-sm text-zinc-500 sm:min-w-[11rem]">
                            {{ PersianNumber::format($day['new_count']) }} جدید ·
                            {{ PersianNumber::format($day['won_count']) }} برد ·
                            {{ DealMoney::formatRials($day['invoice_amount_rials']) }}
                        </span>
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
                            <div class="crm-overview-meter">
                                <span style="width: {{ $percent }}%; background: {{ $stage['color'] }}"></span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-auto pt-2">
                <a href="{{ route($portal.'.crm.deals.index') }}" class="crm-overview-link" wire:navigate>
                    مشاهده معاملات
                    <x-saas.icon name="arrow-left" class="h-3.5 w-3.5" />
                </a>
            </div>
        </section>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="saas-card space-y-4 p-5">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">بینش تماس‌های متصل به CRM</h2>
                <p class="mt-1 text-sm text-zinc-500">علت تماس و احساس از خلاصه‌های تماس در ۷ روز</p>
            </div>

            @if ($stats['crm_call_summaries_7d'] === 0)
                <p class="py-6 text-sm text-zinc-500">در ۷ روز اخیر تماس متصل به CRM ثبت نشده است.</p>
            @else
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-zinc-700 dark:text-zinc-200">احساس</h3>
                        <ul class="space-y-2.5">
                            @forelse ($callSentiments as $sentiment)
                                @php
                                    $width = $sentimentMax > 0
                                        ? max($sentiment['count'] > 0 ? 8 : 0, (int) round(($sentiment['count'] / $sentimentMax) * 100))
                                        : 0;
                                    $color = $sentimentColors[$sentiment['label']] ?? '#6366F1';
                                @endphp
                                <li class="space-y-1" wire:key="call-sentiment-{{ $loop->index }}">
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <span class="text-zinc-700 dark:text-zinc-300">{{ $sentiment['label'] }}</span>
                                        <span class="font-semibold tabular-nums text-zinc-900 dark:text-white">{{ PersianNumber::format($sentiment['count']) }}</span>
                                    </div>
                                    <div class="crm-overview-meter">
                                        <span style="width: {{ $width }}%; background: {{ $color }}"></span>
                                    </div>
                                </li>
                            @empty
                                <li class="text-sm text-zinc-500">احساسی ثبت نشده است.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-zinc-700 dark:text-zinc-200">علت‌های تماس پرتکرار</h3>
                        <ul class="space-y-2.5">
                            @forelse ($callReasons as $reason)
                                @php
                                    $width = $reasonMax > 0
                                        ? max($reason['count'] > 0 ? 8 : 0, (int) round(($reason['count'] / $reasonMax) * 100))
                                        : 0;
                                @endphp
                                <li class="space-y-1" wire:key="call-reason-{{ $loop->index }}">
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <span class="min-w-0 truncate text-zinc-700 dark:text-zinc-300">{{ $reason['label'] }}</span>
                                        <span class="shrink-0 font-semibold tabular-nums text-zinc-900 dark:text-white">{{ PersianNumber::format($reason['count']) }}</span>
                                    </div>
                                    <div class="crm-overview-meter crm-overview-meter--brand">
                                        <span style="width: {{ $width }}%"></span>
                                    </div>
                                </li>
                            @empty
                                <li class="text-sm text-zinc-500">علتی ثبت نشده است.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @endif
        </section>

        <section class="saas-card space-y-4 p-5">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">اقلام پرفروش فاکتور</h2>
                <p class="mt-1 text-sm text-zinc-500">بر اساس مبلغ خطوط فاکتور صادرشده در ۷ روز</p>
            </div>

            @if (empty($stats['top_invoice_items']))
                <p class="py-6 text-sm text-zinc-500">هنوز فاکتوری صادر نشده است.</p>
            @else
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($stats['top_invoice_items'] as $item)
                        @php
                            $tone = $itemTones[$loop->index % count($itemTones)];
                        @endphp
                        <li class="flex items-center gap-3 py-3 text-sm" wire:key="invoice-item-{{ $loop->index }}">
                            <span class="crm-overview-item-icon {{ $tone['bg'] }}" aria-hidden="true">
                                <x-saas.icon name="cube" />
                            </span>
                            <span class="min-w-0 flex-1 truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $item['label'] }}</span>
                            <span class="shrink-0 tabular-nums text-zinc-600 dark:text-zinc-300">{{ DealMoney::formatRials($item['amount_rials']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
