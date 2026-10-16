<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">راه‌های تماس</h2>
    </div>

    @if (session('contact_method_status'))
        <p class="text-sm text-emerald-700" role="status">{{ session('contact_method_status') }}</p>
    @endif

    @error('value')
        <p class="text-sm text-rose-700" role="alert">{{ $message }}</p>
    @enderror

    <ul class="space-y-2">
        @forelse ($methods as $method)
            <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-800" wire:key="method-{{ $method->id }}">
                <div>
                    <p class="font-medium" dir="ltr">{{ $method->raw_value }}</p>
                    <p class="text-xs text-zinc-500">
                        {{ $method->type === 'email' ? 'ایمیل' : ($method->type === 'mobile' ? 'موبایل' : 'تلفن') }}
                        @if ($method->is_primary)
                            <span class="ms-1 text-emerald-700">اصلی</span>
                        @endif
                        <span class="ms-1">{{ $method->verification_status === 'verified' ? 'تأییدشده' : 'تأیید نشده' }}</span>
                        @if ($method->label)
                            <span class="ms-1">{{ $method->label }}</span>
                        @endif
                    </p>
                </div>
                <div class="flex gap-2">
                    @unless ($method->is_primary)
                        <button type="button" class="text-xs font-medium text-indigo-700" wire:click="makePrimary({{ $method->id }})">اصلی شود</button>
                    @endunless
                    @unless ($method->is_primary && $method->isPhone())
                        <button type="button" class="text-xs font-medium text-rose-700" wire:click="deactivate({{ $method->id }})">غیرفعال</button>
                    @endunless
                </div>
            </li>
        @empty
            <li class="text-sm text-zinc-500">هنوز روش تماسی ثبت نشده است.</li>
        @endforelse
    </ul>

    <form wire:submit="add" class="space-y-2">
        <label class="block text-sm text-zinc-600" for="contact-method-value">شماره یا ایمیل جدید</label>
        <input id="contact-method-value" wire:model="value" type="text" class="saas-input w-full" dir="ltr" autocomplete="off">
        <input wire:model="label" type="text" class="saas-input w-full" placeholder="برچسب، مثلاً موبایل شخصی" maxlength="100">
        <button type="submit" class="saas-btn-primary text-sm">افزودن</button>
    </form>
</div>
