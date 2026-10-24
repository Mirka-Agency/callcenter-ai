@php
    use App\Support\JalaliDate;
    use App\Support\PersianNumber;

    $percent = fn (?float $ratio): string => $ratio === null ? '—' : PersianNumber::format($ratio * 100, null, 1).'٪';
    $number = fn (int|float|null $value, int $decimals = 0): string => $value === null ? '—' : PersianNumber::format($value, null, $decimals);
@endphp

<x-filament-panels::page>
    <div class="flex flex-wrap gap-2">
        @foreach (array_keys(\App\Filament\Pages\Support\OperationsHealth::windows()) as $key)
            <x-filament::button
                size="sm"
                :color="$window === $key ? 'primary' : 'gray'"
                wire:click="setWindow('{{ $key }}')"
            >
                {{ __('admin.operations.windows.'.$key) }}
            </x-filament::button>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.operations.stuck_calls') }}</p>
            <p class="mt-2 text-3xl font-bold {{ $stuckCalls > 0 ? 'text-danger-600' : '' }}">{{ $number($stuckCalls) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ __('admin.operations.stuck_calls_description') }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.operations.llm_failure_rate') }}</p>
            <p class="mt-2 text-3xl font-bold">{{ $percent($llmRequests['failure_rate']) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ __('admin.operations.llm_requests', ['total' => $number($llmRequests['total']), 'failed' => $number($llmRequests['failed'])]) }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('admin.operations.failing_providers') }}</p>
            @php($failing = collect($providers)->filter(fn ($row) => $row['total'] >= 10 && ($row['failure_rate'] ?? 0) >= $failingRate)->count())
            <p class="mt-2 text-3xl font-bold {{ $failing > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $number($failing) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ __('admin.operations.failing_providers_description', ['rate' => $percent($failingRate)]) }}</p>
        </x-filament::section>
    </div>

    <x-filament::section :heading="__('admin.operations.providers_heading')" :description="__('admin.operations.providers_description')">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-gray-500 dark:border-gray-700">
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.provider') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.channel') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.events') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.failed') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.failure_rate') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.affected_organizations') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.last_failure') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @forelse ($providers as $row)
                        @php($isFailing = $row['total'] >= 10 && ($row['failure_rate'] ?? 0) >= $failingRate)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $row['provider'] }}</td>
                            <td class="px-3 py-2">{{ __('admin.operations.channels.'.$row['kind'].'_'.$row['channel']) }}</td>
                            <td class="px-3 py-2">{{ $number($row['total']) }}</td>
                            <td class="px-3 py-2">{{ $number($row['failed']) }}</td>
                            <td class="px-3 py-2">
                                <x-filament::badge :color="$isFailing ? 'danger' : (($row['failure_rate'] ?? 0) > 0.05 ? 'warning' : 'success')">
                                    {{ $percent($row['failure_rate']) }}
                                </x-filament::badge>
                            </td>
                            <td class="px-3 py-2">{{ $number($row['failing_organizations']) }} / {{ $number($row['organizations']) }}</td>
                            <td class="px-3 py-2">{{ JalaliDate::datetime($row['last_failure_at']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-4 text-gray-500">{{ __('admin.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('admin.operations.ai_quality_heading')" :description="__('admin.operations.ai_quality_description')">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-gray-500 dark:border-gray-700">
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.model') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.prompt_version') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.analyses') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.avg_duration') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.p95_duration') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.avg_tokens') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.avg_score') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('admin.operations.not_evaluable') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @forelse ($aiQuality as $row)
                        <tr>
                            <td class="px-3 py-2 font-medium" dir="ltr">{{ $row['model'] }}</td>
                            <td class="px-3 py-2" dir="ltr">{{ $row['prompt_version'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $number($row['analyses']) }}</td>
                            <td class="px-3 py-2">{{ $row['avg_duration_ms'] === null ? '—' : __('admin.common.seconds', ['value' => $number($row['avg_duration_ms'] / 1000, 1)]) }}</td>
                            <td class="px-3 py-2">{{ $row['p95_duration_ms'] === null ? '—' : __('admin.common.seconds', ['value' => $number($row['p95_duration_ms'] / 1000, 1)]) }}</td>
                            <td class="px-3 py-2">{{ $number($row['avg_tokens']) }}</td>
                            <td class="px-3 py-2">{{ $number($row['avg_score'], 1) }}</td>
                            <td class="px-3 py-2">{{ $percent($row['not_evaluable_rate']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-4 text-gray-500">{{ __('admin.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('admin.operations.top_errors_heading')">
        <div class="divide-y dark:divide-gray-700">
            @forelse ($topErrors as $error)
                <div class="flex items-start justify-between gap-4 py-3">
                    <p class="min-w-0 flex-1 break-words text-sm">{{ \Illuminate\Support\Str::limit($error->processing_error, 240) }}</p>
                    <div class="shrink-0 text-end text-sm">
                        <p class="font-semibold">{{ __('admin.operations.failures_count', ['count' => $number($error->failures)]) }}</p>
                        <p class="text-xs text-gray-500">{{ __('admin.operations.organizations_count', ['count' => $number($error->organizations)]) }}</p>
                    </div>
                </div>
            @empty
                <p class="py-4 text-sm text-gray-500">{{ __('admin.operations.no_errors') }}</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
