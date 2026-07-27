@php
    use App\Support\AgentPerformancePresenter;

    $subtitle = collect([$agent['position'] ?? null, $agent['department'] ?? null])->filter()->implode(' · ')
        ?: 'کارشناس تماس';
@endphp

<div class="space-y-4">
    <div class="flex items-start gap-4">
        <x-saas.avatar
            :name="$agent['name']"
            :url="$agent['avatar_url'] ?? null"
            size="lg"
            ring
            class="shrink-0"
        />

        <div class="min-w-0 flex-1">
            @if ($showRank && ($agent['rank'] ?? null))
                <p class="text-xs font-medium text-zinc-400">رتبه #{{ $agent['rank'] }}</p>
            @endif
            <h3 class="mt-0.5 text-lg font-bold leading-snug text-zinc-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">
                {{ $agent['name'] }}
            </h3>
            <p class="mt-0.5 text-sm text-zinc-500">{{ $subtitle }}</p>

            <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                @if ($tier !== 'normal')
                    <span @class([
                        'rounded-md px-2.5 py-0.5 text-[11px] font-semibold',
                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300' => $tier === 'top',
                        'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' => $tier === 'attention',
                    ])>{{ AgentPerformancePresenter::tierLabel($tier) }}</span>
                @endif
                <span @class(['rounded-md px-2.5 py-0.5 text-xs font-medium', AgentPerformancePresenter::trendBadgeClass($agent['trend'] ?? null)])>
                    {{ AgentPerformancePresenter::trendIcon($agent['trend'] ?? null) }}
                    {{ AgentPerformancePresenter::trendLabel($agent['trend'] ?? null) }}
                </span>
            </div>
        </div>

        <x-saas.score-ring :score="$agent['average_score']" size="sm" class="hidden shrink-0 sm:flex" />
    </div>

    <x-saas.score-ring :score="$agent['average_score']" size="sm" class="flex justify-center sm:hidden" />

    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 border-t border-zinc-100 pt-4 dark:border-zinc-800 sm:grid-cols-4">
        <div>
            <dt class="text-[11px] text-zinc-500">تحلیل‌شده</dt>
            <dd class="text-sm font-semibold tabular-nums">{{ $agent['total_analyzed'] ?? 0 }}</dd>
        </div>
        <div>
            <dt class="text-[11px] text-zinc-500">نرخ پاسخ</dt>
            <dd class="text-sm font-semibold tabular-nums">{{ isset($agent['answer_rate']) ? $agent['answer_rate'].'%' : '—' }}</dd>
        </div>
        <div>
            <dt class="text-[11px] text-zinc-500">امتیاز لید</dt>
            <dd @class(['text-sm font-semibold tabular-nums', AgentPerformancePresenter::scoreTextClass($agent['average_lead_score'] ?? null)])>
                {{ $agent['average_lead_score'] ?: '—' }}
            </dd>
        </div>
        <div>
            <dt class="text-[11px] text-zinc-500">رضایت مشتری</dt>
            <dd class="text-sm font-semibold tabular-nums">{{ $agent['average_sentiment'] ?: '—' }}</dd>
        </div>
    </dl>

    @if (filled($agent['average_duration_label'] ?? null) || (($agent['total_calls'] ?? 0) > ($agent['total_analyzed'] ?? 0)))
        <p class="text-xs text-zinc-500">
            @if (filled($agent['average_duration_label'] ?? null))
                میانگین مدت مکالمه: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $agent['average_duration_label'] }}</span>
            @endif
            @if (($agent['total_calls'] ?? 0) > ($agent['total_analyzed'] ?? 0))
                @if (filled($agent['average_duration_label'] ?? null)) · @endif
                {{ $agent['total_calls'] }} تماس VoIP ثبت‌شده
            @endif
        </p>
    @endif
</div>
