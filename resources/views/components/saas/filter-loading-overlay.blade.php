@props([
    'target',
    'title' => 'در حال تحلیل نتایج...',
    'subtitle' => 'لطفاً چند لحظه صبر کنید',
])

<div
    wire:loading.flex
    wire:target="{{ $target }}"
    {{ $attributes->class(['saas-loading-overlay hidden']) }}
>
    <div class="flex flex-col items-center gap-3 rounded-xl border border-zinc-200/80 bg-white/95 px-8 py-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-900/95">
        <span class="inline-flex h-10 w-10 animate-spin rounded-full border-[3px] border-indigo-500 border-t-transparent"></span>
        <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $title }}</p>
        <p class="text-xs text-zinc-500">{{ $subtitle }}</p>
    </div>
</div>
