<div class="space-y-8">
    <div data-tour="crm-header">
        <h1 class="text-3xl font-semibold tracking-tight">یکپارچه‌سازی CRM</h1>
        <p class="mt-2 text-zinc-500">CRM خود را متصل کنید تا شناسه تماس‌گیرنده شناسایی شود و هوش مشتری در تماس‌های ورودی غنی‌تر گردد.</p>
    </div>

    @unless ($isComplete)
        @include('livewire.shared.integration-setup-pending', [
            'title' => 'سیستم در حال راه‌اندازی است',
            'description' => 'تنظیمات CRM هنوز کامل نشده‌اند. پس از اتصال و تأیید سرویس، جزئیات اتصال اینجا نمایش داده می‌شود.',
        ])
    @else
        <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-900/50 dark:bg-indigo-950/40 dark:text-indigo-100">
            <p class="font-medium">راهنمای اتصال CRM</p>
            <p class="mt-1 text-indigo-800/90 dark:text-indigo-200/90">
                با اتصال CRM، تماس‌های ورودی می‌توانند شماره تماس‌گیرنده را با نام مخاطب، تاریخچه و اقدامات پیگیری پیشنهادی مطابقت دهند.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2" data-tour="crm-connections">
            @foreach ($connections as $connection)
                <div class="saas-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold">{{ $connection->name }}</h3>
                            <p class="text-sm text-zinc-500">{{ $connection->provider->name }}</p>
                            <p class="mt-2 text-xs text-zinc-400">جستجوی شناسه تماس‌گیرنده در حالت فعال</p>
                        </div>
                        <span @class(['saas-badge-success' => $connection->is_active, 'saas-badge-danger' => ! $connection->is_active, 'saas-badge'])>
                            {{ $connection->is_active ? 'متصل' : 'غیرفعال' }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endunless
</div>
