@props([
    'customer',
    'href' => null,
])

@php
    $cardClass = 'group block rounded-lg border border-zinc-200/80 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-700';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cardClass]) }} wire:navigate>
        @include('components.saas.partials.customer-card-body')
    </a>
@else
    <div {{ $attributes->merge(['class' => $cardClass]) }}>
        @include('components.saas.partials.customer-card-body')
    </div>
@endif
