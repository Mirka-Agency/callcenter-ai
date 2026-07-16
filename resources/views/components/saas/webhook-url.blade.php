@props([
    'url',
    'label' => null,
    'method' => 'POST',
    'showMethod' => true,
])

@php
    $label ??= __('ui.voip.webhook_url_label');
@endphp

<div
    x-data="{
        copied: false,
        copy() {
            navigator.clipboard.writeText(@js($url)).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            });
        },
    }"
    {{ $attributes->class(['rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/50']) }}
>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <p class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ $label }}</p>
        @if ($showMethod)
            <span class="rounded bg-zinc-200/80 px-2 py-0.5 font-mono text-[10px] uppercase tracking-wide text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                {{ $method }}
            </span>
        @endif
    </div>

    <p class="mt-2 break-all font-mono text-xs text-zinc-700 dark:text-zinc-200" dir="ltr">{{ $url }}</p>

    <button
        type="button"
        x-on:click="copy()"
        class="mt-2 text-xs font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300"
    >
        <span x-show="!copied">{{ __('ui.voip.webhook_copy') }}</span>
        <span x-show="copied" x-cloak>{{ __('ui.voip.webhook_copied') }}</span>
    </button>
</div>
