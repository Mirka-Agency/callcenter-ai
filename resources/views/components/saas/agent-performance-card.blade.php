@props([
    'agent',
    'href' => null,
    'showRank' => true,
])

@php
    use App\Support\AgentPerformancePresenter;

    $tier = $agent['tier'] ?? AgentPerformancePresenter::tier($agent);
    $cardClass = 'group block rounded-lg border bg-white p-5 shadow-sm transition hover:border-zinc-300 hover:shadow-md dark:bg-zinc-900 dark:hover:border-zinc-700 '.AgentPerformancePresenter::tierBorderClass($tier);
@endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => $cardClass]) }}>
        @include('components.saas.partials.agent-performance-card-body', ['agent' => $agent, 'tier' => $tier, 'showRank' => $showRank])
    </a>
@else
    <div {{ $attributes->merge(['class' => $cardClass]) }}>
        @include('components.saas.partials.agent-performance-card-body', ['agent' => $agent, 'tier' => $tier, 'showRank' => $showRank])
    </div>
@endif
