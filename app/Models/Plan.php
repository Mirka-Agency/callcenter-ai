<?php

namespace App\Models;

use App\Domain\Platform\Enums\BillingPeriod;
use App\Models\Concerns\AuditedByAdmins;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'description', 'price_rials', 'billing_period', 'call_quota', 'seat_limit', 'is_active', 'sort'])]
class Plan extends Model
{
    use AuditedByAdmins;

    protected function casts(): array
    {
        return [
            'billing_period' => BillingPeriod::class,
            'price_rials' => 'integer',
            'call_quota' => 'integer',
            'seat_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
