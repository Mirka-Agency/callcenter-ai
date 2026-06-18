@if ($recordingExpired ?? false)
    <div {{ $attributes->merge(['class' => $embedded ?? false ? '' : 'saas-card']) }}>
        @unless ($embedded ?? false)
            <h2 class="text-lg font-semibold">{{ $recordingTitle ?? 'ضبط' }}</h2>
        @endunless
        <p @class(['text-sm text-amber-700', 'mt-4' => ! ($embedded ?? false)])>{{ __('messages.recording_expired') }}</p>
    </div>
@elseif ($recordingUrl ?? null)
    <div {{ $attributes->merge(['class' => $embedded ?? false ? '' : 'saas-card']) }}>
        @unless ($embedded ?? false)
            <h2 class="text-lg font-semibold">{{ $recordingTitle ?? 'ضبط' }}</h2>
        @endunless
        <div wire:ignore>
            <x-saas.waveform-player :url="$recordingUrl" @class(['mt-4' => ! ($embedded ?? false)]) />
        </div>
    </div>
@endif
