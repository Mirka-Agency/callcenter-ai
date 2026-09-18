<div class="saas-page space-y-6">
    <x-saas.page-header
        title="ویرایش شرکت"
        :description="'به‌روزرسانی اطلاعات «'.($company->displayName()).'»'"
    >
        <x-slot:actions>
            <a href="{{ $backRoute }}" class="saas-btn-secondary text-sm" wire:navigate>انصراف</a>
        </x-slot:actions>
    </x-saas.page-header>

    <form wire:submit="save" class="saas-card max-w-3xl space-y-5">
        <div class="flex items-center gap-4 border-b border-zinc-200/80 pb-5 dark:border-zinc-800">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-xl font-bold text-white">
                {{ mb_substr($company->displayName(), 0, 1) }}
            </div>
            <div>
                <p class="text-sm text-zinc-500">{{ $company->contacts_count }} شخص · {{ $company->total_calls }} تماس</p>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-2 block text-sm font-medium">نام شرکت *</label>
                <input wire:model="name" class="saas-input" required>
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">صنعت</label>
                <input wire:model="industry" class="saas-input" placeholder="مثلاً فناوری، خرده‌فروشی">
                @error('industry') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">وب‌سایت</label>
                <input wire:model="website" class="saas-input" dir="ltr" placeholder="https://example.com">
                @error('website') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">تلفن شرکت</label>
                <input wire:model="phone" class="saas-input" dir="ltr">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">ایمیل شرکت</label>
                <input wire:model="email" type="email" class="saas-input" dir="ltr">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-2 block text-sm font-medium">آدرس</label>
                <textarea wire:model="address" rows="2" class="saas-input"></textarea>
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-2 block text-sm font-medium">یادداشت</label>
                <textarea wire:model="notes" rows="3" class="saas-input" placeholder="نکات داخلی درباره این شرکت"></textarea>
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <button type="submit" class="saas-btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">ذخیره تغییرات</span>
            <span wire:loading wire:target="save">در حال ذخیره…</span>
        </button>
    </form>
</div>
