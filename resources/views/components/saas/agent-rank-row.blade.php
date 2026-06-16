@props([
    'row',
    'rank',
    'href' => null,
    'value',
    'employee' => null,
])

@php
    $tag = $href ? 'a' : 'div';
    $rankClasses = match ((int) $rank) {
        1 => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
        2 => 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        3 => 'bg-orange-100 text-orange-800 dark:bg-orange-950/40 dark:text-orange-300',
        default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
    };
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'group flex items-start gap-3 rounded-lg border border-transparent px-2.5 py-2.5 transition',
        'hover:-translate-y-0.5 hover:border-zinc-200/80 hover:bg-zinc-50/70 hover:shadow-sm dark:hover:border-zinc-700 dark:hover:bg-zinc-800/60' => filled($href),
    ]) }}
>
    <span @class([
        'flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-xs font-bold tabular-nums',
        $rankClasses,
    ])>
        {{ $rank }}
    </span>

    <x-saas.avatar
        :employee="$employee"
        :name="$row['name']"
        :url="$row['avatar_url'] ?? null"
        size="sm"
        class="shrink-0"
    />

    <div class="min-w-0 flex-1">
        <span
            class="line-clamp-2 break-words text-sm font-medium leading-5 text-zinc-900 transition group-hover:text-indigo-700 dark:text-white dark:group-hover:text-indigo-300"
            title="{{ $row['name'] }}"
        >
            {{ $row['name'] }}
        </span>
    </div>

    <span class="mt-0.5 shrink-0 rounded-md bg-indigo-50 px-2 py-1 text-xs font-bold tabular-nums text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
        {{ $value }}
    </span>
</{{ $tag }}>
