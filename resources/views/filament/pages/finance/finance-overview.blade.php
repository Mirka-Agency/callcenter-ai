@php
    use App\Filament\Resources\Organizations\OrganizationResource;
    use App\Support\Money;
    use App\Support\PersianNumber;

    $percent = fn (?float $ratio): string => $ratio === null ? '—' : PersianNumber::format($ratio * 100, null, 1).'٪';
    $number = fn (int|float|null $value): string => $value === null ? '—' : PersianNumber::format($value, null, 1);
    $money = fn (?int $rials): string => $rials === null ? '—' : Money::format($rials);
    $budgetRate = $margins['budget'] ? $margins['total_cost'] / $margins['budget'] : null;
    $cards = [
        ['label' => __('admin.billing.mrr'), 'value' => $money($overview['mrr']), 'hint' => __('admin.finance.paying_customers', ['count' => $number($overview['paying_customers'])])],
        ['label' => __('admin.billing.arr'), 'value' => $money($overview['arr']), 'hint' => __('admin.finance.arpa', ['value' => $money($overview['arpa'])])],
        ['label' => __('admin.finance.cash_this_month'), 'value' => $money($overview['cash_this_month']), 'hint' => __('admin.finance.cash_last_month', ['value' => $money($overview['cash_last_month'])])],
        ['label' => __('admin.finance.outstanding'), 'value' => $money($overview['outstanding']), 'hint' => __('admin.finance.overdue_hint', ['value' => $money($overview['overdue']), 'count' => $number($overview['overdue_customers'])]), 'color' => $overview['overdue'] > 0 ? 'text-danger-600' : null],
        ['label' => __('admin.finance.revenue_churn'), 'value' => $percent($overview['revenue_churn_rate']), 'hint' => __('admin.finance.last_month')],
        ['label' => __('admin.finance.nrr'), 'value' => $percent($overview['net_revenue_retention']), 'hint' => __('admin.finance.last_month')],
        ['label' => __('admin.finance.ltv'), 'value' => $money($overview['ltv']), 'hint' => __('admin.finance.ltv_hint')],
        ['label' => __('admin.finance.upcoming'), 'value' => $number($overview['renewals_due']), 'hint' => __('admin.finance.upcoming_hint', ['trials' => $number($overview['trials_ending'])])],
    ];
@endphp

