<div class="saas-page space-y-6">
    <x-saas.page-header
        data-tour="page-header"
        title="پروفایل سازمان"
        description="اطلاعات سازمان، اتصال ویپ و CRM فقط برای مشاهده است. روزهای تعطیل شرکت را از همین صفحه تنظیم کنید."
    />

    <section class="saas-card max-w-3xl" data-tour="organization-details">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-white">اطلاعات سازمان</h2>
        <p class="mt-1 text-sm text-zinc-500">این اطلاعات از طرف مدیریت سیستم ثبت شده و در پنل کارفرما قابل تغییر نیست.</p>

        <dl class="mt-5 divide-y divide-zinc-200/80 dark:divide-zinc-800">
            <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-zinc-500">نام سازمان</dt>
                <dd class="text-sm font-medium text-zinc-900 dark:text-white sm:col-span-2">{{ $organizationTitle }}</dd>
            </div>
            <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-zinc-500">نام ارائه‌دهنده ویپ</dt>
                <dd class="text-sm font-medium text-zinc-900 dark:text-white sm:col-span-2">{{ $voipProviderName ?? 'تعریف نشده' }}</dd>
            </div>
            <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                <dt class="text-sm text-zinc-500">اتصال پیش‌فرض</dt>
                <dd class="text-sm font-medium text-zinc-900 dark:text-white sm:col-span-2">{{ $defaultConnectionName ?? 'تعریف نشده' }}</dd>
            </div>
            <div class="py-3">
                @if ($webhookUrl)
                    <x-saas.webhook-url :url="$webhookUrl" label="آدرس وب‌هوک ارائه‌دهنده ویپ" :show-method="false" />
                @else
                    <div class="grid gap-1 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-sm text-zinc-500">آدرس وب‌هوک ارائه‌دهنده ویپ</dt>
                        <dd class="text-sm font-medium text-zinc-900 dark:text-white sm:col-span-2">تعریف نشده</dd>
                    </div>
                @endif
            </div>
        </dl>
    </section>

    @if ($crmConnections !== [])
        <section class="saas-card max-w-3xl space-y-4" data-tour="organization-crm">
            <div>
                <h2 class="text-base font-semibold text-zinc-900 dark:text-white">اطلاعات CRM</h2>
                <p class="mt-1 text-sm text-zinc-500">اتصال‌های CRM تعریف‌شده برای این سازمان. این اطلاعات قابل تغییر نیست.</p>
            </div>

            @foreach ($crmConnections as $connection)
                <dl class="divide-y divide-zinc-200/80 rounded-md border border-zinc-200/80 px-4 dark:divide-zinc-800 dark:border-zinc-800">
                    <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-sm text-zinc-500">نام اتصال</dt>
                        <dd class="text-sm font-medium text-zinc-900 dark:text-white sm:col-span-2">{{ $connection['name'] }}</dd>
                    </div>
                    <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-sm text-zinc-500">ارائه‌دهنده</dt>
                        <dd class="text-sm font-medium text-zinc-900 dark:text-white sm:col-span-2">{{ $connection['provider'] }}</dd>
                    </div>
                    <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-sm text-zinc-500">وضعیت</dt>
                        <dd class="text-sm font-medium text-zinc-900 dark:text-white sm:col-span-2">{{ $connection['is_active'] ? 'فعال' : 'غیرفعال' }}</dd>
                    </div>
                    <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="text-sm text-zinc-500">اتصال پیش‌فرض</dt>
                        <dd class="text-sm font-medium text-zinc-900 dark:text-white sm:col-span-2">{{ $connection['is_default'] ? 'بله' : 'خیر' }}</dd>
                    </div>
                </dl>
            @endforeach
        </section>
    @endif

    <form wire:submit="save" class="saas-card max-w-3xl space-y-5" data-tour="organization-holidays">
        <div>
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">تعطیلات شرکت</h2>
            <p class="mt-1 text-sm text-zinc-500">مشخص کنید کدام روزهای هفته شرکت تعطیل است. تماس‌های این روزها در نمودارها و آمار عملکرد محاسبه نمی‌شوند. اگر پنجشنبه روز کاری شماست، آن را از تعطیلات خارج کنید.</p>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">روزهای تعطیل هفته</h3>
            <p class="mt-1 text-sm text-zinc-500">روز انتخاب‌شده برای این شرکت تعطیل است. جمعه معمولاً تعطیل است؛ پنجشنبه را فقط وقتی انتخاب کنید که شرکت آن روز کار نمی‌کند.</p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($weekdayOptions as $weekday => $label)
                @php $checked = in_array($weekday, $selectedWeekdays, true); @endphp
                <label @class([
                    'flex cursor-pointer items-center justify-between gap-3 rounded-md border px-4 py-3 text-sm font-medium transition',
                    'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $checked,
                    'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' => ! $checked,
                ])>
                    <span>{{ $label }}</span>
                    <span class="flex items-center gap-2 text-xs font-normal {{ $checked ? 'text-zinc-300 dark:text-zinc-600' : 'text-zinc-400' }}">
                        {{ $checked ? 'تعطیل' : 'کاری' }}
                        <input
                            type="checkbox"
                            value="{{ $weekday }}"
                            wire:model.live="holidayWeekdays"
                            @checked($checked)
                            class="rounded border-zinc-300"
                        >
                    </span>
                </label>
            @endforeach
        </div>

        @error('holidayWeekdays') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @error('holidayWeekdays.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <p class="text-sm text-zinc-500">
            @if ($holidayLabels === [])
                هیچ روزی تعطیل نیست و تماس‌های هر هفت روز هفته در آمار می‌آید.
            @else
                تعطیل: {{ implode('، ', $holidayLabels) }}.
                روز کاری: {{ $workdayLabels === [] ? 'ندارد' : implode('، ', $workdayLabels) }}.
            @endif
        </p>

        <div class="flex justify-end border-t border-zinc-200/80 pt-5 dark:border-zinc-800">
            <button type="submit" class="saas-btn-primary">ذخیره تعطیلات</button>
        </div>
    </form>
</div>
