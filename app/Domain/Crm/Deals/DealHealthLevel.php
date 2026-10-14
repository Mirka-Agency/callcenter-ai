<?php

namespace App\Domain\Crm\Deals;

enum DealHealthLevel: string
{
    case Healthy = 'healthy';
    case Watch = 'watch';
    case AtRisk = 'at_risk';
    case InsufficientData = 'insufficient_data';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'سالم',
            self::Watch => 'نیاز به توجه',
            self::AtRisk => 'در خطر',
            self::InsufficientData => 'داده ناکافی',
        };
    }
}
