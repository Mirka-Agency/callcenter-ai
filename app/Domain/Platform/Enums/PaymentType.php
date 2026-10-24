<?php

namespace App\Domain\Platform\Enums;

enum PaymentType: string
{
    case Payment = 'payment';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Payment => 'دریافت',
            self::Refund => 'بازپرداخت',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Payment => 'success',
            self::Refund => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
