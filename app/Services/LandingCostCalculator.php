<?php

namespace App\Services;

use App\Models\LandingCalculatorModel;

class LandingCostCalculator
{
    public const DEFAULT_CALLS = 5000;

    public const DEFAULT_MINUTES = 4.0;

    /**
     * Monthly quote for the public calculator.
     * Per-minute amounts are the toman prices entered in the admin.
     *
     * @return array{
     *     unit: string,
     *     calls: int,
     *     minutes: float,
     *     total: float,
     *     totalDecimals: int,
     *     selectedId: int|null,
     *     callFill: float,
     *     minuteFill: float,
     *     models: list<array{id: int, name: string, perMinute: int, default: bool, share: float}>
     * }
     */
    public function present(): array
    {
        $models = LandingCalculatorModel::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (LandingCalculatorModel $model): array => [
                'id' => $model->id,
                'name' => $model->name,
                'perMinute' => (int) $model->cost_per_minute_tomans,
                'default' => (bool) $model->is_default,
            ])
            ->values();

        $selected = $models->firstWhere('default', true) ?? $models->first();
        $perMinute = (int) ($selected['perMinute'] ?? 0);
        $total = $this->roundDisplay(self::DEFAULT_CALLS * self::DEFAULT_MINUTES * $perMinute);
        $max = (float) $models->max('perMinute');

        return [
            'unit' => 'تومان',
            'calls' => self::DEFAULT_CALLS,
            'minutes' => self::DEFAULT_MINUTES,
            'total' => $total,
            'totalDecimals' => $this->decimals($total),
            'selectedId' => $selected['id'] ?? null,
            'callFill' => round(self::DEFAULT_CALLS / 15000 * 100, 2),
            'minuteFill' => round((self::DEFAULT_MINUTES - 0.5) / 9.5 * 100, 2),
            'models' => $models->map(fn (array $model): array => [
                ...$model,
                'share' => $max > 0 ? round($model['perMinute'] / $max * 100, 1) : 0,
            ])->all(),
        ];
    }

    private function roundDisplay(float $amount): float
    {
        return round($amount, $this->decimals($amount));
    }

    private function decimals(float $amount): int
    {
        $abs = abs($amount);

        if ($abs === 0.0 || $abs >= 100) {
            return 0;
        }

        return $abs >= 1 ? 1 : 2;
    }
}
