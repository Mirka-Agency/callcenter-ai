<?php

namespace App\Models;

use App\Domain\Platform\Enums\BillingPeriod;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Models\Concerns\AuditedByAdmins;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A plan change ends the current subscription and starts a new one,
 * so revenue history can be rebuilt from start/end dates.
 */
#[Fillable([
    'organization_id', 'plan_id', 'coupon_id', 'status', 'price_rials', 'billing_period',
    'starts_at', 'current_period_ends_at', 'trial_ends_at', 'cancelled_at', 'ended_at', 'notes',
])]
class Subscription extends Model
{
    use AuditedByAdmins;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_period' => BillingPeriod::class,
            'price_rials' => 'integer',
            'starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    /** Monthly recurring revenue contributed by this subscription. Trials contribute nothing. */
    public function monthlyRevenue(): int
    {
        if ($this->status === SubscriptionStatus::Trial) {
            return 0;
        }

        return intdiv($this->price_rials, $this->billing_period->months());
    }

    /** Subscriptions that were paying (not trial) at the given moment. */
    public function scopePayingAt(Builder $query, CarbonInterface $moment): Builder
    {
        return $query
            ->where('status', '!=', SubscriptionStatus::Trial->value)
            ->where('starts_at', '<=', $moment)
            ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $moment));
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SubscriptionStatus::Trial->value,
            SubscriptionStatus::Active->value,
            SubscriptionStatus::PastDue->value,
        ]);
    }
}
