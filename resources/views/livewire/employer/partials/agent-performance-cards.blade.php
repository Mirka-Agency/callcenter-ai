@php
    $feed = $agentCardFeed;
    $shown = count($feed['items']);
    $filterButtonClass = 'rounded-lg px-3 py-1.5 text-sm font-medium transition';
@endphp

<section class="saas-section" @if (! empty($sectionTour)) data-tour="{{ $sectionTour }}" @endif>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="saas-section-title">{{ $title }}</h2>
            <p class="saas-section-subtitle">
                {{ $subtitle }}
                @if ($feed['counts']['all'] > 0)
                    <span class="ms-1 text-zinc-400">
                        ({{ $shown }} از {{ $feed['total'] }})
                    </span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="setAgentCardFilter('all')"
                @class([
                    $filterButtonClass,
                    'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $agentCardFilter === 'all',
                    'bg-zinc-100 text-zinc-600 dark:bg-zinc-800' => $agentCardFilter !== 'all',
                ])
            >همه ({{ $feed['counts']['all'] }})</button>
            <button
                type="button"
                wire:click="setAgentCardFilter('top')"
                @class([
                    $filterButtonClass,
                    'bg-emerald-600 text-white' => $agentCardFilter === 'top',
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30' => $agentCardFilter !== 'top',
                ])
            >برترین‌ها ({{ $feed['counts']['top'] }})</button>
            <button
                type="button"
                wire:click="setAgentCardFilter('attention')"
                @class([
                    $filterButtonClass,
                    'bg-amber-600 text-white' => $agentCardFilter === 'attention',
                    'bg-amber-50 text-amber-700 dark:bg-amber-950/30' => $agentCardFilter !== 'attention',
                ])
            >نیازمند توجه ({{ $feed['counts']['attention'] }})</button>
            @if ($showPerformanceLink ?? false)
                <a href="{{ route('employer.intelligence.performance') }}" class="saas-btn-secondary text-sm">گزارش کامل</a>
            @endif
        </div>
    </div>

    <div class="max-h-[70vh] overflow-y-auto overscroll-contain pe-1">
        <div class="grid gap-4 lg:grid-cols-2">
            @forelse ($feed['items'] as $agent)
                <div wire:key="agent-card-{{ $agent['id'] }}">
                    <x-saas.agent-performance-card
                        :agent="$agent"
                        :href="$agentProfileUrl($agent)"
                    />
                </div>
            @empty
                <div class="col-span-full">
                    <x-saas.empty-state
                        :title="$emptyTitle"
                        :description="$emptyDescription"
                    />
                </div>
            @endforelse
        </div>

        @if ($feed['hasMore'])
            <div
                wire:intersect="loadMoreAgentCards"
                class="flex justify-center py-4"
                aria-hidden="true"
            >
                <span
                    wire:loading.flex
                    wire:target="loadMoreAgentCards"
                    class="inline-flex items-center gap-2 text-xs text-zinc-500"
                >
                    <span class="inline-flex h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                    در حال بارگذاری…
                </span>
            </div>
        @endif
    </div>
</section>
