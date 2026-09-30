@props(['label', 'value', 'hint' => null, 'trend' => null, 'tone' => null, 'comparison' => null, 'comparisonUnit' => null, 'tooltip' => null])

@php
    $comparisonAmount = $comparison === null ? null : abs((float) $comparison);
    $comparisonText = $comparisonAmount === null
        ? null
        : (fmod($comparisonAmount, 1.0) === 0.0
            ? (string) (int) $comparisonAmount
            : rtrim(rtrim(number_format($comparisonAmount, 1, '.', ''), '0'), '.'));
@endphp

<div {{ $attributes->class([
    'saas-stat',
    'saas-stat--good' => $tone === 'good',
    'saas-stat--medium' => $tone === 'medium',
    'saas-stat--bad' => $tone === 'bad',
]) }} @if ($tooltip) tabindex="0" @endif>
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
        @if ($comparisonText !== null)
            <p @class([
                'saas-stat-comparison inline-flex items-center gap-1 font-medium',
                'text-emerald-600 dark:text-emerald-400' => (float) $comparison > 0,
                'text-red-600 dark:text-red-400' => (float) $comparison < 0,
                'text-zinc-500' => (float) $comparison == 0,
            ])>
                @if ((float) $comparison > 0)
                    <svg class="h-[1em] w-[1em] shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 13V3m0 0L4 7m4-4 4 4" />
                    </svg>
                @elseif ((float) $comparison < 0)
                    <svg class="h-[1em] w-[1em] shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 3v10m0 0 4-4m-4 4L4 9" />
                    </svg>
                @endif
                <span>{{ $comparisonText }}{{ $comparisonUnit }} نسبت به ماه قبل</span>
            </p>
        @endif
    </div>
    @if ($tooltip)
        <p class="saas-stat-tooltip" role="tooltip">{{ $tooltip }}</p>
    @endif
</div>
