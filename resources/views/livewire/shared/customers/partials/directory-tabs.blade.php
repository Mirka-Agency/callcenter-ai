@props([
    'portal',
    'active' => 'contacts',
])

@php
    $tabs = [
        'contacts' => [
            'label' => 'اشخاص',
            'route' => $portal === 'employee' ? 'employee.customers.contacts.index' : 'employer.customers.contacts.index',
        ],
        'companies' => [
            'label' => 'شرکت‌ها',
            'route' => $portal === 'employee' ? 'employee.customers.companies.index' : 'employer.customers.companies.index',
        ],
        'calls' => [
            'label' => 'تماس‌ها',
            'route' => $portal === 'employee' ? 'employee.customers.calls.index' : 'employer.customers.calls.index',
        ],
    ];
@endphp

<nav class="crm-directory-tabs" aria-label="بخش‌های مخاطبان" data-tour="customers-directory-tabs">
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
