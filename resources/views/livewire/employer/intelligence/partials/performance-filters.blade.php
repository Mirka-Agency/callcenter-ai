@php
    use App\Enums\ReportDatePreset;
@endphp

<div
    data-tour="performance-filters"
    wire:key="performance-date-filters-{{ $datePreset }}-{{ $customFrom }}-{{ $customTo }}"
    x-data="{ showCustom: @js($showCustomDateRange || $datePreset === 'custom') }"
>
    <div class="flex flex-wrap gap-2">
        @foreach (ReportDatePreset::namedPresets() as $preset)
            <button
                type="button"
                wire:click="setDatePreset('{{ $preset->value }}')"
                @class([
                    'rounded-md px-3 py-1.5 text-xs font-medium transition',
                    'bg-zinc-900 text-white shadow-sm dark:bg-white dark:text-zinc-900' => $datePreset === $preset->value,
                    'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200' => $datePreset !== $preset->value,
                ])
            >{{ $preset->label() }}</button>
        @endforeach

        <button
            type="button"
            @click="
                if (showCustom) {
                    if (@js($datePreset === 'custom')) {
                        $wire.closeCustomDateRangePanel();
                    }
                    showCustom = false;
                } else {
                    showCustom = true;
                }
            "
            :class="showCustom || @js($datePreset === 'custom') ? 'bg-indigo-600 text-white shadow-sm' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200'"
            class="rounded-md px-3 py-1.5 text-xs font-medium transition"
        >بازه دلخواه</button>
    </div>

    <div x-show="showCustom" x-cloak data-deferred-date-range class="mt-3 flex flex-wrap items-center gap-3">
        <label class="text-sm text-zinc-500">از</label>
        <x-saas.jalali-date-input wire:key="performance-custom-from" wire:model="draftCustomFrom" defer class="text-sm" />
        <label class="text-sm text-zinc-500">تا</label>
        <x-saas.jalali-date-input wire:key="performance-custom-to" wire:model="draftCustomTo" defer class="text-sm" />
        <button type="button" data-apply-deferred-date-range class="saas-btn-primary text-sm">
            تایید بازه
        </button>
    </div>
</div>
