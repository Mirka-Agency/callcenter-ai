@props([
    'active' => false,
    'dir' => null,
])

<span
    {{ $attributes->class([
        'saas-sort-icon',
        'is-active' => $active,
        'is-asc' => $active && $dir === 'asc',
        'is-desc' => $active && $dir === 'desc',
    ]) }}
    aria-hidden="true"
>
    <svg viewBox="0 0 12 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path class="saas-sort-up" d="M3 6.25 6 3.25 9 6.25" />
        <path class="saas-sort-down" d="M3 9.75 6 12.75 9 9.75" />
    </svg>
</span>
