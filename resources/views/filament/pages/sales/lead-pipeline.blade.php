@php
    use App\Support\JalaliDate;
    use App\Support\PersianNumber;

    $percent = fn (?float $ratio): string => $ratio === null ? '—' : PersianNumber::format($ratio * 100, null, 1).'٪';
    $number = fn (int|float|null $value, int $decimals = 0): string => $value === null ? '—' : PersianNumber::format($value, null, $decimals);
@endphp

<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.leads.breaching_now', ['hours' => $number($slaHours)]) }}</p>
            <p class="mt-2 text-3xl font-bold {{ $response['breaching_now'] > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $number($response['breaching_now']) }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.leads.median_response') }}</p>
            <p class="mt-2 text-3xl font-bold">{{ $response['median_hours'] === null ? '—' : __('admin.leads.hours', ['value' => $number($response['median_hours'], 1)]) }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.leads.within_sla') }}</p>
            <p class="mt-2 text-3xl font-bold">{{ $percent($response['contacted'] === 0 ? null : $response['within_sla'] / $response['contacted']) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ __('admin.leads.within_sla_description', ['within' => $number($response['within_sla']), 'total' => $number($response['contacted'])]) }}</p>
        </x-filament::section>
    </div>

    <x-filament::section :heading="__('admin.leads.board_heading')">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-gray-500 dark:border-gray-700">
                        <th class="px-3 py-2 text-start">{{ __('admin.leads.type') }}</th>
                        @foreach ($statuses as $status)
                            <th class="px-3 py-2 text-center"><x-filament::badge :color="$status->color()">{{ $status->label() }}</x-filament::badge></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @foreach ($types as $type => $definition)
                        <tr>
                            <td class="px-3 py-2 font-medium">
                                <a href="{{ $definition['resource']::getUrl('index') }}" class="text-primary-600 hover:underline">{{ __('admin.leads.types.'.$type) }}</a>
                            </td>
                            @foreach ($statuses as $status)
                                <td class="px-3 py-2 text-center">{{ $number($board[$type][$status->value] ?? 0) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section :heading="__('admin.leads.follow_ups_heading')">
            <div class="divide-y text-sm dark:divide-gray-700">
                @forelse ($followUps as $lead)
                    <a href="{{ $lead['url'] }}" class="flex items-center justify-between gap-3 py-2 hover:bg-gray-50 dark:hover:bg-white/5">
                        <div>
                            <p class="font-medium">{{ $lead['name'] }}</p>
                            <p class="text-xs text-gray-500">{{ __('admin.leads.types.'.$lead['type']) }} · <span dir="ltr">{{ $lead['phone'] }}</span> · {{ $lead['assignee'] ?? __('admin.leads.unassigned') }}</p>
                        </div>
                        <div class="text-end">
                            <x-filament::badge :color="$lead['status']->color()">{{ $lead['status']->label() }}</x-filament::badge>
                            <p class="mt-1 text-xs text-danger-600">{{ JalaliDate::datetime($lead['follow_up_at']) }}</p>
                        </div>
                    </a>
                @empty
                    <p class="py-3 text-gray-500">{{ __('admin.leads.no_follow_ups') }}</p>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('admin.leads.sources_heading')" :description="__('admin.leads.sources_description')">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-gray-500 dark:border-gray-700">
                        <th class="px-3 py-2 text-start">{{ __('admin.leads.source') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.leads.leads') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.leads.converted_count') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.leads.conversion_rate') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @forelse ($sources as $row)
                        <tr>
                            <td class="px-3 py-2" dir="ltr">{{ $row['source'] }}</td>
                            <td class="px-3 py-2">{{ $number($row['leads']) }}</td>
                            <td class="px-3 py-2">{{ $number($row['converted']) }}</td>
                            <td class="px-3 py-2 font-semibold">{{ $percent($row['rate']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-3 text-gray-500">{{ __('admin.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    </div>
</x-filament-panels::page>
