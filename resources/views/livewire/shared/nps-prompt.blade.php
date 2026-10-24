<div>
    @if ($visible)
        <div class="nps-prompt" role="dialog" aria-labelledby="nps-prompt-title">
            @if ($submitted)
                <p class="nps-prompt__title">{{ __('admin.nps.thanks') }}</p>
                <button type="button" class="saas-btn-secondary mt-3" wire:click="$set('visible', false)">{{ __('admin.nps.close') }}</button>
            @else
                <p id="nps-prompt-title" class="nps-prompt__title">{{ __('admin.nps.question', ['app' => config('app.name')]) }}</p>
                <div class="nps-prompt__scores" dir="ltr">
                    @foreach (range(0, 10) as $value)
                        <button
                            type="button"
                            wire:click="choose({{ $value }})"
                            @class(['nps-prompt__score', 'is-selected' => $score === $value])
                            aria-pressed="{{ $score === $value ? 'true' : 'false' }}"
                        >{{ \App\Support\JalaliDate::persianDigits($value) }}</button>
                    @endforeach
                </div>
                <div class="nps-prompt__legend" dir="ltr">
                    <span>{{ __('admin.nps.unlikely') }}</span>
                    <span>{{ __('admin.nps.likely') }}</span>
                </div>
                @if ($score !== null)
                    <textarea wire:model="comment" rows="2" class="nps-prompt__comment" placeholder="{{ __('admin.nps.comment_placeholder') }}"></textarea>
                @endif
                @error('score') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                <div class="nps-prompt__actions">
                    <button type="button" class="saas-btn-primary" wire:click="submit" @disabled($score === null)>{{ __('admin.nps.submit') }}</button>
                    <button type="button" class="saas-btn-secondary" wire:click="skip">{{ __('admin.nps.skip') }}</button>
                </div>
            @endif
        </div>
    @endif
</div>
