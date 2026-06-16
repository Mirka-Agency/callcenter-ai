<?php

namespace App\Models;

use App\Domain\Billing\Enums\ConversationEstimateType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

#[Fillable([
    'default_llm_provider_id',
    'default_llm_model_id',
    'allow_negative_balance',
    'currency',
    'billing_unit_currency',
    'billing_unit_price',
    'estimation_words_per_minute',
    'estimation_tokens_per_word',
    'estimation_conversation_ratios',
])]
class PlatformAiSettings extends Model
{
    protected function casts(): array
    {
        return [
            'allow_negative_balance' => 'boolean',
            'billing_unit_price' => 'decimal:2',
            'estimation_tokens_per_word' => 'decimal:2',
            'estimation_conversation_ratios' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'currency' => 'IRR',
            'billing_unit_currency' => 'USD',
            'billing_unit_price' => 500_000,
            'allow_negative_balance' => false,
            'estimation_words_per_minute' => 150,
            'estimation_tokens_per_word' => 1.30,
            'estimation_conversation_ratios' => self::defaultConversationRatios(),
        ]);
    }

    /** @return array<string, float> */
    public static function defaultConversationRatios(): array
    {
        return collect(ConversationEstimateType::cases())
            ->mapWithKeys(fn (ConversationEstimateType $type) => [
                $type->value => $type->defaultOutputRatio(),
            ])
            ->all();
    }

    public function conversationRatio(ConversationEstimateType $type): float
    {
        $ratios = $this->estimation_conversation_ratios ?? self::defaultConversationRatios();

        return (float) ($ratios[$type->value] ?? $type->defaultOutputRatio());
    }

    public static function currencyCode(): string
    {
        return static::current()->currency ?? 'IRR';
    }

    public static function billingUnitCurrency(): string
    {
        return static::current()->billing_unit_currency ?? 'USD';
    }

    public static function billingUnitPrice(): float
    {
        return (float) (static::current()->billing_unit_price ?? 1);
    }

    public static function convertFromUnits(float $amount): float
    {
        return round($amount * static::billingUnitPrice(), 6);
    }

    public static function formatMoney(float|int $amount): string
    {
        return Number::currency($amount, static::currencyCode(), 'fa');
    }

    public static function formatUnitMoney(float|int $amount): string
    {
        return Number::currency($amount, static::billingUnitCurrency(), 'fa');
    }

    public function defaultProvider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'default_llm_provider_id');
    }

    public function defaultModel(): BelongsTo
    {
        return $this->belongsTo(LlmModel::class, 'default_llm_model_id');
    }
}
