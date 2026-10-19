@php
    use App\Support\PersianNumber;
@endphp

<div class="saas-page space-y-6">
    <nav class="text-sm text-zinc-500" aria-label="مسیر">
        <span>CRM</span>
        <span class="mx-2">/</span>
        <span class="text-zinc-800 dark:text-zinc-200">تنظیمات</span>
    </nav>

    <x-saas.page-header
        title="تنظیمات مشتریان و پیگیری"
        description="کاریز خلاصه تماس، مشخصات فروشنده، مالیات، مهر و امضا."
    />

    <section class="saas-card space-y-3 p-4">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-white">ساخت معامله از تحلیل تماس</h2>
        <p class="text-sm leading-7 text-zinc-600 dark:text-zinc-400">
            ساخت خودکار معامله خاموش است. پس از تحلیل، اگر برای همان شخص فقط یک معامله باز وجود داشته باشد تماس به آن وصل می‌شود.
            اگر چند معامله باز باشد یا هیچ معامله‌ای نباشد، فقط یک پیشنهاد برای بررسی کارشناس ساخته می‌شود.
        </p>
        <p class="text-sm font-medium {{ $autoCreate ? 'text-amber-700' : 'text-emerald-700' }}">
            وضعیت فعلی: {{ $autoCreate ? 'ساخت خودکار روشن' : 'ساخت خودکار خاموش' }}
        </p>
    </section>

    <section class="saas-card space-y-3 p-5">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-white">خلاصه تماس ← CRM</h2>
        <label class="block space-y-1.5">
            <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">کاریز ثبت خلاصه تماس</span>
            <select wire:model="callSummaryPipelineId" class="saas-input">
                <option value="">— انتخاب نشده —</option>
                @foreach ($pipelines as $pipeline)
                    <option value="{{ $pipeline->id }}">
                        {{ $pipeline->name }}{{ $pipeline->is_default ? ' (پیش‌فرض)' : '' }}
                    </option>
                @endforeach
            </select>
        </label>
        <p class="text-sm leading-6 text-zinc-500">
            پس از هر تماس، اگر این کاریز انتخاب شده باشد مخاطب و فرصت در همین کاریز ساخته یا به‌روز می‌شود. اگر خالی بماند، هیچ چیزی از خلاصه تماس به CRM اضافه نمی‌شود.
        </p>
        @error('callSummaryPipelineId') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
    </section>

    <section class="saas-card space-y-2 p-5">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-white">دسترسی AI به کاتالوگ</h2>
        <label class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" wire:model="aiCatalogSearchEnabled" class="mt-1 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
            <span>
                <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">اجازه جستجوی محصولات و خدمات توسط AI در تماس</span>
                <span class="mt-1 block text-sm leading-6 text-zinc-500">
                    به‌صورت پیش‌فرض روشن است. اگر این گزینه فعال باشد، دستیار می‌تواند نام، قیمت و مشخصات محصولات/خدمات کاتالوگ را در تماس جستجو کند.
                </span>
            </span>
        </label>
    </section>

    <section class="saas-card space-y-2 p-5">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-white">تأیید هویت در تماس</h2>
        <label class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" wire:model="requireNationalIdForNewNumbers" class="mt-1 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
            <span>
                <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">برای شمارهٔ جدید، کد ملی لازم باشد</span>
                <span class="mt-1 block text-sm leading-6 text-zinc-500">
                    پیش‌فرض خاموش است. تماسی که از شماره ذخیره‌شده روی مخاطب می‌آید بدون تأیید کد ملی به‌روز می‌شود. اگر این گزینه روشن باشد، فقط شماره‌ای که در پرونده نیست باید کد ملی را بگوید تا به همان شخص وصل شود.
                </span>
            </span>
        </label>
    </section>

    <section class="saas-card space-y-2 p-5">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-white">مسئول پیش‌فرض شخص</h2>
        <label class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" wire:model="assignFromCall" class="mt-1 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
            <span>
                <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">کارشناس تماس، مسئول شخص تازه باشد</span>
                <span class="mt-1 block text-sm leading-6 text-zinc-500">
                    اگر شخص از تماس تازه ساخته شود و کارشناس همان تماس عضو فعال سازمان باشد، او مسئول پیش‌فرض شود. مشتریان قبلی تغییر نمی‌کنند.
                </span>
            </span>
        </label>
    </section>

    <section class="saas-card space-y-2 p-5">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-white">پیش‌فاکتور توسط AI</h2>
        <label class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" wire:model="aiProformaEnabled" class="mt-1 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
            <span>
                <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">ارسال پیش‌فاکتور توسط دستیار AI</span>
                <span class="mt-1 block text-sm leading-6 text-zinc-500">
                    اگر فعال باشد، دستیار روی تماس می‌تواند پیش‌فاکتور CRM را صادر و برای تماس‌گیرنده پیامک کند. پیامک از سهمیه پیامک سازمان مصرف می‌شود.
                </span>
            </span>
        </label>
    </section>

    <form wire:submit="saveSalesFollowUp" class="saas-card space-y-5 p-5">
        <div>
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">پیگیری فروش پس از تماس</h2>
            <p class="mt-2 text-sm leading-7 text-zinc-500">
                برای فروش: بعد از تماس فقط وقتی لازم باشد یادآور می‌سازد (پیش‌فرض خاموش و کم‌تعداد). اگر مشتری opt-out کند، نوبت/سفارش بگیرد، callback باز داشته باشد، یا همین الان یک پیگیری باز داشته باشد، دوباره ساخته نمی‌شود تا اسپم نشود.
            </p>
        </div>

        <label class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" wire:model="salesFollowUpEnabled" class="mt-1 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
            <span>
                <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">فعال‌سازی پیگیری فروش</span>
                <span class="mt-1 block text-sm leading-6 text-zinc-500">
                    با خاموش کردن، همه یادآورهای باز پیگیری فروش حذف می‌شوند. بستن فرصت (موفق/ناموفق) هم آن‌ها را پاک می‌کند.
                </span>
            </span>
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">روزهای پیش‌فرض (با کاما)</span>
                <input wire:model="salesFollowUpDefaultDays" class="saas-input" dir="ltr" placeholder="3, 10">
                @error('salesFollowUpDefaultDays') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">سقف تعداد — {{ $maxCountLabel }}</span>
                <input wire:model="salesFollowUpMaxCount" type="number" min="1" max="20" class="saas-input" dir="ltr">
                @error('salesFollowUpMaxCount') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">حداقل فاصله روز — {{ $minIntervalLabel }}</span>
                <input wire:model="salesFollowUpMinDayInterval" type="number" min="1" max="90" class="saas-input" dir="ltr">
                @error('salesFollowUpMinDayInterval') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">زودترین پیگیری (روز) — {{ $earliestLabel }}</span>
                <input wire:model="salesFollowUpEarliestDays" type="number" min="0" max="90" class="saas-input" dir="ltr">
                @error('salesFollowUpEarliestDays') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
        </div>

        <div class="grid gap-4 border-t border-zinc-200/80 pt-5 dark:border-zinc-800 sm:grid-cols-2">
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">فاصله قبل از برنامه‌ریزی دوباره — {{ $replanLabel }} روز</span>
                <input wire:model="salesFollowUpReplanIntervalDays" type="number" min="1" max="365" class="saas-input" dir="ltr">
                @error('salesFollowUpReplanIntervalDays') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="flex cursor-pointer items-start gap-3 sm:pt-7">
                <input type="checkbox" wire:model="salesFollowUpAiCanChangeDays" class="mt-1 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">AI بتواند روزها را عوض کند</span>
                    <span class="mt-1 block text-sm leading-6 text-zinc-500">
                        خاموش بهتر است؛ فقط تصمیم «لازم است / نیست» بماند.
                    </span>
                </span>
            </label>
        </div>

        <div class="flex justify-end border-t border-zinc-200/80 pt-5 dark:border-zinc-800">
            <button type="submit" class="saas-btn-primary" wire:loading.attr="disabled">ذخیره پیگیری فروش</button>
        </div>
    </form>

    <form wire:submit="saveSettings" class="saas-card space-y-5 p-5">
        <div>
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">هویت شرکت</h2>
            <p class="mt-1 text-sm text-zinc-500">مشخصات فروشنده برای فاکتور، پیش‌فاکتور، مهر و امضا.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">نام حقوقی</span>
                <input wire:model="legalName" class="saas-input">
                @error('legalName') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">نام تجاری</span>
                <input wire:model="tradeName" class="saas-input">
                @error('tradeName') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">شناسه ملی</span>
                <input wire:model="nationalId" class="saas-input" dir="ltr">
                @error('nationalId') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">کد اقتصادی</span>
                <input wire:model="economicCode" class="saas-input" dir="ltr">
                @error('economicCode') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">شماره ثبت</span>
                <input wire:model="registrationNumber" class="saas-input" dir="ltr">
                @error('registrationNumber') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">کد پستی</span>
                <input wire:model="postalCode" class="saas-input" dir="ltr">
                @error('postalCode') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">تلفن</span>
                <input wire:model="companyPhone" class="saas-input" dir="ltr">
                @error('companyPhone') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block space-y-1.5">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">ایمیل</span>
                <input wire:model="companyEmail" type="email" class="saas-input" dir="ltr">
                @error('companyEmail') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block space-y-1.5 sm:col-span-2">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">آدرس</span>
                <input wire:model="companyAddress" class="saas-input">
                @error('companyAddress') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
            <label class="block space-y-1.5 sm:max-w-xs">
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">مالیات پیش‌فرض (%) — {{ $taxLabel }}</span>
                <input wire:model="defaultTaxPercent" type="number" min="0" max="100" step="0.01" class="saas-input" dir="ltr">
                @error('defaultTaxPercent') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
            </label>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200/80 pt-5 dark:border-zinc-800">
            <p class="text-xs text-zinc-500">
                {{ PersianNumber::format($pipelines->count()) ?? $pipelines->count() }} کاریز · ذخیره شامل کاریز خلاصه تماس، مسئول پیش‌فرض شخص و گزینه‌های AI هم می‌شود.
            </p>
            <button type="submit" class="saas-btn-primary" wire:loading.attr="disabled">ذخیره تنظیمات</button>
        </div>
    </form>
</div>
