@props([
    'webhookUrl' => null,
])

<div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-100 space-y-3" data-tour="asterisk-guide">
    <div>
        <p class="font-medium">{{ __('ui.voip.asterisk_guide_title') }}</p>
        <p class="mt-1 text-sky-900/90 dark:text-sky-200/90">{{ __('ui.voip.asterisk_guide_intro') }}</p>
    </div>

    <ol class="list-none space-y-1 text-sky-900/90 dark:text-sky-200/90">
        <li>{{ __('ui.voip.asterisk_step_1') }}</li>
        <li>{{ __('ui.voip.asterisk_step_2') }}</li>
        <li>{{ __('ui.voip.asterisk_step_3') }}</li>
        <li>{{ __('ui.voip.asterisk_step_4') }}</li>
        <li>{{ __('ui.voip.asterisk_step_5') }}</li>
    </ol>

    <p class="text-xs text-sky-800 dark:text-sky-300">{{ __('ui.voip.asterisk_fields_hint') }}</p>

    @if ($webhookUrl)
        <details class="rounded-lg border border-sky-200/80 bg-white/70 p-3 text-xs dark:border-sky-800 dark:bg-sky-950/60" open>
            <summary class="cursor-pointer font-medium">{{ __('ui.voip.asterisk_curl_title') }}</summary>
            <pre class="mt-2 overflow-x-auto rounded bg-white p-2 font-mono text-[11px] text-zinc-700 dark:bg-zinc-950 dark:text-zinc-200" dir="ltr">{{ \App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleCurlCommand($webhookUrl) }}</pre>
        </details>

        <details class="rounded-lg border border-sky-200/80 bg-white/70 p-3 text-xs dark:border-sky-800 dark:bg-sky-950/60">
            <summary class="cursor-pointer font-medium">{{ __('ui.voip.asterisk_dialplan_title') }}</summary>
            <pre class="mt-2 overflow-x-auto rounded bg-white p-2 font-mono text-[11px] text-zinc-700 dark:bg-zinc-950 dark:text-zinc-200" dir="ltr">{{ \App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleDialplan($webhookUrl) }}</pre>
        </details>

        <details class="rounded-lg border border-sky-200/80 bg-white/70 p-3 text-xs dark:border-sky-800 dark:bg-sky-950/60">
            <summary class="cursor-pointer font-medium">{{ __('ui.voip.asterisk_shell_title') }}</summary>
            <pre class="mt-2 overflow-x-auto rounded bg-white p-2 font-mono text-[11px] text-zinc-700 dark:bg-zinc-950 dark:text-zinc-200" dir="ltr">{{ \App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleShellHelper($webhookUrl) }}</pre>
        </details>

        <details class="rounded-lg border border-sky-200/80 bg-white/70 p-3 text-xs dark:border-sky-800 dark:bg-sky-950/60">
            <summary class="cursor-pointer font-medium">{{ __('ui.voip.custom_payload_title') }}</summary>
            <p class="mt-2 text-sky-800/80 dark:text-sky-300">{{ __('ui.voip.custom_payload_hint') }}</p>
            <pre class="mt-2 overflow-x-auto rounded bg-white p-2 font-mono text-[11px] text-zinc-700 dark:bg-zinc-950 dark:text-zinc-200" dir="ltr">{{ json_encode(\App\Infrastructure\Voip\Adapters\CustomVoipAdapter::sampleWebhookPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    @else
        <p class="text-xs text-sky-800 dark:text-sky-300">{{ __('ui.voip.asterisk_save_first') }}</p>
    @endif
</div>
