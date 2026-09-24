@props([
    'employee' => null,
    'user' => null,
    'name' => null,
    'subtitle' => null,
    'url' => null,
    'avatarSize' => 'sm',
    'href' => null,
])

@php
    $displayName = $name ?? $employee?->full_name ?? $user?->name ?? '?';
@endphp

<div {{ $attributes->class(['flex min-w-0 items-center gap-3']) }}>
    <x-saas.avatar
        :employee="$employee"
        :user="$user"
        :name="$displayName"
        :url="$url ?? ($employee?->avatarUrl())"
        :gender="$employee?->gender"
        :agent="(bool) $employee"
        :size="$avatarSize"
    />

    <div class="min-w-0">
        @if ($href)
            <a href="{{ $href }}" class="truncate font-medium text-zinc-900 transition hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">
                {{ $displayName }}
            </a>
        @else
            <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $displayName }}</p>
        @endif

        @if ($subtitle)
            <p class="truncate text-xs text-zinc-500">{{ $subtitle }}</p>
        @endif
    </div>
</div>
