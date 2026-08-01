@props([
    'webhookUrl' => null,
    'organizationId' => null,
    'webhookToken' => null,
    'incomingCallUrl' => null,
    'connectionId' => null,
])

@php
    $incomingCallUrl ??= url('/api/voip/incoming-call');
    $hasWebhook = filled($webhookUrl);
    $hasNotify = $hasWebhook && filled($organizationId) && filled($webhookToken);
@endphp

<div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-100 space-y-4" data-tour="asterisk-guide">
    <div>
        <p class="font-medium text-base">{{ __('ui.voip.asterisk_guide_title') }}</p>
        <p class="mt-1 text-sky-900/90 dark:text-sky-200/90">{{ __('ui.voip.asterisk_guide_intro') }}</p>
        <p class="mt-2 text-xs font-medium text-sky-800 dark:text-sky-200">{{ __('ui.voip.asterisk_webhook_method_note') }}</p>
        <p class="mt-2 text-xs text-sky-800 dark:text-sky-300">{{ __('ui.voip.asterisk_network_note') }}</p>
    </div>

    <ol class="list-decimal list-inside space-y-1.5 text-sky-900/90 dark:text-sky-200/90">
        <li>{{ __('ui.voip.asterisk_step_1') }}</li>
        <li>{{ __('ui.voip.asterisk_step_2') }}</li>
        <li>{{ __('ui.voip.asterisk_step_3') }}</li>
        <li>{{ __('ui.voip.asterisk_step_4') }}</li>
        <li>{{ __('ui.voip.asterisk_step_5') }}</li>
        <li>{{ __('ui.voip.asterisk_step_6') }}</li>
    </ol>

    @if ($hasWebhook)
        <div class="space-y-2">
            <p class="text-xs font-medium text-sky-900 dark:text-sky-100">{{ __('ui.voip.asterisk_settings_title') }}</p>
            <x-saas.webhook-url :url="$webhookUrl" :label="__('ui.voip.asterisk_cdr_url_label')" />
            @if ($connectionId)
                <p class="text-xs text-sky-800 dark:text-sky-300" dir="ltr">
                    {{ __('ui.voip.connection_id') }}: <code class="rounded bg-white/80 px-1 dark:bg-zinc-950">{{ $connectionId }}</code>
                </p>
            @endif
            @if ($organizationId)
                <p class="text-xs text-sky-800 dark:text-sky-300" dir="ltr">
                    organization_id: <code class="rounded bg-white/80 px-1 dark:bg-zinc-950">{{ $organizationId }}</code>
                </p>
            @endif
        </div>

        <x-saas.copy-code
            :label="__('ui.voip.asterisk_curl_title')"
            :code="\App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleCurlCommand($webhookUrl)"
            :open="true"
        >
            <p>{{ __('ui.voip.asterisk_curl_hint') }}</p>
        </x-saas.copy-code>

        <x-saas.copy-code
            :label="__('ui.voip.asterisk_dialplan_title')"
            :code="\App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleDialplan($webhookUrl)"
        >
            <p>{{ __('ui.voip.asterisk_dialplan_hint') }}</p>
        </x-saas.copy-code>

        <x-saas.copy-code
            :label="__('ui.voip.asterisk_shell_title')"
            :code="\App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleShellHelper($webhookUrl)"
        >
            <p>{{ __('ui.voip.asterisk_shell_hint') }}</p>
        </x-saas.copy-code>

        <x-saas.copy-code
            :label="__('ui.voip.custom_payload_title')"
            :code="json_encode(\App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleWebhookPayload('inbound'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)"
        >
            <p>{{ __('ui.voip.custom_payload_hint') }}</p>
        </x-saas.copy-code>

        <x-saas.copy-code
            :label="__('ui.voip.asterisk_outbound_payload_title')"
            :code="json_encode(\App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleWebhookPayload('outbound'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)"
        >
            <p>{{ __('ui.voip.asterisk_outbound_payload_hint') }}</p>
        </x-saas.copy-code>

        <div class="rounded-lg border border-sky-200/80 bg-white/70 p-3 text-xs dark:border-sky-800 dark:bg-sky-950/60 space-y-2">
            <p class="font-medium">{{ __('ui.voip.asterisk_fields_title') }}</p>
            <p class="text-sky-800 dark:text-sky-300">{{ __('ui.voip.asterisk_fields_hint') }}</p>
            <div class="overflow-x-auto">
                <table class="w-full text-left font-mono text-[11px]" dir="ltr">
                    <thead>
                        <tr class="border-b border-sky-200 dark:border-sky-800">
                            <th class="py-1 pr-3">JSON field</th>
                            <th class="py-1 pr-3">Aliases</th>
                            <th class="py-1">Meaning</th>
                        </tr>
                    </thead>
                    <tbody class="text-zinc-700 dark:text-zinc-200">
                        <tr class="border-b border-sky-100 dark:border-sky-900"><td class="py-1 pr-3">call_id</td><td class="pr-3">uniqueid, uid</td><td>{{ __('ui.voip.asterisk_field_call_id') }}</td></tr>
                        <tr class="border-b border-sky-100 dark:border-sky-900"><td class="py-1 pr-3">direction</td><td class="pr-3">call_type, call_direction</td><td>{{ __('ui.voip.asterisk_field_direction') }}</td></tr>
                        <tr class="border-b border-sky-100 dark:border-sky-900"><td class="py-1 pr-3">from</td><td class="pr-3">src, caller, phone</td><td>{{ __('ui.voip.asterisk_field_from') }}</td></tr>
                        <tr class="border-b border-sky-100 dark:border-sky-900"><td class="py-1 pr-3">to</td><td class="pr-3">dst, callee</td><td>{{ __('ui.voip.asterisk_field_to') }}</td></tr>
                        <tr class="border-b border-sky-100 dark:border-sky-900"><td class="py-1 pr-3">extension</td><td class="pr-3">agent, dst</td><td>{{ __('ui.voip.asterisk_field_extension') }}</td></tr>
                        <tr class="border-b border-sky-100 dark:border-sky-900"><td class="py-1 pr-3">status</td><td class="pr-3">disposition, dialstatus</td><td>{{ __('ui.voip.asterisk_field_status') }}</td></tr>
                        <tr class="border-b border-sky-100 dark:border-sky-900"><td class="py-1 pr-3">duration</td><td class="pr-3">billsec</td><td>{{ __('ui.voip.asterisk_field_duration') }}</td></tr>
                        <tr><td class="py-1 pr-3">recording_url</td><td class="pr-3">audio_url, audio_link</td><td>{{ __('ui.voip.asterisk_field_recording') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if ($hasNotify)
            <div class="space-y-2 border-t border-sky-200/80 pt-4 dark:border-sky-800">
                <p class="font-medium">{{ __('ui.voip.asterisk_notify_title') }}</p>
                <p class="text-xs text-sky-800 dark:text-sky-300">{{ __('ui.voip.asterisk_notify_intro') }}</p>

                <x-saas.webhook-url :url="$incomingCallUrl" :label="__('ui.voip.asterisk_notify_url_label')" />

                <x-saas.copy-code
                    :label="__('ui.voip.asterisk_notify_curl_title')"
                    :code="\App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleIncomingCallCurl($incomingCallUrl, (int) $organizationId, (string) $webhookToken)"
                >
                    <p>{{ __('ui.voip.asterisk_notify_curl_hint') }}</p>
                </x-saas.copy-code>

                <x-saas.copy-code
                    :label="__('ui.voip.asterisk_notify_dialplan_title')"
                    :code="\App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleRingingDialplan($incomingCallUrl, (int) $organizationId, (string) $webhookToken)"
                >
                    <p>{{ __('ui.voip.asterisk_notify_dialplan_hint') }}</p>
                </x-saas.copy-code>
            </div>
        @endif
    @else
        <p class="text-xs text-sky-800 dark:text-sky-300">{{ __('ui.voip.asterisk_save_first') }}</p>
    @endif
</div>
