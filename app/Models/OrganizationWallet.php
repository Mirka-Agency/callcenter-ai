<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'balance', 'currency', 'low_balance_threshold'])]
class OrganizationWallet extends Model
{
    public const DEFAULT_LOW_BALANCE_THRESHOLD_IRR = 100_000.0;

    public const DEFAULT_LOW_BALANCE_THRESHOLD = 10.0;

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:6',
            'low_balance_threshold' => 'decimal:6',
        ];
    }

    public function lowBalanceThreshold(): float
    {
        $custom = $this->low_balance_threshold;

        if ($custom !== null && (float) $custom > 0) {
            return (float) $custom;
        }

        return $this->defaultLowBalanceThreshold();
    }

    public function defaultLowBalanceThreshold(): float
    {
        return $this->currency === 'IRR'
            ? self::DEFAULT_LOW_BALANCE_THRESHOLD_IRR
            : self::DEFAULT_LOW_BALANCE_THRESHOLD;
    }

    public function criticalBalanceThreshold(): float
    {
        $floor = $this->currency === 'IRR' ? 1.0 : 0.01;

        return max($floor, round($this->lowBalanceThreshold() * 0.1, 6));
    }

    public function hasLowBalance(): bool
    {
        return (float) $this->balance < $this->lowBalanceThreshold();
    }

    public function hasCriticalBalance(): bool
    {
        return (float) $this->balance < $this->criticalBalanceThreshold();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'organization_id', 'organization_id')
            ->latest('created_at');
    }
}
