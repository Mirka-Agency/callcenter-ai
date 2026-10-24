<?php

namespace App\Models;

use App\Models\Concerns\AuditedByAdmins;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class AdminSetting extends Model
{
    use AuditedByAdmins;

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public const MONTHLY_AI_BUDGET_RIALS = 'monthly_ai_budget_rials';

    public const CURRENCY_DISPLAY = 'currency_display';

    public const VAT_RATE = 'vat_rate';

    public const INVOICE_DUE_DAYS = 'invoice_due_days';

    public const SELLER_PROFILE = 'seller_profile';

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->first()?->value ?? $default;
    }

    public static function store(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
