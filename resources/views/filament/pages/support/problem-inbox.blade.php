<x-filament-panels::page>
    <x-filament::section :description="__('admin.inbox.description')">
        <div class="divide-y dark:divide-gray-700">
            @forelse ($items as $item)
                <div class="flex items-start justify-between gap-4 py-3">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <x-filament::icon
                            :icon="$item['severity'] === 'danger' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-exclamation-triangle'"
                            @class(['mt-0.5 h-5 w-5 shrink-0', 'text-danger-500' => $item['severity'] === 'danger', 'text-warning-500' => $item['severity'] !== 'danger'])
                        />
                        <div class="min-w-0">
                            <p class="font-medium">{{ $item['title'] }}</p>
                            @if ($item['detail'])
                                <p class="mt-0.5 text-sm text-gray-500">{{ $item['detail'] }}</p>
                            @endif
                            <x-filament::badge color="gray" size="sm" class="mt-1 inline-flex">{{ $item['area']->label() }}</x-filament::badge>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <span class="text-lg font-bold">{{ \App\Support\PersianNumber::format($item['count'], 0) }}</span>
                        @if ($item['url'])
                            <x-filament::button tag="a" :href="$item['url']" size="sm" color="gray">{{ __('admin.common.open') }}</x-filament::button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="flex items-center gap-2 py-6 text-success-600">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6" />
                    <span>{{ __('admin.inbox.empty') }}</span>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
