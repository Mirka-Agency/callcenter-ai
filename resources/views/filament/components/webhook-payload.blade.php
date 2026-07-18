<div class="space-y-3">
    <p class="text-sm text-gray-600 dark:text-gray-300">
        {{ __('filament.misc.webhook_payload_security_notice') }}
    </p>

    @if (! empty($highlights))
        <div>
            <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ __('filament.misc.webhook_payload_highlights') }}
            </p>
            <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2" dir="ltr">
                @foreach ($highlights as $key => $value)
                    <div class="rounded-md bg-gray-100 px-3 py-2 dark:bg-gray-800">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $key }}</dt>
                        <dd class="mt-0.5 break-all font-mono text-sm text-gray-900 dark:text-gray-100">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    <pre
        dir="ltr"
        class="max-h-[60vh] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-gray-950 p-4 text-left font-mono text-xs text-gray-100"
    >{{ $payload }}</pre>
</div>
