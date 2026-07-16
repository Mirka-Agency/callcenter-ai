<div class="space-y-8">
    <div data-tour="voip-header">
        <h1 class="text-3xl font-semibold tracking-tight">VoIP</h1>
        <p class="mt-2 text-zinc-500">سیستم تلفن خود را متصل کنید تا تماس‌های ورودی و شناسه تماس‌گیرنده در فضای کاری کارشناس نمایش داده شود.</p>
    </div>

    @unless ($isComplete)
        @include('livewire.shared.integration-setup-pending', [
            'title' => 'اتصال VoIP در حال راه‌اندازی است',
            'description' => 'تنظیمات تلفن سازمانی هنوز کامل نشده. پس از اتصال و تأیید سرویس، جزئیات تماس و وب‌هوک اینجا نمایش داده می‌شود.',
        ])
    @else
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100" data-tour="voip-guide">
            <p class="font-medium">{{ __('ui.voip.webhook_guide_title') }}</p>
            <p class="mt-1 text-emerald-800/90 dark:text-emerald-200/90">
                {{ __('ui.voip.webhook_guide_body') }}
            </p>
            <p class="mt-2 text-xs text-emerald-700 dark:text-emerald-300">
                {{ __('ui.voip.webhook_method') }}
                — {{ __('ui.voip.webhook_secret_hint') }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3" data-tour="voip-stats">
            <x-saas.stat-card label="تماس‌های امروز" :value="$todayCalls" />
            <x-saas.stat-card label="این ماه" :value="$monthCalls" />
            <x-saas.stat-card label="اتصالات" :value="$connections->where('is_active', true)->count()" />
        </div>

        <div class="grid gap-4 md:grid-cols-2" data-tour="voip-connections">
            @foreach ($connections as $connection)
                <div class="saas-card space-y-3">
                    <div>
                        <h3 class="font-semibold">{{ $connection->name }}</h3>
                        <p class="text-sm text-zinc-500">{{ $connection->provider->name }}</p>
                    </div>

                    <x-saas.webhook-url :url="$connection->inbound_webhook_url" />

                    <button
                        type="button"
                        wire:click="regenerateWebhookToken({{ $connection->id }})"
                        wire:confirm="{{ __('ui.voip.webhook_regenerate_confirm') }}"
                        wire:loading.attr="disabled"
                        class="saas-btn-secondary text-sm text-amber-700 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300"
                    >
                        <span wire:loading.remove wire:target="regenerateWebhookToken({{ $connection->id }})">
                            {{ __('ui.voip.webhook_regenerate_button') }}
                        </span>
                        <span wire:loading wire:target="regenerateWebhookToken({{ $connection->id }})">
                            {{ __('ui.voip.webhook_regenerating') }}
                        </span>
                    </button>

                    @if ($connection->provider->code === 'custom')
                        <details class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-xs dark:border-zinc-700 dark:bg-zinc-900/50">
                            <summary class="cursor-pointer font-medium text-zinc-700 dark:text-zinc-200">
                                {{ __('ui.voip.custom_payload_title') }}
                            </summary>
                            <p class="mt-2 text-zinc-500">{{ __('ui.voip.custom_payload_hint') }}</p>
                            <pre class="mt-2 overflow-x-auto rounded bg-white p-2 font-mono text-[11px] text-zinc-700 dark:bg-zinc-950 dark:text-zinc-200" dir="ltr">{{ json_encode(\App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleWebhookPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">{{ __('ui.voip.incoming_call_title') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('ui.voip.incoming_call_body') }}</p>
            <p class="mt-2 break-all font-mono text-xs text-zinc-600 dark:text-zinc-300" dir="ltr">POST {{ $incomingCallEndpoint }}</p>
            <p class="mt-2 text-xs text-zinc-500">
                شامل <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">organization_id</code>،
                <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">caller_number</code>
                و در صورت امکان <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">customer_name</code>.
            </p>
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
                        <tr><td colspan="4" class="text-center text-zinc-500">هنوز تماسی از طریق VoIP ثبت نشده — پس از اتصال، تماس‌های ورودی اینجا دیده می‌شوند.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endunless
</div>
