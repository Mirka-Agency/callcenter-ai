@php
    use App\Support\PersianNumber;
@endphp

<div
    class="crm-pipeline-builder"
    x-data="{
        dragKey: null,
        helpOpen: false,
        helpType: null,
        onDragStart(key, event) {
            this.dragKey = key;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', key);
            const card = event.currentTarget.closest('article');
            if (card) {
                event.dataTransfer.setDragImage(card, Math.min(48, card.offsetWidth / 2), 28);
            }
        },
        onDrop(targetKey) {
            if (! this.dragKey || this.dragKey === targetKey) return;
            $wire.moveStage(this.dragKey, targetKey);
            this.dragKey = null;
        },
        openHelp(type) {
            this.helpType = type;
            this.helpOpen = true;
        },
        closeHelp() {
            this.helpOpen = false;
            this.helpType = null;
        }
    }"
>
    <header class="crm-pipeline-builder__header">
        <div class="min-w-0">
            <nav class="crm-pipeline-builder__crumbs" aria-label="مسیر صفحه">
                <a href="{{ $dealsUrl }}" wire:navigate>CRM</a>
                <span aria-hidden="true">/</span>
                <a href="{{ $pipelinesUrl }}" wire:navigate>کاریز</a>
                <span aria-hidden="true">/</span>
                <span>{{ $isEditing ? 'ویرایش کاریز' : 'ایجاد کاریز جدید' }}</span>
            </nav>
            <div class="mt-2 flex items-center gap-2">
                <span class="crm-pipeline-builder__title-icon" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h4v14H4V5Zm6 0h4v9h-4V5Zm6 0h4v6h-4V5Z" />
                    </svg>
                </span>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">
                    {{ $isEditing ? 'ویرایش کاریز' : 'ایجاد کاریز جدید' }}
                </h1>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="saas-btn-secondary text-sm" wire:click="cancel">انصراف</button>
            <button type="button" class="saas-btn-brand text-sm" wire:click="save" wire:loading.attr="disabled">ذخیره</button>
        </div>
    </header>

    @error('stages')
        <p class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</p>
    @enderror

    <div class="crm-pipeline-builder__layout">
        <aside class="crm-pipeline-builder__sidebar space-y-5">
            <section class="space-y-4">
                <h2 class="text-sm font-bold text-zinc-900 dark:text-white">اطلاعات کلی</h2>

                <label class="block text-sm">
                    <span class="mb-1.5 block font-medium text-zinc-700 dark:text-zinc-300">عنوان کاریز</span>
                    <input wire:model="name" type="text" class="saas-input" maxlength="120" placeholder="کاریز فروش">
                    @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>

                <label class="block text-sm">
                    <span class="mb-1.5 block font-medium text-zinc-700 dark:text-zinc-300">توضیحات</span>
                    <textarea
                        wire:model="description"
                        rows="5"
                        class="saas-input"
                        maxlength="2000"
                        placeholder="توضیح کوتاهی درباره این کاریز..."
                    ></textarea>
                    @error('description') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>
            </section>

            <section class="space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                <h2 class="text-sm font-bold text-zinc-900 dark:text-white">تنظیمات</h2>

                <label class="crm-pipeline-toggle">
                    <span>
                        <span class="block font-medium text-zinc-800 dark:text-zinc-100">فعال بودن کاریز</span>
                        <span class="mt-0.5 block text-xs text-zinc-500">نمایش در فهرست کاریزها و معاملات</span>
                    </span>
                    <input type="checkbox" wire:model.live="isActive" class="crm-pipeline-switch">
                </label>

                <label class="crm-pipeline-toggle">
                    <span>
                        <span class="block font-medium text-zinc-800 dark:text-zinc-100">کاریز پیش‌فرض</span>
                        <span class="mt-0.5 block text-xs text-zinc-500">برای فرصت‌های جدید به‌صورت پیش‌فرض</span>
                    </span>
                    <input type="checkbox" wire:model.live="isDefault" class="crm-pipeline-switch" @disabled($isEditing && $isDefault)>
                </label>

                <label class="crm-pipeline-toggle">
                    <span>
                        <span class="block font-medium text-zinc-800 dark:text-zinc-100">نمایش احتمال موفقیت معامله</span>
                        <span class="mt-0.5 block text-xs text-zinc-500">نمایش فیلد احتمال روی کارت مراحل</span>
                    </span>
                    <input type="checkbox" wire:model.live="showWinProbability" class="crm-pipeline-switch">
                </label>
            </section>
        </aside>

        <div class="crm-pipeline-builder__stages" role="list" aria-label="مراحل کاریز">
            @foreach ($stages as $index => $stage)
                <article
                    class="crm-pipeline-stage-card"
                    role="listitem"
                    wire:key="{{ $stage['key'] }}"
                    draggable="true"
                    x-on:dragstart="onDragStart(@js($stage['key']), $event)"
                    x-on:dragover.prevent
                    x-on:drop.prevent="onDrop(@js($stage['key']))"
                >
                    <header
                        class="crm-pipeline-stage-card__header"
                        style="--stage-color: {{ \App\Domain\Crm\Deals\PipelineStagePalette::colorForPosition($index + 1) }}"
                        title="برای جابه‌جایی بکشید"
                        draggable="true"
                        x-on:dragstart.stop="onDragStart(@js($stage['key']), $event)"
                    >
                        <span class="truncate">{{ $stage['name'] ?: 'مرحله '.PersianNumber::format($index + 1) }}</span>
                    </header>

                    <div class="crm-pipeline-stage-card__body">
                        <div class="crm-pipeline-stage-field">
                            <div class="crm-pipeline-stage-field__label">
                                <svg class="crm-pipeline-stage-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z" />
                                </svg>
                                <span>عنوان مرحله</span>
                            </div>
                            <input wire:model.live="stages.{{ $index }}.name" type="text" class="crm-pipeline-stage-field__input" maxlength="80">
                            @error('stages.'.$index.'.name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="crm-pipeline-stage-field">
                            <div class="crm-pipeline-stage-field__label">
                                <svg class="crm-pipeline-stage-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h10" />
                                </svg>
                                <span>توضیحات مرحله</span>
                            </div>
                            <textarea
                                wire:model="stages.{{ $index }}.description"
                                rows="4"
                                class="crm-pipeline-stage-field__textarea"
                                maxlength="2000"
                            ></textarea>
                        </div>

                        @if ($showWinProbability)
                            <div class="crm-pipeline-stage-field">
                                <div class="crm-pipeline-stage-field__label">
                                    <svg class="crm-pipeline-stage-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M5 8l7-3 7 3M5 16l7 3 7-3M5 8v8m14-8v8" />
                                    </svg>
                                    <span>احتمال موفقیت معامله</span>
                                </div>
                                <input
                                    wire:model="stages.{{ $index }}.win_probability"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="crm-pipeline-stage-field__input"
                                    placeholder="0"
                                >
                                @error('stages.'.$index.'.win_probability') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <label class="crm-pipeline-stage-switch-row">
                            <span class="crm-pipeline-stage-field__label">
                                <svg class="crm-pipeline-stage-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0" />
                                </svg>
                                <span>شاخص راکد</span>
                                <button
                                    type="button"
                                    class="crm-pipeline-help-btn"
                                    data-tip="شاخص راکد چیست"
                                    aria-label="شاخص راکد چیست"
                                    x-on:click.prevent.stop="openHelp('stale')"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9" />
                                        <path stroke-linecap="round" d="M12 8v.01M11 12h1v5h1" />
                                    </svg>
                                </button>
                            </span>
                            <input type="checkbox" wire:model.live="stages.{{ $index }}.stale_enabled" class="crm-pipeline-stage-switch">
                        </label>

                        <label class="crm-pipeline-stage-switch-row">
                            <span class="crm-pipeline-stage-field__label">
                                <svg class="crm-pipeline-stage-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0" />
                                </svg>
                                <span>شاخص فاسد</span>
                                <button
                                    type="button"
                                    class="crm-pipeline-help-btn"
                                    data-tip="شاخص فاسد چیست"
                                    aria-label="شاخص فاسد چیست"
                                    x-on:click.prevent.stop="openHelp('rotten')"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9" />
                                        <path stroke-linecap="round" d="M12 8v.01M11 12h1v5h1" />
                                    </svg>
                                </button>
                            </span>
                            <input type="checkbox" wire:model.live="stages.{{ $index }}.is_lost_stage" class="crm-pipeline-stage-switch">
                        </label>

                        <button
                            type="button"
                            class="crm-pipeline-stage-card__delete"
                            wire:click="removeStage({{ $index }})"
                            wire:confirm="این مرحله حذف شود؟"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-9 0V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7h12Z" />
                            </svg>
                            حذف مرحله
                        </button>
                    </div>
                </article>
            @endforeach

            <button type="button" class="crm-pipeline-stage-add" wire:click="addStage">
                <span class="crm-pipeline-stage-add__icon" aria-hidden="true">+</span>
                <span class="font-semibold text-zinc-800 dark:text-zinc-100">افزودن مرحله جدید</span>
                <span class="text-xs text-zinc-500">برای افزودن مرحله جدید کلیک کنید</span>
            </button>
        </div>
    </div>

    <div
        x-cloak
        x-show="helpOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/40 p-4 sm:items-center"
        x-on:keydown.escape.window="if (helpOpen) closeHelp()"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="helpType === 'rotten' ? 'pipeline-help-rotten-title' : 'pipeline-help-stale-title'"
    >
        <div
            class="absolute inset-0"
            x-on:click="closeHelp()"
            aria-hidden="true"
        ></div>
        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-950">
            <div class="flex items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <h2
                    class="text-lg font-bold text-zinc-900 dark:text-white"
                    x-bind:id="helpType === 'rotten' ? 'pipeline-help-rotten-title' : 'pipeline-help-stale-title'"
                    x-text="helpType === 'rotten' ? 'شاخص فاسد چیست؟' : 'شاخص راکد چیست؟'"
                ></h2>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800"
                    x-on:click="closeHelp()"
                    aria-label="بستن"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>
            <div class="space-y-3 px-5 py-4 text-sm leading-7 text-zinc-600 dark:text-zinc-300">
                <template x-if="helpType === 'stale'">
                    <p>با فعال شدن این ویژگی در هر مرحله از کاریز، اگر معامله بیش از تعداد روزی که تعیین کرده‌اید بر روی آن یادداشت یا فعالیت ثبت نشود به عنوان معامله راکد در نظر گرفته می‌شود. این بدین معناست که معامله به حد اخطار شاخص راکد شدن رسیده است و باید در سریع ترین زمان روی آن اقدامی صورت بگیرد.</p>
                </template>
                <template x-if="helpType === 'rotten'">
                    <p>با فعال شدن این ویژگی در هر مرحله از کاریز، اگر معامله بیش از تعداد روزی که تعیین کرده‌اید بر روی آن یادداشت یا فعالیت ثبت نشود به عنوان معامله راکد در نظر گرفته می‌شود. این بدین معناست که معامله به حد اخطار شاخص راکد شدن رسیده است و باید در سریع ترین زمان روی آن اقدامی صورت بگیرد.</p>
                </template>
            </div>
            <div class="flex justify-end border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <button type="button" class="saas-btn-primary text-sm" x-on:click="closeHelp()">متوجه شدم</button>
            </div>
        </div>
    </div>
</div>
