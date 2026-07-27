<div class="space-y-8">
    <div data-tour="voip-header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">VoIP</h1>
                <p class="mt-2 text-zinc-500">سیستم تلفن خود را متصل کنید تا تماس‌های ورودی و شناسه تماس‌گیرنده در فضای کاری کارشناس نمایش داده شود.</p>
            </div>
            @if (\App\Services\EmployerIntegrationGate::allowsFullManagement())
                <a href="{{ route('employer.voip.connections.index') }}" class="saas-btn-primary">مدیریت اتصالات VoIP</a>
            @endif
        </div>
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

        <div class="grid gap-4 sm:grid-cols-4" data-tour="voip-stats">
            <x-saas.stat-card label="تماس‌های امروز" :value="$todayCalls" />
            <x-saas.stat-card label="این ماه" :value="$monthCalls" />
            <x-saas.stat-card label="از دست‌رفته" :value="$missedCalls" />
            <x-saas.stat-card label="اتصالات" :value="$connections->where('is_active', true)->count()" />
        </div>

        <div class="grid gap-4 md:grid-cols-2" data-tour="voip-connections">
            @foreach ($connections as $connection)
                <div class="saas-card space-y-3">
                    <div>
                        <h3 class="font-semibold">{{ $connection->name }}</h3>
                        <p class="text-sm text-zinc-500">{{ \App\Domain\Voip\Enums\VoipProviderCode::tryFrom($connection->provider->code)?->label() ?? $connection->provider->name }}</p>
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
                        @include('livewire.employer.voip.partials.asterisk-guide', [
                            'webhookUrl' => $connection->inbound_webhook_url,
                            'organizationId' => $connection->organization_id,
                            'webhookToken' => $connection->webhook_token,
                            'connectionId' => $connection->id,
                            'incomingCallUrl' => $incomingCallEndpoint,
                        ])
                    @endif
                </div>
            @endforeach
        </div>

        <div class="saas-card space-y-3">
            <h2 class="text-lg font-semibold">{{ __('ui.voip.incoming_call_title') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('ui.voip.incoming_call_body') }}</p>
            <x-saas.webhook-url :url="$incomingCallEndpoint" :label="__('ui.voip.asterisk_notify_url_label')" />
            <p class="text-xs text-zinc-500">
                {{ __('ui.voip.asterisk_notify_intro') }}
            </p>
            <p class="text-xs text-zinc-500">
                فیلدهای لازم:
                <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">organization_id</code>،
                <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">caller_number</code>
                و هدر
                <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">X-Voip-Webhook-Token</code>.
            </p>
        </div>

        <div class="saas-card">
            <h2 class="text-lg font-semibold">تماس‌های اخیر</h2>
            <p class="mt-1 text-sm text-zinc-500">
                {{ $recentCallsHint }}
            </p>
            <table class="saas-table mt-4">
                <thead><tr><th>جهت</th><th>شناسه تماس‌گیرنده (از)</th><th>به</th><th>وضعیت</th><th>شروع</th></tr></thead>
                <tbody>
                    @forelse ($recentCalls as $call)
                        <tr>
                            <td>{{ $call->direction?->label() ?? '—' }}</td>
                            <td>{{ $call->source_number ?: '—' }}</td>
                            <td>{{ $call->destination_number }}</td>
                            <td>
                                @if ($call->status)
                                    <span @class([
                                        'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                                        'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $call->status->value === 'missed' || $call->status->value === 'busy' || $call->status->value === 'failed',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' => $call->status->value === 'completed' || $call->status->value === 'answered',
                                        'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' => $call->status->value === 'ringing' || $call->status->value === 'initiated',
                                        'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' => ! in_array($call->status->value, ['missed', 'busy', 'failed', 'completed', 'answered', 'ringing', 'initiated'], true),
                                    ])>
                                        {{ $call->status->label() }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ shamsi($call->started_at, 'datetime') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-zinc-500">هنوز تماسی از طریق VoIP ثبت نشده — پس از اتصال، تماس‌های ورودی اینجا دیده می‌شوند.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endunless
</div>
