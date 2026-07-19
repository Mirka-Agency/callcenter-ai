<div class="space-y-4">
    @if ($callId)
        <p class="text-sm text-gray-600 dark:text-gray-300" dir="ltr">
            <span class="font-medium">cuid / call id:</span>
            <span class="font-mono">{{ $callId }}</span>
        </p>
    @endif

    @if (! empty($diagnosis))
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
            <p class="mb-2 font-medium">{{ __('filament.misc.webhook_call_details_diagnosis') }}</p>
            <ul class="list-disc space-y-1 ps-5">
                @foreach ($diagnosis as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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

    <div>
        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
            {{ __('filament.misc.webhook_call_details_local') }}
        </p>
        @if ($localCallLog)
            <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2" dir="ltr">
                @foreach ($localCallLog as $key => $value)
                    <div class="rounded-md bg-gray-100 px-3 py-2 dark:bg-gray-800">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $key }}</dt>
                        <dd class="mt-0.5 break-all font-mono text-sm text-gray-900 dark:text-gray-100">{{ $value === null || $value === '' ? '—' : $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @else
            <p class="text-sm text-gray-500">{{ __('filament.misc.webhook_call_details_no_local') }}</p>
        @endif
    </div>

    <div>
        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
            {{ __('filament.misc.webhook_call_details_api') }}
        </p>

        @if (! ($api['attempted'] ?? false))
            <p class="text-sm text-gray-500">{{ $api['error'] ?? __('filament.misc.em_dash') }}</p>
        @elseif (! ($api['success'] ?? false))
            <div class="rounded-lg border border-rose-300 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-700 dark:bg-rose-950 dark:text-rose-100">
                {{ $api['error'] ?? $api['message'] ?? __('filament.misc.em_dash') }}
            </div>
        @else
            <p class="mb-2 text-sm text-emerald-700 dark:text-emerald-300">{{ $api['message'] ?? __('filament.misc.em_dash') }}</p>
            @if (! empty($api['rows']))
                <pre
                    dir="ltr"
                    class="max-h-[40vh] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-gray-950 p-4 text-left font-mono text-xs text-gray-100"
                >{{ json_encode($api['rows'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <p class="text-sm text-gray-500">{{ __('filament.misc.webhook_call_details_api_empty_rows') }}</p>
            @endif
        @endif

        @if (! empty($api['raw']))
            <details class="mt-3">
                <summary class="cursor-pointer text-sm text-gray-600 dark:text-gray-300">{{ __('filament.sections.raw_payload') }}</summary>
                <pre
                    dir="ltr"
                    class="mt-2 max-h-[40vh] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-gray-950 p-4 text-left font-mono text-xs text-gray-100"
                >{{ json_encode($api['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </details>
        @endif
    </div>
</div>
