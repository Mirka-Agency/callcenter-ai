<?php

namespace App\Livewire\Employer\Concerns;

trait HasAgentPerformanceCardFeed
{
    public string $agentCardFilter = 'all';

    public int $agentCardVisible = 8;

    public function setAgentCardFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'top', 'attention'], true)) {
            return;
        }

        $this->agentCardFilter = $filter;
        $this->agentCardVisible = 8;
    }

    public function loadMoreAgentCards(): void
    {
        $this->agentCardVisible += 8;
    }

    /**
     * @param  list<array<string, mixed>>  $agents
     * @return array{
     *     items: list<array<string, mixed>>,
     *     total: int,
     *     hasMore: bool,
     *     counts: array{all: int, top: int, attention: int}
     * }
     */
    protected function agentCardFeed(array $agents): array
    {
        $all = collect($agents)->values();
        $filtered = $this->agentCardFilter === 'all'
            ? $all
            : $all->where('tier', $this->agentCardFilter)->values();

        return [
            'items' => $filtered->take($this->agentCardVisible)->values()->all(),
            'total' => $filtered->count(),
            'hasMore' => $filtered->count() > $this->agentCardVisible,
            'counts' => [
                'all' => $all->count(),
                'top' => $all->where('tier', 'top')->count(),
                'attention' => $all->where('tier', 'attention')->count(),
            ],
        ];
    }
}
