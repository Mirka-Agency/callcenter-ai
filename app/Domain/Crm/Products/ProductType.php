<?php

namespace App\Domain\Crm\Products;

enum ProductType: string
{
    case Product = 'product';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'محصول',
            self::Service => 'خدمت',
        };
    }
}
