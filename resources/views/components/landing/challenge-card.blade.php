@props([
    'title',
    'text',
    'strokeWidth' => '1.6',
])

<article class="challenges__card">
    <span class="challenges__icon" aria-hidden="true">
        <svg class="challenges__glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round">
            {{ $slot }}
        </svg>
        <svg class="challenges__x" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
            <circle class="challenges__mark" cx="8" cy="8" r="6.1" />
            <path d="M5.7 5.7 10.3 10.3M10.3 5.7 5.7 10.3" />
        </svg>
    </span>
    <h3>{{ $title }}</h3>
    <p>{{ $text }}</p>
</article>
