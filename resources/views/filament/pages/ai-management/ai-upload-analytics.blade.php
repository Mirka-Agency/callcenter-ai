<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('filament.fields.total_manual_uploads') }}</p>
            <p class="mt-2 text-3xl font-bold">{{ number_format($overview['total_uploads']) }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('filament.fields.total_analyzed') }}</p>
            <p class="mt-2 text-3xl font-bold">{{ number_format($overview['total_analyzed']) }}</p>
        </x-filament::section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-filament::section :heading="__('filament.upload_analytics.uploads_per_organization')">
            <div class="divide-y dark:divide-gray-700">
                @forelse ($perOrganization as $row)
                    <div class="flex items-center justify-between py-3">
                        <span>{{ $row->organization?->title ?? __('filament.misc.unknown') }}</span>
                        <span class="font-semibold">{{ number_format($row->upload_count) }}</span>
                    </div>
                @empty
                    <p class="py-4 text-sm text-gray-500">{{ __('filament.misc.no_manual_uploads') }}</p>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('filament.upload_analytics.uploads_per_employee')">
            <div class="divide-y dark:divide-gray-700">
                @forelse ($perEmployee as $row)
                    <div class="flex items-center justify-between py-3">
                        <div>
                            <p>{{ $row->employee?->full_name ?? __('filament.misc.unknown') }}</p>
                            <p class="text-xs text-gray-500">{{ $row->organization?->title }}</p>
                        </div>
                        <span class="font-semibold">{{ number_format($row->upload_count) }}</span>
                    </div>
                @empty
                    <p class="py-4 text-sm text-gray-500">{{ __('filament.misc.no_employee_uploads') }}</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
