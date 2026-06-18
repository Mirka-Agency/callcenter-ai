@props(['label', 'value', 'hint' => null, 'trend' => null])

<div {{ $attributes->merge(['class' => 'saas-stat']) }}>
    <p class="text-sm font-medium text-zinc-500">{{ $label }}</p>
    <p class="saas-stat-value">{{ $value }}</p>
    @if ($hint)
        <p class="text-sm text-zinc-500">{{ $hint }}</p>
    @endif
    @if ($trend)
        <p @class([
            'text-sm font-medium',
            'text-emerald-600' => $trend > 0,
            'text-red-600' => $trend < 0,
            'text-zinc-500' => $trend == 0,
        ])>
            {{ $trend > 0 ? '+' : '' }}{{ $trend }}٪ نسبت به دوره قبل
        </p>
    @endif
</div>
