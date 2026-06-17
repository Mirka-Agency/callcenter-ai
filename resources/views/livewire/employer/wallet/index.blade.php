@php
    use App\Domain\Billing\Enums\WalletTransactionType;

    $hasDailyTrend = collect($dailyTrend)->isNotEmpty();
    $hasMonthlyTrend = collect($monthlyTrend)->isNotEmpty();
    $hasTokenSplit = ($monthOverview['input_tokens'] ?? 0) > 0 || ($monthOverview['output_tokens'] ?? 0) > 0;

    $dailyLabels = collect($dailyTrend)->map(fn (array $point) => shamsi($point['period'], 'month_day'))->all();

    $dailyUsageChart = [
        'labels' => $dailyLabels,
        'datasets' => [
            [
                'type' => 'bar',
                'label' => 'تعداد تحلیل',
                'data' => collect($dailyTrend)->pluck('analyses_count')->all(),
                'backgroundColor' => 'rgba(14, 165, 233, 0.85)',
                'borderRadius' => 6,
                'yAxisID' => 'y',
                'order' => 2,
            ],
            [
                'type' => 'line',
                'label' => 'هزینه',
                'data' => collect($dailyTrend)->pluck('total_cost')->all(),
                'borderColor' => 'rgb(245, 158, 11)',
                'backgroundColor' => 'rgba(245, 158, 11, 0.12)',
                'fill' => true,
                'tension' => 0.35,
                'yAxisID' => 'y1',
                'order' => 1,
            ],
        ],
        'options' => [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => [
                'y' => ['type' => 'linear', 'position' => 'left', 'beginAtZero' => true, 'title' => ['display' => true, 'text' => 'تحلیل']],
                'y1' => ['type' => 'linear', 'position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false], 'title' => ['display' => true, 'text' => 'هزینه']],
            ],
        ],
    ];

    $tokenChart = [
        'labels' => ['توکن ورودی', 'توکن خروجی'],
        'datasets' => [[
            'data' => [$monthOverview['input_tokens'] ?? 0, $monthOverview['output_tokens'] ?? 0],
            'backgroundColor' => ['rgb(99, 102, 241)', 'rgb(16, 185, 129)'],
        ]],
    ];

    $monthlyLabels = collect($monthlyTrend)->map(fn (array $point) => shamsi($point['period'].'-15', 'month_day'))->all();

    $monthlyCostChart = [
        'labels' => $monthlyLabels,
        'datasets' => [[
            'label' => 'هزینه ماهانه',
            'data' => collect($monthlyTrend)->pluck('total_cost')->all(),
            'backgroundColor' => 'rgba(139, 92, 246, 0.85)',
            'borderRadius' => 8,
        ]],
        'options' => ['plugins' => ['legend' => ['display' => false]]],
    ];

    $tokenTrendChart = [
        'labels' => $dailyLabels,
        'datasets' => [
            [
                'label' => 'ورودی',
                'data' => collect($dailyTrend)->pluck('input_tokens')->all(),
                'borderColor' => 'rgb(99, 102, 241)',
                'backgroundColor' => 'rgba(99, 102, 241, 0.15)',
                'fill' => true,
                'tension' => 0.35,
            ],
            [
                'label' => 'خروجی',
                'data' => collect($dailyTrend)->pluck('output_tokens')->all(),
                'borderColor' => 'rgb(16, 185, 129)',
                'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                'fill' => true,
                'tension' => 0.35,
            ],
        ],
        'options' => ['plugins' => ['legend' => ['position' => 'bottom']]],
    ];

    $transactionBadgeClass = fn (WalletTransactionType $type) => match ($type) {
        WalletTransactionType::Deposit, WalletTransactionType::Refund => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
        WalletTransactionType::AiUsage, WalletTransactionType::Withdrawal => 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
        default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    };
@endphp

<div class="saas-page space-y-6">
    <x-saas.page-header
        data-tour="page-header"
        title="اعتبار تحلیل"
        description="مدیریت موجودی، پایش مصرف و بررسی روند هزینه تحلیل تماس‌ها."
    />

    @if ($criticalBalance)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100">
            <strong>اعتبار تحلیل رو به اتمام است.</strong> قبل از بارگذاری یا تحلیل تماس جدید، اعتبار خود را شارژ کنید.
        </div>
    @elseif ($lowBalance)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
            <strong>موجودی کم است.</strong> برای جلوگیری از توقف تحلیل تماس‌ها، اعتبار تحلیل را شارژ کنید.
        </div>
    @endif

    <section class="saas-hero saas-hero--accent" data-tour="wallet-balance">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">موجودی فعلی</p>
                <p class="mt-2 text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $formatMoney($overview['balance']) }}</p>
                <p class="mt-2 text-sm text-zinc-500">
                    @if ($estimatedDaysRemaining !== null && $estimatedDaysRemaining > 0)
                        با میانگین مصرف ۳۰ روز اخیر، حدود {{ number_format($estimatedDaysRemaining) }} روز اعتبار باقی می‌ماند.
                    @elseif ($avgDailyCost <= 0)
                        هنوز الگوی مصرفی شکل نگرفته — پس از اولین تحلیل، پیش‌بینی باقی‌مانده دقیق‌تر می‌شود.
                    @else
                        موجودی فعلی برای ادامه تحلیل‌ها کافی نیست.
                    @endif
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="saas-inline-stat">
                    <p class="text-xs text-zinc-500">مصرف امروز (تقریبی)</p>
                    <p class="mt-1 text-lg font-semibold">{{ $formatMoney(collect($dailyTrend)->last()['total_cost'] ?? 0) }}</p>
                </div>
                <div class="saas-inline-stat">
                    <p class="text-xs text-zinc-500">میانگین روزانه</p>
                    <p class="mt-1 text-lg font-semibold">{{ $formatMoney($avgDailyCost) }}</p>
                </div>
                <div class="saas-inline-stat">
                    <p class="text-xs text-zinc-500">آستانه هشدار</p>
                    <p class="mt-1 text-lg font-semibold">{{ $formatMoney($lowBalanceThreshold) }}</p>
                </div>
            </div>
        </div>
    </section>

    <div data-tour="wallet-stats" class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-saas.stat-card label="تحلیل‌های این ماه" :value="number_format($overview['month_analyses'])" />
        <x-saas.stat-card label="هزینه این ماه" :value="$formatMoney($overview['month_cost'])" />
        <x-saas.stat-card label="توکن مصرف‌شده" :value="number_format($overview['month_tokens'])" hint="این ماه" />
        <x-saas.stat-card
            label="میانگین هزینه هر تحلیل"
            :value="$formatMoney($monthOverview['average_cost_per_analysis'] ?? 0)"
        />
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if ($showAiInfrastructure)
            <x-saas.stat-card label="مدل فعال" :value="$overview['model']['model_name'] ?? '—'" />
            <x-saas.stat-card label="ارائه‌دهنده" :value="$overview['model']['provider_name'] ?? '—'" />
        @else
            <x-saas.stat-card label="وضعیت سرویس" value="فعال" />
            <x-saas.stat-card
                label="آخرین تحلیل"
                :value="$overview['last_analysis_at'] ? shamsi($overview['last_analysis_at'], 'datetime_short') : '—'"
            />
        @endif
        <x-saas.stat-card label="میانگین امتیاز تحلیل" :value="($monthOverview['average_score'] ?? 0) ?: '—'" />
        <x-saas.stat-card label="ارز کیف پول" :value="$overview['currency']" />
    </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2" data-tour="wallet-charts">
        <div class="saas-card">
            <h2 class="text-lg font-semibold">روند مصرف روزانه</h2>
            <p class="mt-1 text-sm text-zinc-500">تعداد تحلیل و هزینه در ۳۰ روز گذشته</p>
            @if ($hasDailyTrend)
                <div class="mt-4 h-64" wire:key="wallet-daily-usage-{{ md5(json_encode($dailyTrend)) }}">
                    <canvas id="wallet-daily-usage" data-report-chart data-type="bar" data-config='@json($dailyUsageChart)'></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.no_wallet_usage.title') }}" description="{{ __('ui.empty.no_wallet_usage.description') }}" />
                </div>
            @endif
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">روند توکن‌ها</h2>
            <p class="mt-1 text-sm text-zinc-500">توکن ورودی و خروجی در ۳۰ روز گذشته</p>
            @if ($hasDailyTrend)
                <div class="mt-4 h-64" wire:key="wallet-token-trend-{{ md5(json_encode($dailyTrend)) }}">
                    <canvas id="wallet-token-trend" data-report-chart data-type="line" data-config='@json($tokenTrendChart)'></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.no_wallet_usage.title') }}" description="{{ __('ui.empty.no_wallet_usage.description') }}" />
                </div>
            @endif
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">ترکیب توکن این ماه</h2>
            <p class="mt-1 text-sm text-zinc-500">سهم توکن ورودی و خروجی در مصرف ماه جاری</p>
            @if ($hasTokenSplit)
                <div class="mt-4 mx-auto h-64 max-w-xs" wire:key="wallet-token-split-{{ md5(json_encode($monthOverview)) }}">
                    <canvas id="wallet-token-split" data-report-chart data-type="doughnut" data-config='@json($tokenChart)'></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.no_wallet_usage.title') }}" description="{{ __('ui.empty.no_wallet_usage.description') }}" />
                </div>
            @endif
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">هزینه ماهانه</h2>
            <p class="mt-1 text-sm text-zinc-500">مجموع هزینه تحلیل‌ها در ۶ ماه اخیر</p>
            @if ($hasMonthlyTrend)
                <div class="mt-4 h-64" wire:key="wallet-monthly-cost-{{ md5(json_encode($monthlyTrend)) }}">
                    <canvas id="wallet-monthly-cost" data-report-chart data-type="bar" data-config='@json($monthlyCostChart)'></canvas>
                </div>
            @else
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.no_wallet_usage.title') }}" description="{{ __('ui.empty.no_wallet_usage.description') }}" />
                </div>
            @endif
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="saas-card xl:col-span-2" data-tour="wallet-transactions">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">تراکنش‌های اخیر</h2>
                    <p class="mt-1 text-sm text-zinc-500">واریز، مصرف تحلیل و تعدیل‌های اعتبار</p>
                </div>
            </div>

            @if ($recentTransactions->isEmpty())
                <div class="mt-4">
                    <x-saas.empty-state title="{{ __('ui.empty.no_transactions.title') }}" description="{{ __('ui.empty.no_transactions.description') }}" />
                </div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="saas-table min-w-[40rem]">
                        <thead>
                            <tr>
                                <th>تاریخ</th>
                                <th>نوع</th>
                                <th>مبلغ</th>
                                <th>موجودی پس از تراکنش</th>
                                <th>توضیح</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentTransactions as $transaction)
                                <tr wire:key="wallet-tx-{{ $transaction->id }}">
                                    <td class="whitespace-nowrap text-sm">{{ shamsi($transaction->created_at, 'datetime_short') }}</td>
                                    <td>
                                        <span @class(['saas-badge', $transactionBadgeClass($transaction->type)])>
                                            {{ $transaction->type->label() }}
                                        </span>
                                    </td>
                                    <td @class([
                                        'whitespace-nowrap font-semibold tabular-nums',
                                        'text-emerald-600 dark:text-emerald-400' => $transaction->amount > 0,
                                        'text-amber-700 dark:text-amber-300' => $transaction->amount < 0,
                                    ])>
                                        {{ $transaction->amount > 0 ? '+' : '' }}{{ $formatMoney($transaction->amount) }}
                                    </td>
                                    <td class="whitespace-nowrap tabular-nums text-sm">{{ $formatMoney($transaction->balance_after) }}</td>
                                    <td class="max-w-xs truncate text-sm text-zinc-500">{{ $transaction->description ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">نرخ مصرف</h2>
            @if ($showAiInfrastructure)
                <p class="mt-1 text-sm text-zinc-500">
                    مدل: <strong>{{ $overview['model']['model_name'] ?? '—' }}</strong>
                </p>
            @else
                <p class="mt-1 text-sm text-zinc-500">نرخ مصرف بر اساس تحلیل‌های انجام‌شده محاسبه می‌شود.</p>
            @endif

            <div class="mt-4 space-y-3">
                <div class="rounded-lg border border-zinc-200/80 bg-zinc-50/80 p-4 dark:border-zinc-700 dark:bg-zinc-900/80">
                    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">توکن ورودی</p>
                    <p class="mt-1 text-xl font-semibold">{{ $formatMoney($overview['model']['input_price_per_million'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-zinc-400">به ازای هر ۱ میلیون توکن</p>
                </div>
                <div class="rounded-lg border border-zinc-200/80 bg-zinc-50/80 p-4 dark:border-zinc-700 dark:bg-zinc-900/80">
                    <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">توکن خروجی</p>
                    <p class="mt-1 text-xl font-semibold">{{ $formatMoney($overview['model']['output_price_per_million'] ?? 0) }}</p>
                    <p class="mt-1 text-xs text-zinc-400">به ازای هر ۱ میلیون توکن</p>
                </div>
            </div>
        </div>
    </div>
</div>
