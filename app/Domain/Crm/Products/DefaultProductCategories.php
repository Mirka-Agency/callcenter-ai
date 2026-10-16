<?php

namespace App\Domain\Crm\Products;

final class DefaultProductCategories
{
    /**
     * @return list<array{slug: string, name: string, icon: string, tone: string, position: int}>
     */
    public static function catalog(): array
    {
        return [
            ['slug' => 'software', 'name' => 'نرم‌افزار', 'icon' => 'monitor', 'tone' => 'sky', 'position' => 10],
            ['slug' => 'hardware', 'name' => 'سخت‌افزار', 'icon' => 'chip', 'tone' => 'emerald', 'position' => 20],
            ['slug' => 'services', 'name' => 'خدمات', 'icon' => 'gear', 'tone' => 'violet', 'position' => 30],
            ['slug' => 'subscription', 'name' => 'اشتراک', 'icon' => 'layers', 'tone' => 'amber', 'position' => 40],
            ['slug' => 'training', 'name' => 'آموزش', 'icon' => 'graduation', 'tone' => 'rose', 'position' => 50],
            ['slug' => 'consulting', 'name' => 'مشاوره', 'icon' => 'users', 'tone' => 'indigo', 'position' => 60],
        ];
    }
}
