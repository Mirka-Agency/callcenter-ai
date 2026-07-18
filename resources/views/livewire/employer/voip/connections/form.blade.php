<div class="space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ $connection ? 'ویرایش اتصال VoIP' : 'افزودن اتصال VoIP' }}</h1>
        </div>
        <a href="{{ route('employer.voip.connections.index') }}" class="saas-btn-secondary">بازگشت</a>
    </div>

    <form wire:submit="save" class="saas-card max-w-3xl space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">ارائه‌دهنده</label>
                <select wire:model.live="voip_provider_id" class="saas-input" required>
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

        @unless ($this->isCustomProvider)
            <div class="border-t border-zinc-200 pt-6 dark:border-zinc-800">
                <h2 class="mb-4 font-semibold">اعتبارنامه API</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">آدرس API</label>
                        <input wire:model="api_url" class="saas-input" placeholder="http://c53.hostedastel.ir/API/v4">
                        <p class="mt-1 text-xs text-zinc-500">برای سیموتل/Astel معمولاً آدرس سرور به‌همراه /API/v4 است.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">کلید API</label>
                        <input wire:model="api_key" type="password" class="saas-input" placeholder="{{ $connection ? 'خالی = بدون تغییر' : 'X-APIKEY از Astel' }}">
                        <p class="mt-1 text-xs text-zinc-500">همان X-APIKEY؛ یکی از کلید یا توکن کافی است.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">توکن API</label>
                        <input wire:model="api_token" type="password" class="saas-input" placeholder="{{ $connection ? 'خالی = بدون تغییر' : '' }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">نام کاربری</label>
                        <input wire:model="username" class="saas-input" placeholder="اختیاری — فقط اگر Basic Auth داده شده">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">رمز عبور</label>
                        <input wire:model="password" type="password" class="saas-input" placeholder="{{ $connection ? 'خالی = بدون تغییر' : '' }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Timeout (ثانیه)</label>
                        <input wire:model="timeout" type="number" class="saas-input">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">کانتکس سیموتل / Astel</label>
                        <input wire:model="simotel_context" class="saas-input font-mono text-sm" placeholder="مثلاً c2191093492" dir="ltr">
                        <p class="mt-1 text-xs text-zinc-500">شناسه tenant برای ردیابی؛ در درخواست API ارسال نمی‌شود.</p>
                    </div>
                </div>
            </div>
        @endunless

        <div class="border-t border-zinc-200 pt-6 dark:border-zinc-800 space-y-4">
            <h2 class="font-semibold">وب‌هوک</h2>

            @if ($connection)
                <x-saas.webhook-url :url="$connection->inbound_webhook_url" />
            @else
                <p class="text-sm text-zinc-500">پس از ذخیره، آدرس وب‌هوک اختصاصی اینجا نمایش داده می‌شود.</p>
            @endif

            <div>
                <label class="mb-1 block text-sm font-medium">کد امنیتی وب‌هوک (اختیاری — ۴۸ کاراکتر)</label>
                <input wire:model="webhook_token" class="saas-input font-mono text-sm" placeholder="{{ $connection ? 'خالی = بدون تغییر' : 'خالی = خودکار' }}">
                @error('webhook_token') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if ($this->isCustomProvider)
                <div>
                    <label class="mb-1 block text-sm font-medium">نگاشت فیلدهای وب‌هوک (JSON)</label>
                    <textarea wire:model="webhook_field_mapping_json" rows="4" class="saas-input font-mono text-xs" dir="ltr"></textarea>
                </div>
            @endif

            <div>
                <label class="mb-1 block text-sm font-medium">نگاشت داخلی (JSON)</label>
                <textarea wire:model="extension_mapping_json" rows="4" class="saas-input font-mono text-xs" dir="ltr" placeholder='{"982191093492":"101"}'></textarea>
                <p class="mt-1 text-xs text-zinc-500">کلید: DID یا شماره فیزیکی؛ مقدار: داخلی کارشناس (مثلاً 982191093492 → 101).</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">تنظیمات ضبط (JSON)</label>
                <textarea wire:model="recording_settings_json" rows="4" class="saas-input font-mono text-xs" dir="ltr"></textarea>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="saas-btn-primary">ذخیره</button>
            @if ($connection)
                <button type="button" wire:click="test" class="saas-btn-secondary">تست اتصال</button>
                <button type="button" wire:click="sync" class="saas-btn-secondary">همگام‌سازی داخلی‌ها</button>
                <button type="button" wire:click="regenerateToken" wire:confirm="{{ __('ui.voip.webhook_regenerate_confirm') }}" class="saas-btn-secondary">تغییر توکن وب‌هوک</button>
            @endif
        </div>
    </form>
</div>
