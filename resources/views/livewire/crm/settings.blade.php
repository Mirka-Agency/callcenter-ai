<div class="saas-page space-y-6">
    <nav class="text-sm text-zinc-500" aria-label="مسیر">
        <span>CRM</span>
        <span class="mx-2">/</span>
        <span class="text-zinc-800 dark:text-zinc-200">تنظیمات</span>
    </nav>

    <x-saas.page-header title="تنظیمات CRM" description="این شرکت از CRM آوایار استفاده می‌کند. تغییر به CRM خارجی فقط از پنل ادمین انجام می‌شود." />

    <section class="saas-card space-y-3 p-4">
        <h2 class="text-lg font-semibold">ساخت معامله از تحلیل تماس</h2>
        <p class="text-sm leading-7 text-zinc-600">
            ساخت خودکار معامله خاموش است. پس از تحلیل، اگر برای همان شخص فقط یک معامله باز وجود داشته باشد تماس به آن وصل می‌شود.
            اگر چند معامله باز باشد یا هیچ معامله‌ای نباشد، فقط یک پیشنهاد برای بررسی کارشناس ساخته می‌شود.
            امتیاز لید، مبلغ و مرحله فروش را عوض نمی‌کند.
        </p>
        <p class="text-sm font-medium {{ $autoCreate ? 'text-amber-700' : 'text-emerald-700' }}">
            وضعیت فعلی: {{ $autoCreate ? 'ساخت خودکار روشن' : 'ساخت خودکار خاموش' }}
        </p>
    </section>

    <section class="saas-card p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">پایپ‌لاین‌ها</h2>
            <a href="{{ route('employer.crm.pipelines.index') }}" class="saas-btn-secondary text-sm" wire:navigate>مدیریت پایپ‌لاین</a>
        </div>
        <ul class="mt-4 space-y-2 text-sm">
            @forelse ($pipelines as $pipeline)
                <li class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-800">
                    <span>{{ $pipeline->name }} · {{ $pipeline->stages_count }} مرحله</span>
                    <span class="text-zinc-500">{{ $pipeline->is_default ? 'پیش‌فرض' : ($pipeline->is_active ? 'فعال' : 'غیرفعال') }}</span>
                </li>
            @empty
                <li class="text-zinc-500">هنوز پایپ‌لاینی ثبت نشده است.</li>
            @endforelse
        </ul>
    </section>

</div>
