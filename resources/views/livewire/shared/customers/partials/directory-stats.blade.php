@php
    use App\Support\JalaliDate;

    $routes = [
        'companies' => $portal === 'employee' ? 'employee.customers.companies.index' : 'employer.customers.companies.index',
        'contacts' => $portal === 'employee' ? 'employee.customers.contacts.index' : 'employer.customers.contacts.index',
        'calls' => $portal === 'employee' ? 'employee.customers.calls.index' : 'employer.customers.calls.index',
    ];
    $cards = [
        [
            'key' => 'companies',
            'label' => 'شرکت',
            'count' => $stats['companies'],
        ],
        [
            'key' => 'contacts',
            'label' => 'شخص',
            'count' => $stats['contacts'],
        ],
        [
            'key' => 'calls',
            'label' => 'تماس',
            'count' => $stats['calls'],
        ],
    ];
@endphp

<nav class="crm-directory-stats" data-tour="customers-directory-stats" aria-label="فیلتر شرکت، شخص و تماس">
    @foreach ($cards as $card)
        <a
            href="{{ route($routes[$card['key']]) }}"
            wire:navigate
            class="crm-directory-stat"
            data-tour="customers-directory-stat-{{ $card['key'] }}"
            aria-label="فیلتر {{ $card['label'] }}"
        >
            <span class="crm-directory-stat__label">{{ $card['label'] }}</span>
            <span class="crm-directory-stat__value">{{ JalaliDate::persianDigits(number_format($card['count'])) }}</span>
        </a>
    @endforeach
</nav>