<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($cards as $card)
            <x-filament::section>
                <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
                <p @class(['mt-2 text-2xl font-bold', $card['color'] ?? null])>{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $card['hint'] }}</p>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section :heading="__('admin.finance.movement_heading')" :description="__('admin.finance.movement_description')">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-start text-gray-500 dark:border-gray-700">
                        <th class="py-2 text-start">{{ __('admin.finance.month') }}</th>
                        <th class="py-2 text-start">{{ __('admin.finance.start_mrr') }}</th>
                        <th class="py-2 text-start text-success-600">{{ __('admin.finance.new') }}</th>
                        <th class="py-2 text-start text-success-600">{{ __('admin.finance.expansion') }}</th>
                        <th class="py-2 text-start text-info-600">{{ __('admin.finance.reactivated') }}</th>
                        <th class="py-2 text-start text-warning-600">{{ __('admin.finance.contraction') }}</th>
                        <th class="py-2 text-start text-danger-600">{{ __('admin.finance.churned') }}</th>
                        <th class="py-2 text-start">{{ __('admin.finance.end_mrr') }}</th>
                        <th class="py-2 text-start">{{ __('admin.finance.nrr') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movement as $row)
                        <tr class="border-b last:border-0 dark:border-gray-800">
                            <td class="py-2 font-medium">{{ $row['label'] }}</td>
                            <td class="py-2">{{ $money($row['start_mrr']) }}</td>
                            <td class="py-2">{{ $money($row['new']) }}</td>
                            <td class="py-2">{{ $money($row['expansion']) }}</td>
                            <td class="py-2">{{ $money($row['reactivated']) }}</td>
                            <td class="py-2">{{ $money($row['contraction']) }}</td>
                            <td class="py-2">{{ $money($row['churned']) }} <span class="text-xs text-gray-500">({{ $number($row['churned_customers']) }})</span></td>
                            <td class="py-2 font-semibold">{{ $money($row['end_mrr']) }}</td>
                            <td class="py-2">{{ $percent($row['net_revenue_retention']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section :heading="__('admin.finance.aging_heading')">
            <div class="divide-y dark:divide-gray-700">
                @foreach ($aging as $bucket => $row)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <span>{{ __('admin.finance.aging.'.$bucket) }}</span>
                        <span>
                            <span @class(['font-semibold', 'text-danger-600' => $bucket !== 'not_due' && $row['amount'] > 0])>{{ $money($row['amount']) }}</span>
                            <span class="text-xs text-gray-500">({{ __('admin.finance.invoices_count', ['count' => $number($row['count'])]) }})</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('admin.finance.ai_cost_heading')" :description="__('admin.finance.ai_cost_description')">
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="text-gray-500">{{ __('admin.finance.ai_cost') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ $money($margins['total_cost']) }}</p>
                </div>
                <div>
                    <p class="text-gray-500">{{ __('admin.finance.revenue') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ $money($margins['total_revenue']) }}</p>
                </div>
                <div>
                    <p class="text-gray-500">{{ __('admin.finance.budget') }}</p>
                    <p @class(['mt-1 text-xl font-bold', 'text-danger-600' => $budgetRate >= 1, 'text-warning-600' => $budgetRate >= 0.8 && $budgetRate < 1])>
                        {{ $margins['budget'] ? $percent($budgetRate) : __('admin.finance.no_budget') }}
                    </p>
                </div>
            </div>
            @if ($margins['budget'])
                <div class="mt-3 h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                    <div @class(['h-2 rounded-full', 'bg-danger-500' => $budgetRate >= 1, 'bg-warning-500' => $budgetRate >= 0.8 && $budgetRate < 1, 'bg-primary-500' => $budgetRate < 0.8]) style="width: {{ min(100, round($budgetRate * 100)) }}%"></div>
                </div>
            @endif
            <p class="mt-4 text-sm font-medium">{{ __('admin.finance.lowest_margins') }}</p>
            <div class="mt-2 divide-y text-sm dark:divide-gray-700">
                @forelse (array_slice($margins['rows'], 0, 8) as $row)
                    <div class="flex items-center justify-between py-2">
                        <a href="{{ OrganizationResource::getUrl('overview', ['record' => $row['organization_id']]) }}" class="text-primary-600 hover:underline">{{ $row['title'] }}</a>
                        <span>
                            <span @class(['font-semibold', 'text-danger-600' => $row['margin'] < 0])>{{ $money($row['margin']) }}</span>
                            <span class="text-xs text-gray-500">{{ __('admin.finance.margin_detail', ['revenue' => $money($row['revenue']), 'cost' => $money($row['cost'])]) }}</span>
                        </span>
                    </div>
                @empty
                    <p class="py-2 text-gray-500">{{ __('admin.finance.no_cost_data') }}</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section :heading="__('admin.finance.quota_heading')" :description="__('admin.finance.quota_description')">
            <div class="space-y-3">
                @forelse ($quota as $row)
                    <div>
                        <div class="flex justify-between text-sm">
                            <a href="{{ OrganizationResource::getUrl('overview', ['record' => $row['organization_id']]) }}" class="text-primary-600 hover:underline">{{ $row['title'] }} <span class="text-xs text-gray-500">({{ $row['plan'] }})</span></a>
                            <span class="font-semibold">{{ $number($row['usage']) }} / {{ $number($row['quota']) }}</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                            <div @class(['h-2 rounded-full', 'bg-danger-500' => $row['rate'] >= 1, 'bg-warning-500' => $row['rate'] < 1]) style="width: {{ min(100, round($row['rate'] * 100)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">{{ __('admin.finance.no_quota_pressure') }}</p>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('admin.finance.cac_heading')" :description="__('admin.finance.cac_description')">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-gray-500 dark:border-gray-700">
                        <th class="py-2 text-start">{{ __('admin.finance.month') }}</th>
                        <th class="py-2 text-start">{{ __('admin.finance.spend') }}</th>
                        <th class="py-2 text-start">{{ __('admin.finance.new_customers') }}</th>
                        <th class="py-2 text-start">{{ __('admin.finance.cac') }}</th>
                        <th class="py-2 text-start">{{ __('admin.finance.payback') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($acquisition as $row)
                        <tr class="border-b last:border-0 dark:border-gray-800">
                            <td class="py-2 font-medium">{{ $row['label'] }}</td>
                            <td class="py-2">{{ $money($row['spend']) }}</td>
                            <td class="py-2">{{ $number($row['new_customers']) }}</td>
                            <td class="py-2">{{ $money($row['cac']) }}</td>
                            <td class="py-2">{{ $row['payback_months'] === null ? '—' : __('admin.finance.months_value', ['value' => $number($row['payback_months'])]) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    </div>
</x-filament-panels::page>
