<?php

namespace App\Support\Navigation;

use App\Support\OnPrem;

class EmployerNavigation
{
    public static function items(): array
    {
        $items = [
            ['label' => 'داشبورد', 'route' => 'employer.dashboard', 'icon' => 'home'],
            ['label' => 'عملکرد کارشناسان', 'route' => 'employer.intelligence.performance', 'icon' => 'chart'],
            ['label' => 'تحلیل تماس‌ها', 'route' => 'employer.intelligence.index', 'icon' => 'sparkles'],
            ['label' => 'داخلی‌ها', 'route' => 'employer.extensions.index', 'icon' => 'phone'],
            ['label' => 'مشتریان', 'route' => 'employer.customers.index', 'icon' => 'users'],
            ['label' => 'آپلود دستی تماس', 'route' => 'employer.manual-analyses.index', 'icon' => 'upload'],
            ['label' => 'صف تحلیل تماس', 'route' => 'employer.processing-queue.index', 'icon' => 'cloud'],
            ['label' => 'CRM', 'route' => 'employer.crm.index', 'icon' => 'cloud'],
            ['label' => 'خطوط تلفنی', 'route' => 'employer.voip.index', 'icon' => 'phone'],
        ];

        if (! OnPrem::billingHidden()) {
            $items[] = ['label' => 'اعتبار هوش مصنوعی', 'route' => 'employer.wallet.index', 'icon' => 'wallet'];
        }

        return $items;
    }
}
