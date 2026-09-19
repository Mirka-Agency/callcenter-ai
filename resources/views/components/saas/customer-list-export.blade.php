@props([
    'portal',
    'entity',
    'search' => '',
    'sort' => 'last_contact',
])

@php
    $routeName = $portal === 'employee'
        ? "employee.customers.{$entity}.export"
        : "employer.customers.{$entity}.export";

    $query = array_filter([
        'search' => $search !== '' ? $search : null,
        'sort' => $sort !== 'last_contact' ? $sort : null,
    ]);
@endphp

<div {{ $attributes->merge(['class' => 'relative']) }} x-data="{ open: false }" @click.outside="open = false">
    <button type="button" class="saas-btn-secondary text-sm" @click="open = ! open">
        دریافت لیست
    </button>
    <div
        x-show="open"
        x-cloak
        class="absolute end-0 z-30 mt-2 w-40 overflow-hidden rounded-lg border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
    >
        @foreach (['xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
            <a
                href="{{ route($routeName, array_merge($query, ['format' => $format])) }}"
                data-export-link
                class="block px-3 py-2 text-sm text-zinc-700 hover:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>
