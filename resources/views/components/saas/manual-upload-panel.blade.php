@props([
    'employees' => collect(),
    'showEmployeeAssign' => false,
    'uploadZoneState' => 'idle',
    'selectedFileName' => null,
    'selectedFileSize' => null,
    'showMetadata' => false,
    'audioReady' => false,
    'highlightedSampleId' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-6']) }}>
    <x-saas.audio-dropzone
        :state="$uploadZoneState"
        :file-name="$selectedFileName"
        :file-size="$selectedFileSize"
    />

    <div class="rounded-lg border border-zinc-200/80 dark:border-zinc-800">
        <button
            type="button"
            wire:click="$toggle('showMetadata')"
            class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-900/50"
        >
            <span>جزئیات تماس (اختیاری)</span>
            <svg @class(['h-4 w-4 text-zinc-400 transition', 'rotate-180' => $showMetadata]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        @if ($showMetadata)
            <div class="grid gap-4 border-t border-zinc-200/80 p-4 dark:border-zinc-800 sm:grid-cols-2">
                @if ($showEmployeeAssign)
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">اختصاص به کارشناس</label>
                        <select wire:model="employeeId" class="saas-input mt-1 text-sm">
                            <option value="">— بدون اختصاص —</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">عنوان</label>
                    <input wire:model="title" class="saas-input mt-1 text-sm" placeholder="مثلاً تماس پیگیری فروش">
                </div>
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">دسته‌بندی</label>
                    <input wire:model="category" class="saas-input mt-1 text-sm" placeholder="فروش، پشتیبانی، …">
                </div>
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">نام مشتری</label>
                    <input wire:model="customerName" class="saas-input mt-1 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">تلفن مشتری</label>
                    <input wire:model="customerPhone" class="saas-input mt-1 text-sm" dir="ltr">
                </div>
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">تاریخ مکالمه</label>
                    <input wire:model="conversationDate" type="datetime-local" class="saas-input mt-1 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">برچسب‌ها</label>
                    <input wire:model="tags" class="saas-input mt-1 text-sm" placeholder="با کاما جدا کنید">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">یادداشت‌ها</label>
                    <textarea wire:model="notes" rows="3" class="saas-input mt-1 text-sm" placeholder="مثلاً موضوع تماس، نگرانی مشتری یا نکته‌ای که به تحلیل کمک می‌کند…"></textarea>
                </div>
            </div>
        @endif
    </div>

    <button
        type="button"
        id="upload-analyze-button"
        wire:click="submitForAnalysis"
        wire:loading.attr="disabled"
        wire:target="submitForAnalysis,audio"
        @class([
            'saas-btn-primary w-full py-3 text-base',
            'opacity-50 cursor-not-allowed' => ! $audioReady,
        ])
        @disabled(! $audioReady)
    >
        <span wire:loading.remove wire:target="submitForAnalysis">{{ __('ui.cta.start_analysis') }}</span>
        <span wire:loading wire:target="submitForAnalysis">در حال ارسال…</span>
    </button>

    @if ($highlightedSampleId)
        <p class="text-center text-xs text-zinc-500">
            برای تحلیل سریع، از دکمه «تحلیل این نمونه» در بخش نمونه مکالمه استفاده کنید — نیازی به بارگذاری فایل نیست.
        </p>
    @endif
</div>
