<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

trait OccurredBetween
{
    /**
     * Limit rows to those that happened in the window.
     * Prefer started_at; if the VoIP webhook omitted it, fall back to created_at.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOccurredBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->where(function (Builder $inner) use ($from, $to) {
            $inner->whereBetween('started_at', [$from, $to])
                ->orWhere(function (Builder $missingStart) use ($from, $to) {
                    $missingStart->whereNull('started_at')
                        ->whereBetween('created_at', [$from, $to]);
                });
        });
    }
}
