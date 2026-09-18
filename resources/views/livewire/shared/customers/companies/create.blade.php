<div class="saas-page space-y-6">
    <x-saas.page-header
        title="شرکت جدید"
        description="ثبت شرکت مشتری — بعداً می‌توانید اشخاص را به آن متصل کنید."
    >
        <x-slot:actions>
            <a href="{{ $backRoute }}" class="saas-btn-secondary text-sm" wire:navigate>انصراف</a>
        </x-slot:actions>
    </x-saas.page-header>

    <form wire:submit="save" class="saas-card max-w-3xl space-y-5">
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-2 block text-sm font-medium">نام شرکت *</label>
                <input wire:model="name" class="saas-input" required autofocus>
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">صنعت</label>
                <input wire:model="industry" class="saas-input">
                @error('industry') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">وب‌سایت</label>
                <input wire:model="website" class="saas-input" dir="ltr">
                @error('website') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">تلفن</label>
                <input wire:model="phone" class="saas-input" dir="ltr">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">ایمیل</label>
                <input wire:model="email" type="email" class="saas-input" dir="ltr">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-2 block text-sm font-medium">آدرس</label>
                <textarea wire:model="address" rows="2" class="saas-input"></textarea>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-2 block text-sm font-medium">یادداشت</label>
                <textarea wire:model="notes" rows="3" class="saas-input"></textarea>
            </div>
        </div>

        <button type="submit" class="saas-btn-primary" wire:loading.attr="disabled">ایجاد شرکت</button>
    </form>
</div>
