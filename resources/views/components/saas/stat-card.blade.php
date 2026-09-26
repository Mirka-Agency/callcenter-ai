@props(['label', 'value', 'hint' => null, 'trend' => null, 'tone' => null])

<div {{ $attributes->class([
    'saas-stat',
    'saas-stat--good' => $tone === 'good',
    'saas-stat--medium' => $tone === 'medium',
    'saas-stat--bad' => $tone === 'bad',
]) }}>
    <p class="saas-stat-label">{{ $label }}</p>
    <p class="saas-stat-value">{{ $value }}</p>
    <div class="saas-stat-meta">
        @if ($hint)
            <p>{{ $hint }}</p>
        @endif
        @if ($trend)
            <p @class([
                'font-medium',
                'text-emerald-600' => $trend > 0,
                'text-red-600' => $trend < 0,
                'text-zinc-500' => $trend == 0,
            ])>
                {{ $trend > 0 ? '+' : '' }}{{ $trend }}٪ نسبت به دوره قبل
            </p>
        @endif
    </div>
</div>
