<div
    class="saas-card lg:sticky lg:top-0 lg:z-10 shadow-sm"
    wire:key="activity-period-{{ $period }}"
    data-tour="activity-filters"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">بازه زمانی</h2>
            <p class="mt-0.5 text-xs text-zinc-500">{{ $activePreset->label() }}</p>
        </div>
        <a href="{{ route('employee.performance', ['preset' => $period]) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
            عملکرد من
        </a>
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
        @foreach ($periodPresets as $preset)
            <button
                type="button"
                wire:click="setPeriod('{{ $preset->value }}')"
                @class([
                    'rounded-md px-3 py-1.5 text-xs font-medium transition',
                    'bg-zinc-900 text-white shadow-sm dark:bg-white dark:text-zinc-900' => $period === $preset->value,
                    'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200' => $period !== $preset->value,
                ])
            >{{ $preset->label() }}</button>
        @endforeach
    </div>
</div>
