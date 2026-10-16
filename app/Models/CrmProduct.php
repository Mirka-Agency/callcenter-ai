<?php

namespace App\Models;

use App\Domain\Crm\Products\ProductType;
use App\Support\PersianNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id',
    'name',
    'description',
    'type',
    'category_id',
    'code',
    'price_tomans',
    'unit_quantity',
    'unit_label',
    'is_active',
    'created_by_user_id',
])]
class CrmProduct extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (CrmProduct $product): void {
            if (! $product->organization_id) {
                throw new \RuntimeException('Products must belong to an organization.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'price_tomans' => 'integer',
            'unit_quantity' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CrmProductCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function formattedPrice(): string
    {
        if ($this->price_tomans === null) {
            return '—';
        }

        $amount = PersianNumber::format($this->price_tomans) ?? (string) $this->price_tomans;
        $price = $amount.' تومان';

        $unit = $this->formattedUnit();
        if ($unit === null) {
            return $price;
        }

        return $price.' / '.$unit;
    }

    public function formattedUnit(): ?string
    {
        $label = filled($this->unit_label) ? trim((string) $this->unit_label) : null;
        if ($this->unit_quantity === null && $label === null) {
            return null;
        }

        if ($this->unit_quantity === null) {
            return $label;
        }

        $qty = fmod((float) $this->unit_quantity, 1.0) === 0.0
            ? PersianNumber::format((int) $this->unit_quantity)
            : PersianNumber::format($this->unit_quantity, 2);

        return trim(($qty ?? (string) $this->unit_quantity).($label ? ' '.$label : ''));
    }
}
