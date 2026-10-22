@props([
    'name' => 'doc',
])

<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" {{ $attributes }}>
    @switch($name)
        @case('grid')
            <rect x="4" y="4" width="6.5" height="6.5" rx="1.4" />
            <rect x="13.5" y="4" width="6.5" height="6.5" rx="1.4" />
            <rect x="4" y="13.5" width="6.5" height="6.5" rx="1.4" />
            <rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.4" />
            @break
        @case('cpu')
            <rect x="7" y="7" width="10" height="10" rx="2" />
            <path stroke-linecap="round" d="M9.5 3.8v2.4M14.5 3.8v2.4M9.5 17.8v2.4M14.5 17.8v2.4M3.8 9.5h2.4M3.8 14.5h2.4M17.8 9.5h2.4M17.8 14.5h2.4" />
            <path stroke-linecap="round" d="M10 12h4" />
            @break
        @case('headset')
            <path stroke-linecap="round" d="M5 13v-1a7 7 0 0 1 14 0v1" />
            <rect x="3.5" y="12.5" width="3.5" height="5.5" rx="1.4" />
            <rect x="17" y="12.5" width="3.5" height="5.5" rx="1.4" />
            <path stroke-linecap="round" d="M17.5 18.2c0 1.6-1.6 2.8-4 2.8h-1.2" />
            @break
        @case('chart')
            <path stroke-linecap="round" d="M5 19V9.5M10 19V5M15 19v-6.5M20 19V8" />
            @break
        @case('user')
            <circle cx="12" cy="8.2" r="2.7" />
            <path stroke-linecap="round" d="M6.8 18.2c.8-2.5 2.7-3.8 5.2-3.8s4.4 1.3 5.2 3.8" />
            @break
        @case('users')
            <circle cx="9" cy="8.4" r="2.3" />
            <circle cx="16" cy="9.2" r="1.9" />
            <path stroke-linecap="round" d="M4.8 17.8c.7-2.2 2.3-3.3 4.3-3.3 2 0 3.5 1.1 4.2 3.3M13.2 14.6c1.3-.2 2.6.3 3.4 1.6.5.8.8 1.5 1 2" />
            @break
        @default
            <path d="M8 4.5h6.2L18 8.3V19a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 7 19V6A1.5 1.5 0 0 1 8.5 4.5H8Z" />
            <path stroke-linecap="round" d="M14 4.8V8.4H17.6M9.2 12.2h5.6M9.2 15.4h4" />
    @endswitch
</svg>
