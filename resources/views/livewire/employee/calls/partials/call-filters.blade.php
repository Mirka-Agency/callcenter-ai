@php
    use App\Enums\ReportDatePreset;

    $activeDatePreset = ReportDatePreset::tryFrom($datePreset);
    $isDefaultDate = $datePreset === ReportDatePreset::Last30->value
        && ! $customFrom
        && ! $customTo;
@endphp

<div
    class="saas-card sticky top-0 z-10 space-y-5 shadow-sm"
    wire:key="employee-call-filters-{{ $datePreset }}-{{ $customFrom }}-{{ $customTo }}"
    x-data="{ showCustom: @js($showCustomDateRange || $datePreset === 'custom'), showMore: @js($showMoreDatePresets) }"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">فیلترها</h2>
        @if ($filter->hasUserFilters())
            <button type="button" wire:click="clearFilters" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                پاک کردن فیلترها
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
            <x-saas.jalali-date-input wire:key="employee-call-from" wire:model="draftCustomFrom" defer class="text-sm" />
            <label class="text-sm text-zinc-500">تا</label>
            <x-saas.jalali-date-input wire:key="employee-call-to" wire:model="draftCustomTo" defer class="text-sm" />
            <button type="button" data-apply-deferred-date-range class="saas-btn-primary text-sm">
                تایید بازه
            </button>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 border-t border-zinc-200/80 pt-4 dark:border-zinc-800">
        <span class="w-full text-sm font-medium text-zinc-700 dark:text-zinc-300">فیلتر سریع</span>
        <button
            type="button"
            wire:click="applyQuickFilter('missed')"
            @class([
                'rounded-md px-3 py-1.5 text-xs font-medium transition',
                'bg-red-600 text-white' => $callStatus === 'missed',
                'bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-500/10 dark:text-red-400' => $callStatus !== 'missed',
            ])
        >تماس‌های از دست رفته</button>
        <button
            type="button"
            wire:click="applyQuickFilter('high_score')"
            @class([
                'rounded-md px-3 py-1.5 text-xs font-medium transition',
                'bg-emerald-600 text-white' => $sortBy === 'score' && $sortDir === 'desc',
                'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400' => ! ($sortBy === 'score' && $sortDir === 'desc'),
            ])
        >بالاترین امتیازها</button>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">وضعیت تماس</label>
            <select wire:model.live="callStatus" class="saas-input mt-1 text-sm">
                <option value="">همه وضعیت‌ها</option>
                @foreach ($callStatuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">جهت تماس</label>
            <select wire:model.live="directionFilter" class="saas-input mt-1 text-sm">
                <option value="">همه</option>
                @foreach ($directions as $direction)
                    <option value="{{ $direction->value }}">{{ $direction->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">حداقل مدت (دقیقه)</label>
                <input wire:model.live.debounce.500ms="durationMin" type="number" min="0" placeholder="۰" class="saas-input mt-1 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">حداکثر مدت (دقیقه)</label>
                <input wire:model.live.debounce.500ms="durationMax" type="number" min="0" placeholder="∞" class="saas-input mt-1 text-sm">
            </div>
        </div>
    </div>

    @if ($filter->hasUserFilters())
        <div class="flex flex-wrap items-center gap-2 border-t border-zinc-200/80 pt-4 dark:border-zinc-800">
            <span class="text-xs font-medium text-zinc-500">فیلترهای فعال:</span>
            @if (! $isDefaultDate)
                <button type="button" wire:click="clearDateFilter" class="rounded-md bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                    @if ($datePreset === 'custom' && ($customFrom || $customTo))
                        بازه: {{ $customFrom ? shamsi($customFrom) : '…' }} تا {{ $customTo ? shamsi($customTo) : '…' }} ×
                    @else
                        بازه: {{ $activeDatePreset?->label() ?? $datePreset }} ×
                    @endif
                </button>
            @endif
            @if ($callStatus)
                <button type="button" wire:click="$set('callStatus', null)" class="rounded-md bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    وضعیت: {{ \App\Domain\Voip\Enums\CallStatus::tryFrom($callStatus)?->label() }} ×
                </button>
            @endif
            @if ($directionFilter)
                <button type="button" wire:click="$set('directionFilter', null)" class="rounded-md bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    جهت: {{ \App\Domain\Voip\Enums\CallDirection::tryFrom($directionFilter)?->label() }} ×
                </button>
            @endif
            @if ($durationMin)
                <button type="button" wire:click="$set('durationMin', null)" class="rounded-md bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    حداقل {{ $durationMin }} دقیقه ×
                </button>
            @endif
            @if ($durationMax)
                <button type="button" wire:click="$set('durationMax', null)" class="rounded-md bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    حداکثر {{ $durationMax }} دقیقه ×
                </button>
            @endif
        </div>
    @endif
</div>
