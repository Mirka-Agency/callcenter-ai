@props([
    'label' => null,
    'placeholder' => 'انتخاب تاریخ',
    'clearable' => false,
    'defer' => false,
])

@php
    $wireModel = null;

    foreach ($attributes->getAttributes() as $key => $value) {
        if (str_starts_with($key, 'wire:model')) {
            $wireModel = $value;
            break;
        }
    }

    if ($wireModel === null && method_exists($attributes, 'wire')) {
        $wireModel = $attributes->wire('model')->value();
    }
@endphp

<div
    wire:ignore
    {{ $attributes->except(['wire:model', 'wire:model.live', 'wire:model.blur', 'wire:model.defer', 'wire:model.lazy'])->merge(['class' => 'relative']) }}
    data-jalali-date-input
    @if ($defer) data-wire-defer="1" @endif
    @if ($wireModel) data-wire-model="{{ $wireModel }}" @endif
>
    @if ($label)
        <label class="mb-1 block text-xs font-medium text-zinc-500">{{ $label }}</label>
    @endif

    <div class="relative">
        <button
            type="button"
            data-jalali-trigger
            class="saas-input flex w-full items-center justify-between gap-2 text-start text-sm"
        >
            <input
                type="text"
                data-jalali-display
                readonly
                placeholder="{{ $placeholder }}"
                class="w-full cursor-pointer border-0 bg-transparent p-0 text-inherit outline-none"
            >
            <svg class="h-4 w-4 shrink-0 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </button>

        <div
            data-jalali-panel
            hidden
            class="jalali-date-panel absolute z-50 mt-2 w-72 rounded-lg border border-zinc-200/80 bg-white p-4 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
        >
            <div class="mb-3 flex items-center gap-2">
                <select data-jalali-month class="saas-input flex-1 py-1.5 text-sm">
                    @foreach (['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'] as $index => $month)
                        <option value="{{ $index }}">{{ $month }}</option>
                    @endforeach
                </select>
                <input data-jalali-year type="number" class="saas-input w-24 py-1.5 text-sm" min="1300" max="1500">
            </div>

            <div class="mb-2 grid grid-cols-7 gap-1 text-center text-xs font-medium text-zinc-500">
                @foreach (['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'] as $day)
                    <div>{{ $day }}</div>
                @endforeach
            </div>

            <div data-jalali-days class="grid grid-cols-7 gap-1"></div>

            @if ($clearable)
                <button type="button" data-jalali-clear class="mt-3 w-full text-center text-xs font-medium text-zinc-500 hover:text-zinc-700">
                    پاک کردن
                </button>
            @endif
        </div>
    </div>
</div>
