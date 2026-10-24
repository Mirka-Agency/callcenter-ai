@php
    use App\Support\JalaliDate;
    use App\Support\PersianNumber;

    $percent = fn (?float $ratio): string => $ratio === null ? '—' : PersianNumber::format($ratio * 100, null, 1).'٪';
    $number = fn (int|float|null $value): string => $value === null ? '—' : PersianNumber::format($value, null, 1);
    $maxOffset = max(array_map(fn (array $cohort): int => count($cohort['retention']), $cohorts ?: [['retention' => []]]));
@endphp

<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('filament.platform_analytics.activation_rate') }}</p>
            <p class="mt-2 text-3xl font-bold">{{ $percent($activation['rate']) }}</p>
            <p class="mt-1 text-xs text-gray-500">
                {{ __('filament.platform_analytics.activation_description', [
                    'activated' => $number($activation['activated']),
                    'eligible' => $number($activation['eligible']),
                    'days' => $number($activationDays),
                ]) }}
            </p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('filament.platform_analytics.time_to_first_value') }}</p>
            <p class="mt-2 text-3xl font-bold">
                {{ $activation['median_days_to_first_value'] === null ? '—' : __('filament.platform_analytics.days_value', ['value' => $number($activation['median_days_to_first_value'])]) }}
            </p>
            <p class="mt-1 text-xs text-gray-500">{{ __('filament.platform_analytics.time_to_first_value_description') }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('filament.platform_analytics.active_definition_title') }}</p>
            <p class="mt-2 text-sm">{{ __('filament.platform_analytics.active_definition') }}</p>
        </x-filament::section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section
            :heading="__('admin.engagement.adoption_heading')"
            :description="__('admin.engagement.adoption_description', ['active' => $number($adoption['active'])])"
        >
            <div class="space-y-3">
                @foreach ($adoption['features'] as $feature => $row)
                    <div>
                        <div class="flex justify-between text-sm">
                            <span>{{ __('admin.engagement.features.'.$feature) }}</span>
                            <span class="font-semibold">{{ $percent($row['rate']) }} <span class="text-xs text-gray-500">({{ $number($row['organizations']) }})</span></span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-2 rounded-full bg-primary-500" style="width: {{ round(($row['rate'] ?? 0) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section
            :heading="__('admin.engagement.onboarding_heading')"
            :description="__('admin.engagement.onboarding_description', ['count' => $number($onboarding['organizations']), 'completed' => $number($onboarding['completed'])])"
        >
            <div class="divide-y dark:divide-gray-700">
                @foreach ($onboarding['stuck_at'] as $step => $count)
                    <div class="flex items-center justify-between py-2 text-sm">
                        <span>{{ __('admin.onboarding.steps.'.$step) }}</span>
                        <x-filament::badge :color="$count > 0 ? 'warning' : 'gray'">{{ __('admin.engagement.stuck_count', ['count' => $number($count)]) }}</x-filament::badge>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.engagement.dau') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $number($activeUsers['dau']) }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.engagement.wau') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $number($activeUsers['wau']) }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.engagement.mau') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $number($activeUsers['mau']) }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.engagement.expansion') }}</p>
            <p class="mt-2 text-2xl font-bold">{{ $number($expansion['organizations']) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ __('admin.engagement.seats_added', ['value' => $number($expansion['seats_added'])]) }}</p>
        </x-filament::section>
    </div>

    <x-filament::section :heading="__('filament.platform_analytics.monthly_table_heading')">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-start text-gray-500 dark:border-gray-700">
                        <th class="px-3 py-2 text-start">{{ __('filament.platform_analytics.month') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('filament.platform_analytics.active_organizations') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('filament.platform_analytics.new_organizations') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('filament.platform_analytics.reactivated') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('filament.platform_analytics.churned') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('filament.platform_analytics.churn_rate') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @foreach ($series as $month)
                        <tr>
                            <td class="px-3 py-2 font-medium">
                                {{ JalaliDate::persianDigits($month['label']) }}
                                @if ($month['is_current'])
                                    <x-filament::badge color="gray" size="sm" class="ms-1 inline-flex">{{ __('filament.platform_analytics.in_progress') }}</x-filament::badge>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $number($month['active']) }}</td>
                            <td class="px-3 py-2">{{ $number($month['new']) }}</td>
                            <td class="px-3 py-2 text-success-600">{{ $number($month['reactivated']) }}</td>
                            <td class="px-3 py-2 text-danger-600">{{ $number($month['churned']) }}</td>
                            <td class="px-3 py-2 font-semibold">{{ $percent($month['churn_rate']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section
        :heading="__('filament.platform_analytics.cohorts_heading')"
        :description="__('filament.platform_analytics.cohorts_description')"
    >
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-gray-500 dark:border-gray-700">
                        <th class="px-3 py-2 text-start">{{ __('filament.platform_analytics.signup_month') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('filament.platform_analytics.cohort_size') }}</th>
                        @for ($offset = 0; $offset < $maxOffset; $offset++)
                            <th class="px-3 py-2 text-center">{{ __('filament.platform_analytics.month_offset', ['n' => JalaliDate::persianDigits($offset)]) }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @foreach ($cohorts as $cohort)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ JalaliDate::persianDigits($cohort['label']) }}</td>
                            <td class="px-3 py-2">{{ $number($cohort['size']) }}</td>
                            @for ($offset = 0; $offset < $maxOffset; $offset++)
                                @php($value = $cohort['retention'][$offset] ?? null)
                                <td
                                    class="px-3 py-2 text-center"
                                    @if ($value !== null) style="background-color: rgb(245 158 11 / {{ round(0.1 + $value * 0.7, 2) }})" @endif
                                >
                                    {{ array_key_exists($offset, $cohort['retention']) ? $percent($value) : '' }}
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
