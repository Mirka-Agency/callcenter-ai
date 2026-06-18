@props([
    'target',
    'title' => 'در حال به‌روزرسانی نتایج…',
    'subtitle' => 'چند لحظه صبر کنید',
    'scoped' => true,
])

{{-- Non-blocking status chip — only for explicit actions (not wire:model.live). --}}
<div
    wire:loading.delay.300ms.flex
    wire:target="{{ $target }}"
    {{ $attributes->class([
        'saas-filter-loading-chip',
        $scoped ? 'saas-filter-loading-chip--scoped' : 'saas-filter-loading-chip--fixed',
    ]) }}
    role="status"
    aria-live="polite"
>
    <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-indigo-500 border-t-transparent" aria-hidden="true"></span>
    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $title }}</span>
</div>
