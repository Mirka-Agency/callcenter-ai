<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ $employee ? 'ویرایش کارشناس' : 'افزودن کارشناس' }}</h1>
            <p class="mt-2 text-zinc-500">{{ $employee ? 'به‌روزرسانی اطلاعات و دسترسی کارشناس.' : 'کارشناس جدید را به تیم تماس اضافه کنید.' }}</p>
        </div>
        <a href="{{ route('employer.employees.index') }}" class="saas-btn-secondary">بازگشت</a>
    </div>

    <form wire:submit="save" class="saas-card max-w-2xl space-y-5" data-tour="employee-form">
        <div class="flex flex-col gap-4 border-b border-zinc-200/80 pb-5 dark:border-zinc-800 sm:flex-row sm:items-center">
            @if ($avatar)
                <img
                    src="{{ $avatar->temporaryUrl() }}"
                    alt=""
                    class="h-20 w-20 shrink-0 rounded-lg object-cover ring-2 ring-white dark:ring-zinc-900"
                >
            @elseif ($employee)
                <x-saas.avatar :employee="$employee" size="xl" ring />
            @else
                <x-saas.avatar :name="trim($first_name.' '.$last_name) ?: '?'" size="xl" ring />
            @endif

            <div class="min-w-0 flex-1">
                <label class="mb-2 block text-sm font-medium">عکس کارشناس</label>
                <input wire:model="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="saas-input text-sm">
                <p class="mt-1 text-xs text-zinc-500">فرمت‌های مجاز: JPG، PNG، WebP — حداکثر ۲ مگابایت</p>
                <div wire:loading wire:target="avatar" class="mt-1 text-xs text-amber-600">در حال بارگذاری عکس…</div>
                @error('avatar') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-medium">نام</label>
                <input wire:model="first_name" class="saas-input" required>
                @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">نام خانوادگی</label>
                <input wire:model="last_name" class="saas-input" required>
            </div>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium">ایمیل</label>
            <input wire:model="email" type="email" class="saas-input" required>
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium">رمز عبور</label>
            <input wire:model="password" type="password" class="saas-input" @if(! $employee) required @endif>
            @if ($employee) <p class="mt-1 text-xs text-zinc-500">برای حفظ رمز فعلی خالی بگذارید.</p> @endif
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-medium">موبایل</label>
                <input wire:model="mobile" class="saas-input">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">بخش</label>
                <input wire:model="department" class="saas-input">
            </div>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium">سمت</label>
            <input wire:model="position" class="saas-input">
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input wire:model="is_active" type="checkbox" class="rounded border-zinc-300">
            کارشناس فعال
        </label>

        @include('livewire.employer.employees.partials.integration-assignments')

        <button type="submit" class="saas-btn-primary" wire:loading.attr="disabled">ذخیره کارشناس</button>
    </form>
</div>
