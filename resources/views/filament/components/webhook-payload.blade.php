<div class="space-y-3">
    <p class="text-sm text-gray-600 dark:text-gray-300">
        {{ __('filament.misc.webhook_payload_security_notice') }}
    </p>

    <pre
        dir="ltr"
        class="max-h-[60vh] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-gray-950 p-4 text-left font-mono text-xs text-gray-100"
    >{{ $payload }}</pre>
</div>
