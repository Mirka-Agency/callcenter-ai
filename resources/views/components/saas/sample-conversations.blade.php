@props([
    'samples' => [],
    'highlightedId' => null,
])

<div {{ $attributes->merge(['class' => 'saas-card border-dashed border-indigo-200/60 bg-gradient-to-b from-indigo-50/40 to-white dark:border-indigo-500/25 dark:from-indigo-950/30 dark:to-zinc-900']) }} id="sample-conversations">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">نمونه مکالمه</h2>
        <p class="mt-1 text-sm leading-relaxed text-zinc-500">
            بدون آپلود فایل، یک نمونه آماده را تحلیل کنید و خروجی واقعی هوش مصنوعی را ببینید.
        </p>
    </div>

    <div class="grid gap-3">
        @foreach ($samples as $sample)
            <div
                @class([
                    'rounded-lg border bg-white p-4 shadow-sm transition dark:bg-zinc-950',
                    'border-indigo-300 ring-2 ring-indigo-400/30 dark:border-indigo-500/50' => $highlightedId === $sample['id'],
                    'border-zinc-200/80 dark:border-zinc-800' => $highlightedId !== $sample['id'],
                    'opacity-60' => ! $sample['available'],
                ])
                wire:key="sample-{{ $sample['id'] }}"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <div @class([
                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-sm font-bold',
                            'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' => $sample['available'],
                            'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500' => ! $sample['available'],
                        ]) aria-hidden="true">
                            {{ $loop->iteration }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <p class="text-sm font-semibold leading-snug text-zinc-900 dark:text-white sm:text-base">
                                    {{ $sample['title'] }}
                                </p>
                                <span class="saas-badge shrink-0 bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                    {{ $sample['category'] }}
                                </span>
                            </div>

                            @if (filled($sample['description']))
                                <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">
                                    {{ $sample['description'] }}
                                </p>
                            @endif

                            @unless ($sample['available'])
                                <p class="mt-1.5 text-xs text-amber-600 dark:text-amber-400">فایل صوتی هنوز آماده نشده</p>
                            @endunless
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click.stop="submitSampleForAnalysis(@js($sample['id']))"
                        wire:loading.attr="disabled"
                        wire:target="submitSampleForAnalysis"
                        @class([
                            'saas-btn-secondary w-full shrink-0 text-sm sm:w-auto',
                            'cursor-not-allowed opacity-70' => ! $sample['available'],
                        ])
                        @disabled(! $sample['available'])
                    >
                        <span wire:loading.remove wire:target="submitSampleForAnalysis">تحلیل این نمونه</span>
                        <span wire:loading wire:target="submitSampleForAnalysis">در حال شروع…</span>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>
