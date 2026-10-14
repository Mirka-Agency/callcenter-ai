<div class="saas-page space-y-6">
    <nav class="text-sm text-zinc-500" aria-label="مسیر">
        <a href="{{ route('employer.crm.pipelines.index') }}" class="hover:text-indigo-600" wire:navigate>پایپ‌لاین‌ها</a>
        <span class="mx-2">/</span>
        <span class="text-zinc-800 dark:text-zinc-200">{{ $pipeline->name }}</span>
    </nav>

    <x-saas.page-header :title="$pipeline->name" description="ترتیب مراحل همان ترتیب برد معاملات است. موفق و ناموفق وضعیت معامله‌اند، نه مرحله." />

    <form wire:submit="savePipeline" class="saas-card grid gap-3 p-4 md:grid-cols-2">
        <label class="text-sm">
            <span class="mb-1 block text-zinc-600">نام</span>
            <input wire:model="name" class="saas-input">
            @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </label>
        <label class="text-sm">
            <span class="mb-1 block text-zinc-600">توضیح</span>
            <input wire:model="description" class="saas-input">
        </label>
        <label class="flex items-center gap-2 text-sm md:col-span-2">
            <input wire:model="isActive" type="checkbox" class="rounded border-zinc-300 text-indigo-600" @disabled($pipeline->is_default)>
            فعال
        </label>
        <div class="flex flex-wrap gap-2 md:col-span-2">
            <button type="submit" class="saas-btn-primary text-sm">ذخیره</button>
            @unless ($pipeline->is_default)
                <button type="button" class="saas-btn-secondary text-sm" wire:click="makeDefault">پیش‌فرض شود</button>
            @endunless
        </div>
        @error('is_active') <p class="text-sm text-rose-600 md:col-span-2">{{ $message }}</p> @enderror
    </form>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold">مراحل</h2>
        @foreach ($pipeline->stages as $stage)
            <article class="saas-card p-4" wire:key="stage-row-{{ $stage->id }}">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full" style="background: {{ $stage->color }}"></span>
                        <div>
                            <p class="font-medium">{{ $stage->position }}. {{ $stage->name }}</p>
                            <p class="text-xs text-zinc-500">{{ (int) ($dealCounts[$stage->id] ?? 0) }} معامله · {{ $stage->is_active ? 'فعال' : 'غیرفعال' }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="saas-btn-secondary text-sm" wire:click="moveStage({{ $stage->id }}, 'up')" aria-label="انتقال {{ $stage->name }} به بالا">بالا</button>
                        <button type="button" class="saas-btn-secondary text-sm" wire:click="moveStage({{ $stage->id }}, 'down')" aria-label="انتقال {{ $stage->name }} به پایین">پایین</button>
                        <button type="button" class="saas-btn-secondary text-sm" wire:click="editStage({{ $stage->id }})">ویرایش</button>
                    </div>
                </div>
                @if ($editingStageId === (int) $stage->id)
                    <form wire:submit="saveStage" class="mt-4 grid gap-3 border-t border-zinc-100 pt-4 md:grid-cols-2">
                        <label class="text-sm">
                            <span class="mb-1 block">عنوان</span>
                            <input wire:model="stageName" class="saas-input">
                        </label>
                        <label class="text-sm">
                            <span class="mb-1 block">رنگ</span>
                            <select wire:model="stageColor" class="saas-input">
                                @foreach ($colors as $color)
                                    <option value="{{ $color }}">{{ $color }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input wire:model="stageActive" type="checkbox" class="rounded border-zinc-300 text-indigo-600">
                            فعال
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="saas-btn-primary text-sm">ذخیره مرحله</button>
                        </div>
                        @if ((int) ($dealCounts[$stage->id] ?? 0) > 0)
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 md:col-span-2">
                                <p>این مرحله معامله دارد. حذف فقط با انتقال معاملات به مرحله دیگر انجام می‌شود.</p>
                                <label class="mt-2 block">
                                    <span class="mb-1 block">مرحله مقصد</span>
                                    <select wire:model="transferStageId" class="saas-input">
                                        <option value="">انتخاب کنید</option>
                                        @foreach ($pipeline->stages as $target)
                                            @if ((int) $target->id !== (int) $stage->id)
                                                <option value="{{ $target->id }}">{{ $target->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        @endif
                        <div class="md:col-span-2">
                            <button type="button" class="saas-btn-secondary text-sm" wire:click="deleteStage({{ $stage->id }})" wire:confirm="حذف مرحله انجام شود؟">حذف مرحله</button>
                            @error('stage') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                            @error('transfer') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </form>
                @endif
            </article>
        @endforeach
    </section>

    <form wire:submit="addStage" class="saas-card grid gap-3 p-4 md:grid-cols-3">
        <h2 class="text-lg font-semibold md:col-span-3">مرحله جدید</h2>
        <label class="text-sm">
            <span class="mb-1 block text-zinc-600">عنوان</span>
            <input wire:model="newStageName" class="saas-input">
        </label>
        <label class="text-sm">
            <span class="mb-1 block text-zinc-600">رنگ</span>
            <select wire:model="newStageColor" class="saas-input">
                @foreach ($colors as $color)
                    <option value="{{ $color }}">{{ $color }}</option>
                @endforeach
            </select>
        </label>
        <div class="self-end">
            <button type="submit" class="saas-btn-primary text-sm">افزودن</button>
        </div>
        @error('color') <p class="text-xs text-rose-600 md:col-span-3">{{ $message }}</p> @enderror
    </form>
</div>
