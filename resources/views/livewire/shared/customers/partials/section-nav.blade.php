@props([
    'portal',
    'active' => 'hub',
])

@php
    $tabs = [
        'hub' => [
            'label' => 'نمای کلی',
            'route' => $portal === 'employee' ? 'employee.customers.index' : 'employer.customers.index',
        ],
        'companies' => [
            'label' => 'شرکت‌ها',
            'route' => $portal === 'employee' ? 'employee.customers.companies.index' : 'employer.customers.companies.index',
        ],
        'contacts' => [
            'label' => 'اشخاص',
            'route' => $portal === 'employee' ? 'employee.customers.contacts.index' : 'employer.customers.contacts.index',
        ],
    ];
@endphp

<nav class="flex flex-wrap gap-2 rounded-xl border border-zinc-200/80 bg-white p-1.5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-label="بخش‌های مشتریان" data-tour="customers-section-nav">
    @foreach ($tabs as $key => $tab)
        <a
            href="{{ route($tab['route']) }}"
            wire:navigate
            @class([
                'rounded-lg px-4 py-2 text-sm font-medium transition',
                'bg-zinc-900 text-white shadow-sm dark:bg-white dark:text-zinc-900' => $active === $key,
                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' => $active !== $key,
            ])
        >{{ $tab['label'] }}</a>
    @endforeach
</nav>
