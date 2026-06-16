@props([
    'label',
    'score',
    'size' => 'md',
])

@php
    use App\Support\AnalysisInsightPresenter;

    $numeric = is_numeric($score) ? (float) $score : 0;
    $percent = min(100, max(0, $numeric));
    $display = $numeric > 0 ? (int) round($numeric) : '—';

    $stroke = match (true) {
        $percent >= 85 => '#10b981',
        $percent >= 70 => '#f59e0b',
        default => '#f43f5e',
    };

    $sizes = [
        'sm' => ['box' => 'h-16 w-16', 'text' => 'text-sm', 'label' => 'text-[11px]'],
        'md' => ['box' => 'h-20 w-20', 'text' => 'text-lg', 'label' => 'text-xs'],
        'lg' => ['box' => 'h-24 w-24', 'text' => 'text-xl', 'label' => 'text-sm'],
    ];
    $sizeClasses = $sizes[$size] ?? $sizes['md'];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-2 text-center']) }}>
    <div class="relative {{ $sizeClasses['box'] }}">
        <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
            <circle
                cx="18"
                cy="18"
                r="15.9"
                fill="none"
                class="stroke-zinc-200 dark:stroke-zinc-700"
                stroke-width="3"
                pathLength="100"
            />
            @if ($numeric > 0)
                <circle
                    cx="18"
                    cy="18"
                    r="15.9"
                    fill="none"
                    stroke="{{ $stroke }}"
                    stroke-width="3"
                    stroke-linecap="round"
                    pathLength="100"
                    stroke-dasharray="{{ $percent }} 100"
                />
            @endif
        </svg>
        <div class="absolute inset-0 flex items-center justify-center">
            <span @class(['font-bold tabular-nums', $sizeClasses['text'], AnalysisInsightPresenter::scoreTextClass((int) round($numeric))])>
                {{ $display }}
            </span>
        </div>
    </div>
    <p @class(['max-w-[7rem] font-medium leading-snug text-zinc-600 dark:text-zinc-400', $sizeClasses['label']])>
        {{ $label }}
    </p>
</div>
