@props([
    'portal',
    'active' => 'all',
])

@php
    $tabs = [
        'all' => [
            'label' => 'همه',
            'route' => $portal === 'employee' ? 'employee.customers.index' : 'employer.customers.index',
        ],
        'contacts' => [
            'label' => 'اشخاص',
            'route' => $portal === 'employee' ? 'employee.customers.contacts.index' : 'employer.customers.contacts.index',
        ],
        'companies' => [
            'label' => 'شرکت‌ها',
            'route' => $portal === 'employee' ? 'employee.customers.companies.index' : 'employer.customers.companies.index',
        ],
        'reviews' => [
            'label' => 'نیازمند بررسی',
            'route' => $portal === 'employee' ? 'employee.customers.reviews.index' : 'employer.customers.reviews.index',
        ],
    ];

    if ($active === 'hub') {
        $active = 'all';
    }
@endphp

<nav class="crm-directory-tabs" aria-label="بخش‌های مشتریان" data-tour="customers-section-nav">
    @foreach ($tabs as $key => $tab)
        <a
            href="{{ route($tab['route']) }}"
            wire:navigate
            @class([
                'crm-directory-tab',
                'is-active' => $active === $key,
            ])
        >{{ $tab['label'] }}</a>
    @endforeach
</nav>
