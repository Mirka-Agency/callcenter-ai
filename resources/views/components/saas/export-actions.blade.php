@props([
    'routeName',
    'routeParams' => [],
])

@php
    $labels = [
        'csv' => 'CSV',
        'xlsx' => 'Excel',
        'pdf' => 'PDF',
    ];

    $query = collect(request()->query())
        ->only(['preset', 'from', 'to', 'employees', 'compare'])
        ->all();
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }}>
    @foreach ($labels as $format => $label)
        @php
            $href = route($routeName, array_merge($routeParams, ['format' => $format]));

            if ($query !== []) {
                $separator = str_contains($href, '?') ? '&' : '?';
                $href .= $separator.http_build_query($query);
            }
        @endphp
        <a
            href="{{ $href }}"
            data-export-link
            class="saas-btn-secondary text-sm"
        >
            {{ $label }}
        </a>
    @endforeach
</div>
