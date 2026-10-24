<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('filament.platform_analytics.churn_risk_heading')"
        :description="__('filament.platform_analytics.churn_risk_description')"
    >
        <div class="divide-y dark:divide-gray-700">
            @forelse ($rows as $row)
                <div class="flex flex-wrap items-start justify-between gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <a href="{{ $row['url'] }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">
                            {{ $row['title'] }}
                        </a>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ __('filament.platform_analytics.analyses_trend', ['recent' => $row['recent'], 'previous' => $row['previous']]) }}
                        </p>
                        @if ($row['reasons'] !== [])
                            <ul class="mt-2 flex flex-wrap gap-1">
                                @foreach ($row['reasons'] as $reason)
                                    <li><x-filament::badge color="gray" size="sm">{{ $reason }}</x-filament::badge></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <x-filament::badge :color="$row['color']">
                        {{ $row['band'] }} · {{ $row['score'] }}
                    </x-filament::badge>
                </div>
            @empty
                <p class="py-4 text-sm text-gray-500">{{ __('filament.platform_analytics.no_churn_risk') }}</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
