@php
    use App\Support\AgentPerformancePresenter;

    $direction = $qualityTrendInsight['direction'] ?? 'baseline';
    $scoreDelta = $qualityTrendInsight['score_delta'] ?? null;
    $signedDelta = $scoreDelta === null
        ? null
        : ($scoreDelta > 0 ? '+'.$scoreDelta : (string) $scoreDelta);
@endphp

<div class="mt-5 border-t border-zinc-200/80 pt-4 dark:border-zinc-800" wire:key="quality-trend-insight-{{ $qualityTrendInsight['period'] }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p @class([
                'text-sm font-semibold',
                'text-emerald-700 dark:text-emerald-300' => $direction === 'up',
                'text-red-700 dark:text-red-300' => $direction === 'down',
                'text-zinc-700 dark:text-zinc-200' => ! in_array($direction, ['up', 'down'], true),
            ])>{{ $qualityTrendInsight['headline'] }} · {{ $qualityTrendInsight['label'] }}</p>
            <p class="mt-1 text-xs text-zinc-500">
                @if ($qualityTrendInsight['previous_score'] !== null)
                    {{ $qualityTrendInsight['previous_score'] }} → {{ $qualityTrendInsight['current_score'] }}
                    @if ($signedDelta !== null)
                        <span @class([
                            'font-medium',
                            'text-emerald-600 dark:text-emerald-400' => $direction === 'up',
                            'text-red-600 dark:text-red-400' => $direction === 'down',
                        ])>({{ $signedDelta }})</span>
                    @endif
                @else
                    میانگین امتیاز: {{ $qualityTrendInsight['current_score'] }}
                @endif
                · {{ $qualityTrendInsight['analyzed_count'] }} تماس تحلیل‌شده
            </p>
        </div>
        <button type="button" wire:click="clearQualityTrendPeriod" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
            بستن
        </button>
    </div>

    <p class="mt-3 text-sm leading-7 text-zinc-700 dark:text-zinc-200">{{ $qualityTrendInsight['reason'] }}</p>

    @if (! empty($qualityTrendInsight['factors']))
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($qualityTrendInsight['factors'] as $factor)
                <span @class([
                    'rounded-md px-2.5 py-1 text-xs font-medium',
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300' => $direction !== 'down',
                    'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-300' => $direction === 'down',
                ])>{{ $factor['item'] }} ({{ $factor['count'] }})</span>
            @endforeach
        </div>
    @endif

    <div class="mt-4">
        <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">
            @if ($direction === 'up')
                کارشناسانی که باعث افزایش روند شدند
            @elseif ($direction === 'down')
                کارشناسانی که باعث کاهش روند شدند
            @else
                کارشناسان این روز
            @endif
        </h3>

        <div class="mt-3 space-y-2">
            @forelse ($qualityTrendInsight['agents'] as $agent)
                <a
                    href="{{ route('employer.intelligence.performance.show', $agent['id']) }}"
                    wire:navigate
                    class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200/80 bg-white px-3 py-2.5 transition hover:border-indigo-300 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-800"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $agent['name'] }}</p>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            {{ $agent['analyzed_count'] }} تماس
                            @if ($agent['highlight'])
                                · {{ $agent['highlight'] }}
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 text-sm">
                        <span @class(['font-bold tabular-nums', AgentPerformancePresenter::scoreTextClass($agent['score'])])>
                            {{ $agent['score'] }}
                        </span>
                        @if ($agent['score_delta'] !== null)
                            <span @class([
                                'rounded-md px-1.5 py-0.5 text-xs font-medium tabular-nums',
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' => $agent['score_delta'] > 0,
                                'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $agent['score_delta'] < 0,
                                'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => $agent['score_delta'] == 0,
                            ])>
                                {{ $agent['score_delta'] > 0 ? '+'.$agent['score_delta'] : $agent['score_delta'] }}
                            </span>
                        @endif
                    </div>
                </a>
            @empty
                <p class="rounded-lg border border-dashed border-zinc-200 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
                    کارشناسی با تماس تحلیل‌شده در این نقطه پیدا نشد.
                </p>
            @endforelse
        </div>
    </div>
</div>
