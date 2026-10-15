<div class="saas-page space-y-6">
    <nav class="text-sm text-zinc-500" aria-label="مسیر">
        <span>CRM</span>
        <span class="mx-2">/</span>
        <span class="text-zinc-800 dark:text-zinc-200">پایپ‌لاین‌های فروش</span>
    </nav>

    <x-saas.page-header title="مدیریت مراحل کاریز" description="کاریزها و مراحل داخلی آوایار از اتصالات خارجی مستقل است.">
        <x-slot:actions>
            <button type="button" class="saas-btn-secondary text-sm" wire:click="createDefault">پایپ‌لاین پیش‌فرض</button>
            <button type="button" class="saas-btn-primary text-sm" wire:click="$toggle('showCreate')">پایپ‌لاین جدید</button>
        </x-slot:actions>
    </x-saas.page-header>

    @if ($showCreate)
        <form wire:submit="createPipeline" class="saas-card grid gap-3 p-4 md:grid-cols-2">
            <label class="text-sm">
                <span class="mb-1 block text-zinc-600">نام</span>
                <input wire:model="name" class="saas-input">
                @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-zinc-600">توضیح</span>
                <input wire:model="description" class="saas-input">
            </label>
            <div class="flex gap-2 md:col-span-2">
                <button type="submit" class="saas-btn-primary text-sm">ساخت</button>
                <button type="button" class="saas-btn-secondary text-sm" wire:click="$set('showCreate', false)">انصراف</button>
            </div>
        </form>
    @endif

    @if ($pipelines->isEmpty())
        <x-saas.empty-state title="پایپ‌لاینی ثبت نشده" description="با دکمه پایپ‌لاین پیش‌فرض، مراحل سرنخ جدید، نیازسنجی، ارائه پیشنهاد و مذاکره ساخته می‌شود. موفق و ناموفق مرحله نیستند." />
    @else
        <div class="space-y-3">
            @foreach ($pipelines as $pipeline)
                <article class="saas-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between" wire:key="pipeline-{{ $pipeline->id }}">
                    <div>
                        <a href="{{ route('employer.crm.pipelines.show', $pipeline) }}" class="font-semibold text-indigo-700" wire:navigate>{{ $pipeline->name }}</a>
                        <p class="mt-1 text-sm text-zinc-500">{{ $pipeline->stages_count }} مرحله · {{ $pipeline->deals_count }} معامله</p>
                        @if ($pipeline->description)
                            <p class="mt-1 text-sm text-zinc-600">{{ $pipeline->description }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($pipeline->is_default)
                            <span class="saas-badge">پیش‌فرض</span>
                        @else
                            <button type="button" class="saas-btn-secondary text-sm" wire:click="makeDefault({{ $pipeline->id }})">انتخاب پیش‌فرض</button>
                        @endif
                        @if ($pipeline->is_active)
                            <button type="button" class="saas-btn-secondary text-sm" wire:click="saveActive({{ $pipeline->id }}, false)">غیرفعال</button>
                        @else
                            <button type="button" class="saas-btn-secondary text-sm" wire:click="saveActive({{ $pipeline->id }}, true)">فعال</button>
                        @endif
                        <button type="button" class="saas-btn-secondary text-sm" wire:click="deletePipeline({{ $pipeline->id }})" wire:confirm="اگر این پایپ‌لاین معامله داشته باشد حذف نمی‌شود.">حذف</button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
    @error('pipeline') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
    @error('is_active') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
</div>
