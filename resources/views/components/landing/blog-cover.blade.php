@props([
    'theme' => 'guide',
    'tone' => 1,
])

<div {{ $attributes->class(['blog-cover', 'blog-cover--'.$theme, 'blog-cover--tone-'.$tone]) }} aria-hidden="true">
    @switch($theme)
        @case('call-center')
            <svg viewBox="0 0 360 200" fill="none">
                <rect x="28" y="128" width="304" height="16" rx="8" fill="#e4e4fb" />
                <rect x="78" y="46" width="168" height="108" rx="12" fill="#f8f8ff" stroke="#ddd6fe" />
                <rect x="90" y="58" width="144" height="78" rx="6" fill="#eef2ff" />
                <rect x="104" y="72" width="72" height="8" rx="4" fill="#c4b5fd" />
                <rect x="104" y="88" width="108" height="6" rx="3" fill="#ddd6fe" />
                <rect x="104" y="102" width="86" height="6" rx="3" fill="#ddd6fe" />
                <rect x="148" y="154" width="28" height="8" rx="3" fill="#c4b5fd" />
                <path d="M118 78v-8a44 44 0 0 1 88 0v10" stroke="#7c25fd" stroke-width="6" stroke-linecap="round" />
                <rect x="100" y="78" width="22" height="28" rx="8" fill="#18181b" />
                <rect x="202" y="78" width="22" height="28" rx="8" fill="#18181b" />
                <path d="M214 106c6 10-2 20-18 22" stroke="#7c25fd" stroke-width="5" stroke-linecap="round" />
            </svg>
            @break
        @case('ai')
            <svg viewBox="0 0 360 200" fill="none">
                <rect x="118" y="78" width="150" height="96" rx="12" fill="#f8f8ff" stroke="#ddd6fe" />
                <rect x="130" y="90" width="126" height="68" rx="6" fill="#eef2ff" />
                <path d="M146 124c8-16 14-16 22 0s14 16 22 0 14-16 22 0" stroke="#7c25fd" stroke-width="4" stroke-linecap="round" />
                <rect x="176" y="174" width="34" height="7" rx="3" fill="#c4b5fd" />
                <circle cx="168" cy="58" r="28" fill="#fff" stroke="#c4b5fd" stroke-width="3" />
                <rect x="156" y="48" width="10" height="10" rx="3" fill="#6366f1" />
                <rect x="172" y="48" width="10" height="10" rx="3" fill="#7c25fd" />
                <path d="M160 66h16" stroke="#7c25fd" stroke-width="3" stroke-linecap="round" />
                <rect x="160" y="84" width="16" height="22" rx="8" fill="#7c25fd" />
                <circle cx="196" cy="34" r="8" fill="#ddd6fe" />
            </svg>
            @break
        @case('sales')
            <svg viewBox="0 0 360 200" fill="none">
                <circle cx="188" cy="104" r="62" fill="#efeaff" />
                <circle cx="188" cy="104" r="46" fill="#f7f5ff" stroke="#ddd6fe" stroke-width="8" />
                <circle cx="188" cy="104" r="28" fill="#ede9fe" stroke="#c4b5fd" stroke-width="8" />
                <circle cx="188" cy="104" r="10" fill="#7c25fd" />
                <path d="M188 104 248 52" stroke="#6366f1" stroke-width="5" stroke-linecap="round" />
                <path d="M236 46h22v22" stroke="#6366f1" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            @break
        @case('customer')
            <svg viewBox="0 0 360 200" fill="none">
                <path d="M86 126c18-28 34-40 48-18 8 14 4 28-6 36" stroke="#f5d0b5" stroke-width="16" stroke-linecap="round" />
                <rect x="138" y="48" width="92" height="92" rx="16" fill="#f6e2c4" stroke="#e7c99a" />
                <circle cx="170" cy="84" r="6" fill="#18181b" />
                <circle cx="198" cy="84" r="6" fill="#18181b" />
                <path d="M166 104c8 10 22 10 32 0" stroke="#18181b" stroke-width="4" stroke-linecap="round" />
            </svg>
            @break
        @case('leadership')
            <svg viewBox="0 0 360 200" fill="none">
                <circle cx="118" cy="78" r="22" fill="#e4e4e7" />
                <rect x="90" y="108" width="56" height="58" rx="20" fill="#e4e4e7" />
                <circle cx="242" cy="78" r="22" fill="#ddd6fe" />
                <rect x="214" y="108" width="56" height="58" rx="20" fill="#ddd6fe" />
                <circle cx="180" cy="64" r="26" fill="#7c25fd" />
                <rect x="146" y="98" width="68" height="70" rx="24" fill="#7c25fd" />
            </svg>
            @break
        @default
            <svg viewBox="0 0 360 200" fill="none">
                <rect x="74" y="36" width="196" height="128" rx="14" fill="#f8f8ff" stroke="#ddd6fe" />
                <rect x="88" y="50" width="168" height="96" rx="8" fill="#eef2ff" />
                <rect x="104" y="66" width="58" height="34" rx="6" fill="#fff" />
                <path d="M112 90l10-12 8 8 10-16 12 20" stroke="#7c25fd" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <rect x="174" y="66" width="66" height="8" rx="4" fill="#c4b5fd" />
                <rect x="174" y="82" width="48" height="8" rx="4" fill="#ddd6fe" />
                <rect x="104" y="112" width="136" height="8" rx="4" fill="#ddd6fe" />
                <rect x="104" y="126" width="96" height="8" rx="4" fill="#e9e4ff" />
                <rect x="156" y="164" width="32" height="8" rx="3" fill="#c4b5fd" />
            </svg>
    @endswitch
</div>
