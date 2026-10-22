@php
    /** @var \App\Filament\Resources\BlogPosts\Pages\EditBlogPost $livewire */
    $livewire = $this;
    $status = $livewire->autosaveStatus ?? 'idle';
    $locked = ! ($livewire->hasWriteLock ?? false);
    $isDraft = $livewire->record->isDraft();
@endphp

<div
    @if (! $locked && $isDraft)
        wire:poll.5s="autosaveDraft"
        wire:poll.30s="heartbeatLock"
    @elseif ($locked)
        wire:poll.30s="heartbeatLock"
    @endif
    class="mb-4 rounded-xl border border-[var(--border,#E7E7EE)] bg-white px-4 py-3 text-sm text-[var(--secondary,#71717A)]"
    role="status"
    aria-live="polite"
>
    @if ($locked)
        <span>{{ __('filament.blog.autosave.readonly', ['name' => $livewire->lockHolderName ?? '—']) }}</span>
    @elseif (! $isDraft)
        <span>{{ __('filament.blog.autosave.published_hint') }}</span>
    @else
        @switch($status)
            @case('saving')
                <span>{{ __('filament.blog.autosave.saving') }}</span>
                @break
            @case('saved')
                <span>{{ __('filament.blog.autosave.saved') }}</span>
                @break
            @case('error')
                <span class="text-rose-600">{{ __('filament.blog.autosave.error') }}</span>
                @break
            @case('locked')
                <span>{{ __('filament.blog.autosave.locked') }}</span>
                @break
            @default
                <span>{{ __('filament.blog.autosave.idle') }}</span>
        @endswitch
    @endif
</div>
