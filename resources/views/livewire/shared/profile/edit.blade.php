<div class="saas-page space-y-6">
    <x-saas.page-header
        title="پروفایل من"
        description="اطلاعات حساب و نمایه عمومی خود را مدیریت کنید."
    >
        <x-slot:actions>
            <a href="{{ $backRoute }}" class="saas-btn-secondary text-sm">بازگشت</a>
        </x-slot:actions>
    </x-saas.page-header>

    <form wire:submit="save" class="saas-card max-w-2xl space-y-5">
        <div class="flex flex-col gap-4 border-b border-zinc-200/80 pb-5 dark:border-zinc-800 sm:flex-row sm:items-center">
            @if ($avatar)
                <img
                    src="{{ $avatar->temporaryUrl() }}"
                    alt=""
                    class="h-20 w-20 shrink-0 rounded-full object-cover ring-2 ring-white dark:ring-zinc-900"
                >
            @else
                <x-saas.avatar :user="auth()->user()" size="xl" ring />
            @endif

            <div class="min-w-0 flex-1">
                <label class="mb-2 block text-sm font-medium">عکس پروفایل</label>
                <input wire:model="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="saas-input text-sm">
                <p class="mt-1 text-xs text-zinc-500">فرمت‌های مجاز: JPG، PNG، WebP — حداکثر ۲ مگابایت</p>
                <div wire:loading wire:target="avatar" class="mt-1 text-xs text-amber-600">در حال بارگذاری عکس…</div>
                @error('avatar') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($portal === 'employer')
            <div>
                <label class="mb-2 block text-sm font-medium">نام</label>
                <input wire:model="name" class="saas-input" required>
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium">نام سازمان</label>
                <input wire:model="organization_title" class="saas-input" required>
                @error('organization_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium">نام</label>
                    <input wire:model="first_name" class="saas-input" required>
                    @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">نام خانوادگی</label>
                    <input wire:model="last_name" class="saas-input" required>
                    @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium">موبایل</label>
                    <input wire:model="mobile" class="saas-input">
                    @error('mobile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium">بخش</label>
                    <input wire:model="department" class="saas-input">
                    @error('department') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium">سمت</label>
                <input wire:model="position" class="saas-input">
                @error('position') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label class="mb-2 block text-sm font-medium">ایمیل</label>
            <input wire:model="email" type="email" class="saas-input" required>
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium">رمز عبور جدید</label>
            <input wire:model="password" type="password" class="saas-input" autocomplete="new-password">
            <p class="mt-1 text-xs text-zinc-500">برای حفظ رمز فعلی خالی بگذارید.</p>
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="saas-btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">ذخیره تغییرات</span>
            <span wire:loading wire:target="save">در حال ذخیره…</span>
        </button>
    </form>
</div>
