<div class="space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ $connection ? 'ویرایش اتصال CRM' : 'افزودن اتصال CRM' }}</h1>
        </div>
        <a href="{{ route('employer.crm.connections.index') }}" class="saas-btn-secondary">بازگشت</a>
    </div>

    <form wire:submit="save" class="saas-card max-w-3xl space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">ارائه‌دهنده</label>
                <select wire:model="crm_provider_id" class="saas-input" required>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">نام اتصال</label>
                <input wire:model="name" class="saas-input" required>
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_default"> پیش‌فرض</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active"> فعال</label>
        </div>

        <div class="border-t border-zinc-200 pt-6 dark:border-zinc-800">
            <h2 class="mb-4 font-semibold">اعتبارنامه API</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">آدرس API</label>
                    <input wire:model="api_url" class="saas-input" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">کلید API</label>
                    <input wire:model="api_key" type="password" class="saas-input" placeholder="{{ $connection ? 'خالی = بدون تغییر' : '' }}">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">توکن API</label>
                    <input wire:model="api_token" type="password" class="saas-input" placeholder="{{ $connection ? 'خالی = بدون تغییر' : '' }}">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">نام کاربری</label>
                    <input wire:model="username" class="saas-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">رمز عبور</label>
                    <input wire:model="password" type="password" class="saas-input" placeholder="{{ $connection ? 'خالی = بدون تغییر' : '' }}">
                </div>
            </div>
        </div>

        <div class="border-t border-zinc-200 pt-6 dark:border-zinc-800">
            <h2 class="mb-4 font-semibold">تنظیمات</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">Webhook URL</label>
                    <input wire:model="webhook_url" class="saas-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Webhook Secret</label>
                    <input wire:model="webhook_secret" type="password" class="saas-input" placeholder="{{ $connection ? 'خالی = بدون تغییر' : '' }}">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Timeout (ثانیه)</label>
                    <input wire:model="timeout" type="number" class="saas-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Pipeline ID</label>
                    <input wire:model="pipeline_id" class="saas-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Pipeline Stage ID</label>
                    <input wire:model="pipeline_stage_id" class="saas-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Deal Owner ID</label>
                    <input wire:model="deal_owner_id" class="saas-input">
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="saas-btn-primary">ذخیره</button>
            @if ($connection)
                <button type="button" wire:click="test" class="saas-btn-secondary">تست اتصال</button>
                <button type="button" wire:click="sync" class="saas-btn-secondary">همگام‌سازی</button>
            @endif
        </div>
    </form>
</div>
