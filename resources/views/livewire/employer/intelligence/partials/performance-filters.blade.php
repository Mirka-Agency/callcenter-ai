@php
    use App\Enums\ReportDatePreset;

    $activeDatePreset = ReportDatePreset::tryFrom($datePreset);
    $isDefaultDate = $datePreset === ReportDatePreset::Last30->value
        && ! $customFrom
        && ! $customTo;
@endphp

<div
    class="saas-card lg:sticky lg:top-0 lg:z-10 space-y-4 shadow-sm"
    data-tour="performance-filters"
    wire:key="performance-date-filters-{{ $datePreset }}-{{ $customFrom }}-{{ $customTo }}"
    x-data="{ showCustom: @js($showCustomDateRange || $datePreset === 'custom'), showMore: @js($showMoreDatePresets) }"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">فیلترها</h2>
        @if (! $isDefaultDate || $selectedEmployeeIds !== [])
            <button type="button" wire:click="clearFilters" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                @lang('ui.cta.clear_filters')
            </button>
        @endif
    </div>

    <div class="space-y-3">
        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">بازه زمانی</p>

        <div class="flex flex-wrap gap-2">
            @foreach ($primaryDatePresets as $preset)
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

            <button
                type="button"
                @click="showMore = !showMore"
                @class([
                    'rounded-md px-3 py-1.5 text-xs font-medium transition',
                    'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => collect($moreDatePresets)->contains(fn ($p) => $p->value === $datePreset),
                    'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200' => ! collect($moreDatePresets)->contains(fn ($p) => $p->value === $datePreset),
                ])
            ><span x-text="showMore ? 'بستن' : 'بیشتر'"></span></button>
        </div>

        <div x-show="showMore" x-cloak class="flex flex-wrap gap-2 border-s-2 border-zinc-200 ps-3 dark:border-zinc-700">
                @foreach ($moreDatePresets as $preset)
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
        </div>

        <div x-show="showCustom" x-cloak data-deferred-date-range class="flex flex-wrap items-center gap-3 rounded-lg border border-indigo-200/80 bg-indigo-50/50 px-4 py-3 dark:border-indigo-500/30 dark:bg-indigo-950/20">
                <label class="text-sm text-zinc-500">از</label>
                <x-saas.jalali-date-input wire:key="performance-custom-from" wire:model="draftCustomFrom" defer class="text-sm" />
                <label class="text-sm text-zinc-500">تا</label>
                <x-saas.jalali-date-input wire:key="performance-custom-to" wire:model="draftCustomTo" defer class="text-sm" />
                <button type="button" data-apply-deferred-date-range class="saas-btn-primary text-sm">
                    تایید بازه
                </button>
        </div>
    </div>

    @if (isset($filterEmployees) && $filterEmployees->isNotEmpty())
        <div class="space-y-2 border-t border-zinc-200/80 pt-4 dark:border-zinc-800">
            <span class="text-sm font-medium text-zinc-500">مشاهده پروفایل کارشناس</span>
            <div class="flex flex-wrap items-center gap-2">
                <x-saas.agent-chip
                    name="همه"
                    wire:click="clearEmployeeFilter"
                    :active="$selectedEmployeeIds === []"
                />
                @foreach ($filterEmployees as $employee)
                    <x-saas.agent-chip
                        :employee="$employee"
                        :href="route('employer.intelligence.performance.show', $employee->id).'?preset='.$datePreset.'&from='.$customFrom.'&to='.$customTo"
                    />
                @endforeach
            </div>
        </div>
    @endif

    @if (! $isDefaultDate)
        <div class="flex flex-wrap items-center gap-2 border-t border-zinc-200/80 pt-4 dark:border-zinc-800">
            <span class="text-xs font-medium text-zinc-500">فیلترهای فعال:</span>
            <button type="button" wire:click="clearDateFilter" class="rounded-md bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                @if ($datePreset === 'custom' && ($customFrom || $customTo))
                    بازه: {{ $customFrom ? shamsi($customFrom) : '…' }} تا {{ $customTo ? shamsi($customTo) : '…' }} ×
                @else
                    بازه: {{ $activeDatePreset?->label() ?? $datePreset }} ×
                @endif
            </button>
        </div>
    @endif
</div>
