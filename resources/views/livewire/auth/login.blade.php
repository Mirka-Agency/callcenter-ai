<div>
    <div class="saas-card text-center">
        <p class="mb-2 text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ config('app.name') }}</p>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">ورود به حساب</h1>
        <p class="mt-2 text-sm text-zinc-500">برای دسترسی به داشبورد تحلیل تماس، وارد شوید.</p>
    </div>

    <form wire:submit="authenticate" class="saas-card mt-6 space-y-5">
        <div>
            <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">ایمیل یا شماره موبایل</label>
            <input wire:model="identifier" type="text" class="saas-input" autocomplete="username" required>
            @error('identifier') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">رمز عبور</label>
            <input wire:model="password" type="password" class="saas-input" autocomplete="current-password" required>
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="saas-btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="authenticate">ورود به داشبورد</span>
            <span wire:loading wire:target="authenticate">در حال ورود...</span>
        </button>
    </form>
</div>
