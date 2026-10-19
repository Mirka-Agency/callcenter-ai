<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'cost_per_minute_tomans',
    'position',
    'is_default',
    'is_active',
])]
class LandingCalculatorModel extends Model
{
    protected function casts(): array
    {
        return [
            'cost_per_minute_tomans' => 'integer',
            'position' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $model): void {
            if (! $model->is_default) {
                return;
            }

            static::query()
                ->whereKeyNot($model->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }
}
