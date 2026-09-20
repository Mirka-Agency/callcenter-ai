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
                <select wire:model.live="crm_provider_id" class="saas-input" required>
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

        @if ($isDynamics)
            <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-100 space-y-2">
                <p class="font-medium">{{ __('ui.crm.dynamics_guide_title') }}</p>
                <ol class="list-decimal list-inside space-y-1 text-sky-900/90 dark:text-sky-200/90">
                    <li>{{ __('ui.crm.dynamics_step_1') }}</li>
                    <li>{{ __('ui.crm.dynamics_step_2') }}</li>
                    <li>{{ __('ui.crm.dynamics_step_3') }}</li>
                    <li>{{ __('ui.crm.dynamics_step_4') }}</li>
                    <li>{{ __('ui.crm.dynamics_step_5') }}</li>
                </ol>
            </div>
        @endif

        <div class="border-t border-zinc-200 pt-6 dark:border-zinc-800">
            <h2 class="mb-4 font-semibold">{{ $isDynamics ? __('ui.crm.dynamics_credentials_title') : 'اعتبارنامه API' }}</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">{{ $isDynamics ? __('ui.crm.dynamics_environment_url') : 'آدرس API' }}</label>
                    <input wire:model="api_url" class="saas-input" required placeholder="{{ $isDynamics ? 'https://yourorg.crm.dynamics.com' : '' }}">
                    @if ($isDynamics)
                        <p class="mt-1 text-xs text-zinc-500">{{ __('ui.crm.dynamics_environment_url_hint') }}</p>
                    @endif
                    @error('api_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                @if ($isDynamics)
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">{{ __('ui.crm.dynamics_tenant_id') }}</label>
                        <input wire:model="tenant_id" class="saas-input" required>
                        <p class="mt-1 text-xs text-zinc-500">{{ __('ui.crm.dynamics_tenant_id_hint') }}</p>
                        @error('tenant_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ __('ui.crm.dynamics_client_id') }}</label>
                        <input wire:model="api_key" type="password" class="saas-input" placeholder="{{ $connection ? 'خالی = بدون تغییر' : '' }}">
                        @error('api_key') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ __('ui.crm.dynamics_client_secret') }}</label>
                        <input wire:model="api_token" type="password" class="saas-input" placeholder="{{ $connection ? 'خالی = بدون تغییر' : '' }}">
                        @error('api_token') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @else
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
                @endif
            </div>
        </div>

        <div class="border-t border-zinc-200 pt-6 dark:border-zinc-800">
            <h2 class="mb-4 font-semibold">تنظیمات</h2>
            <div class="grid gap-4 md:grid-cols-2">
                @unless ($isDynamics)
                    <div>
                        <label class="mb-1 block text-sm font-medium">Webhook URL</label>
                        <input wire:model="webhook_url" class="saas-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Webhook Secret</label>
                        <input wire:model="webhook_secret" type="password" class="saas-input" placeholder="{{ $connection ? 'خالی = بدون تغییر' : '' }}">
                    </div>
                @endunless
                <div>
                    <label class="mb-1 block text-sm font-medium">Timeout (ثانیه)</label>
                    <input wire:model="timeout" type="number" class="saas-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ $isDynamics ? __('ui.crm.dynamics_pipeline_id') : 'Pipeline ID' }}</label>
                    <input wire:model="pipeline_id" class="saas-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ $isDynamics ? __('ui.crm.dynamics_pipeline_stage_id') : 'Pipeline Stage ID' }}</label>
                    <input wire:model="pipeline_stage_id" class="saas-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ $isDynamics ? __('ui.crm.dynamics_owner_id') : 'Deal Owner ID' }}</label>
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
