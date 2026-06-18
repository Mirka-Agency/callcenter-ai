@props([
    'employee' => null,
    'name' => null,
    'url' => null,
    'href' => null,
    'active' => false,
])

@php
    $label = $name ?? $employee?->full_name ?? '?';

    $chipClass = $active
        ? 'border-indigo-300 bg-indigo-50 text-indigo-800 dark:border-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-200'
        : 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600';
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        wire:navigate
        {{ $attributes->class(['saas-chip', $chipClass]) }}
    >
        <x-saas.avatar :employee="$employee" :name="$label" :url="$url" size="xs" class="shrink-0" />
        <span class="max-w-[12rem] truncate sm:max-w-none sm:whitespace-nowrap">{{ $label }}</span>
    </a>
@else
    <button
        type="button"
        {{ $attributes->class(['saas-chip', $chipClass]) }}
    >
        <x-saas.avatar :employee="$employee" :name="$label" :url="$url" size="xs" class="shrink-0" />
        <span class="max-w-[12rem] truncate sm:max-w-none sm:whitespace-nowrap">{{ $label }}</span>
    </button>
@endif
