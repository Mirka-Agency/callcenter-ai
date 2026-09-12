<?php

namespace App\Livewire\Employer\Concerns;

trait HasTeamWeaknessDrilldown
{
    public ?string $selectedTeamWeakness = null;

    public function selectTeamWeakness(string $weakness): void
    {
        $weakness = trim($weakness);

        if ($weakness === '' || mb_strlen($weakness) > 500) {
            return;
        }

        $this->selectedTeamWeakness = $this->selectedTeamWeakness === $weakness
            ? null
            : $weakness;
    }

    public function clearTeamWeakness(): void
    {
        $this->selectedTeamWeakness = null;
    }

    /**
     * @param  list<array{item: string, count: int}>  $teamWeaknesses
     */
    protected function resolvedTeamWeakness(array $teamWeaknesses): ?string
    {
        if ($this->selectedTeamWeakness === null) {
            return null;
        }

        $allowed = collect($teamWeaknesses)->pluck('item')->all();

        if (! in_array($this->selectedTeamWeakness, $allowed, true)) {
            $this->selectedTeamWeakness = null;

            return null;
        }

        return $this->selectedTeamWeakness;
    }
}
