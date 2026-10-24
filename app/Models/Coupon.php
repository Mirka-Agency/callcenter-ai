<?php

namespace App\Models;

use App\Domain\Platform\Enums\CouponType;
use App\Models\Concerns\AuditedByAdmins;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'discount_type', 'discount_value', 'expires_at', 'max_redemptions', 'redemptions', 'is_active'])]
class Coupon extends Model
{
    use AuditedByAdmins;

    protected function casts(): array
    {
        return [
            'discount_type' => CouponType::class,
            'discount_value' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isRedeemable(): bool
    {
        return $this->is_active
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->max_redemptions === null || $this->redemptions < $this->max_redemptions);
    }

    public function apply(int $amountRials): int
    {
        $discount = $this->discount_type === CouponType::Percent
            ? intdiv($amountRials * min(100, $this->discount_value), 100)
            : $this->discount_value;

        return max(0, $amountRials - $discount);
    }
}
