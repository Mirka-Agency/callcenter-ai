@php
    use App\Support\JalaliDate;

    $cards = [
        [
            'key' => 'companies',
            'label' => 'شرکت',
            'count' => $stats['companies'],
            'tone' => 'indigo',
        ],
        [
            'key' => 'contacts',
            'label' => 'شخص',
            'count' => $stats['contacts'],
            'tone' => 'violet',
        ],
        [
            'key' => 'calls',
            'label' => 'تماس',
            'count' => $stats['calls'],
            'tone' => 'sky',
        ],
    ];
@endphp

<div class="crm-directory-stats" data-tour="customers-directory-stats">
    @foreach ($cards as $card)
        <div
            @class([
                'crm-directory-stat',
                'crm-directory-stat--'.$card['tone'],
            ])
            data-tour="customers-directory-stat-{{ $card['key'] }}"
        >
            <p class="crm-directory-stat__label">{{ $card['label'] }}</p>
            <p class="crm-directory-stat__value">{{ JalaliDate::persianDigits(number_format($card['count'])) }}</p>
        </div>
    @endforeach
</div>
