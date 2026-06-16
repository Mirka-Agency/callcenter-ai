<div class="saas-page space-y-6">
    <x-saas.page-header
        title="ویرایش مشتری"
        :description="'به‌روزرسانی اطلاعات «'.$customer->displayName().'»'"
    >
        <x-slot:actions>
            <a href="{{ $backRoute }}" class="saas-btn-secondary text-sm">انصراف</a>
        </x-slot:actions>
    </x-saas.page-header>

    <form wire:submit="save" class="saas-card max-w-2xl space-y-5">
        <div class="flex items-center gap-4 border-b border-zinc-200/80 pb-5 dark:border-zinc-800">
            <x-saas.avatar :name="$customer->displayName()" size="lg" ring />
            <div>
                <p class="text-sm text-zinc-500">شناسه مشتری</p>
                <p class="font-medium text-zinc-900 dark:text-white">#{{ $customer->id }}</p>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-medium">نام</label>
                <input wire:model="name" class="saas-input" placeholder="نام مشتری">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">نام شرکت</label>
                <input wire:model="company_name" class="saas-input" placeholder="نام شرکت (اختیاری)">
                @error('company_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-medium">شماره تماس *</label>
                <input wire:model="phone_number" class="saas-input" dir="ltr" required>
                @error('phone_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">ایمیل</label>
                <input wire:model="email" type="email" class="saas-input" dir="ltr" placeholder="example@mail.com">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium">سمت / نقش</label>
            <input wire:model="job_title" class="saas-input" placeholder="مثلاً مدیر خرید">
            @error('job_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <p class="rounded-lg border border-zinc-200/80 bg-zinc-50 px-4 py-3 text-xs leading-relaxed text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900/60">
            امتیاز لید، روند مکالمه و سایر شاخص‌های هوش مصنوعی پس از تحلیل تماس‌ها به‌صورت خودکار به‌روز می‌شوند و از اینجا قابل ویرایش نیستند.
        </p>

        <button type="submit" class="saas-btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">ذخیره تغییرات</span>
            <span wire:loading wire:target="save">در حال ذخیره…</span>
        </button>
    </form>
</div>
