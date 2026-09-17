<?php

namespace App\Livewire\Employer\Concerns;

trait HasQualityTrendDrilldown
{
    public ?string $selectedQualityTrendPeriod = null;

    public function drilldown(string $dimension, string $value): void
    {
        if ($dimension !== 'period') {
            return;
        }

        $this->selectQualityTrendPeriod($value);
    }

    public function selectQualityTrendPeriod(string $period): void
    {
        $period = trim($period);

        if ($period === '' || mb_strlen($period) > 32) {
            return;
        }

        $this->selectedQualityTrendPeriod = $this->selectedQualityTrendPeriod === $period
            ? null
            : $period;
    }

    public function clearQualityTrendPeriod(): void
    {
        $this->selectedQualityTrendPeriod = null;
    }

    /**
     * @param  list<array{period: string}>  $qualityTrend
     */
    protected function resolvedQualityTrendPeriod(array $qualityTrend): ?string
    {
        if ($this->selectedQualityTrendPeriod === null) {
            return null;
        }

        $allowed = collect($qualityTrend)->pluck('period')->all();

        if (! in_array($this->selectedQualityTrendPeriod, $allowed, true)) {
            $this->selectedQualityTrendPeriod = null;

            return null;
        }

        return $this->selectedQualityTrendPeriod;
    }
}
