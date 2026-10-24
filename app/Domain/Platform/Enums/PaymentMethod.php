<?php

namespace App\Domain\Platform\Enums;

enum PaymentMethod: string
{
    case CardTransfer = 'card_transfer';
    case BankTransfer = 'bank_transfer';
    case OnlineGateway = 'online_gateway';
    case Cheque = 'cheque';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CardTransfer => 'کارت به کارت',
            self::BankTransfer => 'حواله بانکی (پایا/ساتنا)',
            self::OnlineGateway => 'درگاه پرداخت',
            self::Cheque => 'چک',
            self::Cash => 'نقدی',
            self::Other => 'سایر',
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
