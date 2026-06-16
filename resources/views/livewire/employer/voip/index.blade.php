<div class="space-y-8">
    <div data-tour="voip-header">
        <h1 class="text-3xl font-semibold tracking-tight">VoIP</h1>
        <p class="mt-2 text-zinc-500">سیستم تلفن خود را متصل کنید تا تماس‌های ورودی و شناسه تماس‌گیرنده را در فضای کاری کارشناس دریافت کنید.</p>
    </div>

    @unless ($isComplete)
        @include('livewire.shared.integration-setup-pending', [
            'title' => 'سیستم در حال راه‌اندازی است',
            'description' => 'تنظیمات VoIP هنوز کامل نشده‌اند. پس از اتصال و تأیید سرویس، جزئیات تماس و وب‌هوک اینجا نمایش داده می‌شود.',
        ])
    @else
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100" data-tour="voip-guide">
            <p class="font-medium">راهنمای اتصال VoIP</p>
            <p class="mt-1 text-emerald-800/90 dark:text-emerald-200/90">
                وب‌هوک ارائه‌دهنده VoIP خود را به <code class="rounded bg-white/60 px-1 dark:bg-black/30">/webhooks/voip/{connection_id}</code> برای رویدادهای تماس هدایت کنید.
                برای پاپ‌آپ فوری کارشناس، تماس‌های ورودی را نیز به این آدرس ارسال کنید:
            </p>
            <p class="mt-2 break-all font-mono text-xs text-emerald-700 dark:text-emerald-300">POST {{ $incomingCallEndpoint }}</p>
            <p class="mt-2 text-xs text-emerald-700 dark:text-emerald-300">
                شامل <code class="rounded bg-white/60 px-1 dark:bg-black/30">organization_id</code>، <code class="rounded bg-white/60 px-1 dark:bg-black/30">caller_number</code> و در صورت امکان <code class="rounded bg-white/60 px-1 dark:bg-black/30">customer_name</code> برای شناسه تماس‌گیرنده.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3" data-tour="voip-stats">
            <x-saas.stat-card label="تماس‌های امروز" :value="$todayCalls" />
            <x-saas.stat-card label="این ماه" :value="$monthCalls" />
            <x-saas.stat-card label="اتصالات" :value="$connections->where('is_active', true)->count()" />
        </div>

        <div class="grid gap-4 md:grid-cols-2" data-tour="voip-connections">
            @foreach ($connections as $connection)
                <div class="saas-card">
                    <h3 class="font-semibold">{{ $connection->name }}</h3>
                    <p class="text-sm text-zinc-500">{{ $connection->provider->name }}</p>
                    <p class="mt-2 break-all text-xs text-zinc-400">
                        وب‌هوک: {{ url('/webhooks/voip/'.$connection->id) }}
                    </p>
                </div>
            @endforeach
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">تماس‌های اخیر</h2>
            <p class="mt-1 text-sm text-zinc-500">شناسه تماس‌گیرنده در ستون «از» برای تماس‌های ورودی نمایش داده می‌شود.</p>
            <table class="saas-table mt-4">
                <thead><tr><th>جهت</th><th>شناسه تماس‌گیرنده (از)</th><th>به</th><th>شروع</th></tr></thead>
                <tbody>
                    @forelse ($recentCalls as $call)
                        <tr>
                            <td>{{ $call->direction?->label() ?? '—' }}</td>
                            <td>{{ $call->source_number ?: '—' }}</td>
                            <td>{{ $call->destination_number }}</td>
                            <td>{{ shamsi($call->started_at, 'datetime') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-zinc-500">هنوز تماسی ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endunless
</div>
