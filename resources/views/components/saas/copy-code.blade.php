@props([
    'label',
    'code',
    'open' => false,
])

<details
    @class([
        'rounded-lg border border-sky-200/80 bg-white/70 p-3 text-xs dark:border-sky-800 dark:bg-sky-950/60',
    ])
    @if ($open) open @endif
    x-data="{
        copied: false,
        copy() {
            navigator.clipboard.writeText(@js($code)).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            });
        },
    }"
>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 font-medium">
        <span>{{ $label }}</span>
        <button
            type="button"
            x-on:click.stop.prevent="copy()"
            class="shrink-0 rounded border border-sky-300 bg-sky-100 px-2 py-0.5 text-[10px] font-medium text-sky-900 hover:bg-sky-200 dark:border-sky-700 dark:bg-sky-900 dark:text-sky-100"
        >
            <span x-show="!copied">{{ __('ui.voip.webhook_copy') }}</span>
            <span x-show="copied" x-cloak>{{ __('ui.voip.webhook_copied') }}</span>
        </button>
    </summary>
    @if ($slot->isNotEmpty())
        <div class="mt-2 text-sky-800/90 dark:text-sky-300">{{ $slot }}</div>
    @endif
    <pre class="mt-2 overflow-x-auto rounded bg-white p-2 font-mono text-[11px] text-zinc-700 dark:bg-zinc-950 dark:text-zinc-200" dir="ltr">{{ $code }}</pre>
</details>
