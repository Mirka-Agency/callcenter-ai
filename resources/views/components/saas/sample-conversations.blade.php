@props([
    'samples' => [],
    'highlightedId' => null,
])

<div {{ $attributes->merge(['class' => 'saas-card border-dashed']) }} id="sample-conversations">
    <div class="mb-3">
        <h2 class="text-lg font-semibold">مکالمات نمونه</h2>
        <p class="mt-1 text-sm text-zinc-500">
            اگر فایل صوتی ندارید، یکی از نمونه‌ها را برای آزمایش تحلیل انتخاب کنید.
        </p>
    </div>

    <div class="divide-y divide-zinc-200/80 rounded-lg border border-zinc-200/80 dark:divide-zinc-800 dark:border-zinc-800">
        @foreach ($samples as $sample)
            <div
                @class([
                    'flex items-center gap-3 px-3 py-2 transition',
                    'bg-indigo-50/60 dark:bg-indigo-950/20' => $highlightedId === $sample['id'],
                    'opacity-50' => ! $sample['available'],
                ])
                wire:key="sample-{{ $sample['id'] }}"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $sample['title'] }}</p>
                        <span class="saas-badge shrink-0 text-[10px]">{{ $sample['category'] }}</span>
                    </div>
                    @unless ($sample['available'])
                        <p class="mt-0.5 truncate text-[11px] text-amber-600 dark:text-amber-400">فایل صوتی هنوز قرار نگرفته</p>
                    @endunless
                </div>

                <button
                    type="button"
                    wire:click="submitSampleForAnalysis(@js($sample['id']))"
                    wire:loading.attr="disabled"
                    wire:target="submitSampleForAnalysis"
                    @class([
                        'saas-btn-secondary shrink-0 !px-2.5 !py-1 text-xs',
                        'cursor-not-allowed' => ! $sample['available'],
                    ])
                    @disabled(! $sample['available'])
                >
                    <span wire:loading.remove wire:target="submitSampleForAnalysis">تحلیل</span>
                    <span wire:loading wire:target="submitSampleForAnalysis">…</span>
                </button>
            </div>
        @endforeach
    </div>
</div>
