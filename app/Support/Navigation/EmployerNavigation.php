<?php

namespace App\Support\Navigation;

use App\Domain\Crm\OrganizationCrmMode;
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
            ['label' => 'آپلود دستی تماس', 'route' => 'employer.manual-analyses.index', 'icon' => 'upload'],
            ['label' => 'صف تحلیل تماس', 'route' => 'employer.processing-queue.index', 'icon' => 'cloud'],
            [
                'label' => 'CRM',
                'key' => 'crm',
                'icon' => 'users',
                'children' => self::crmChildren(),
            ],
            ['label' => 'خطوط تلفنی', 'route' => 'employer.voip.index', 'icon' => 'phone'],
            ['label' => 'پروفایل سازمان', 'route' => 'employer.organization.profile', 'icon' => 'building'],
        ];

        if (! OnPrem::billingHidden()) {
            $items[] = ['label' => 'اعتبار هوش مصنوعی', 'route' => 'employer.wallet.index', 'icon' => 'wallet'];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private static function crmChildren(): array
    {
        $customers = [
            'label' => 'اشخاص و شرکت‌ها',
            'route' => 'employer.customers.index',
            'icon' => 'users',
            'matches' => ['employer.customers.*'],
        ];

        if (! CurrentOrganizationCrmMode::isConfigured()) {
            return [$customers];
        }

        if (CurrentOrganizationCrmMode::resolve() === OrganizationCrmMode::External) {
            return [
                $customers,
                [
                    'label' => 'اتصالات خارجی',
                    'route' => 'employer.crm.index',
                    'icon' => 'cloud',
                    'matches' => ['employer.crm.index', 'employer.crm.connections.*'],
                ],
            ];
        }

        return [
            [
                'label' => 'نمای کلی',
                'route' => 'employer.crm.overview',
                'icon' => 'chart',
                'matches' => ['employer.crm.overview'],
            ],
            $customers,
            [
                'label' => 'کاریز',
                'route' => 'employer.crm.deals.index',
                'icon' => 'analytics',
                'matches' => ['employer.crm.deals.index', 'employer.crm.deals.show'],
            ],
            [
                'label' => 'فهرست فرصت‌ها',
                'route' => 'employer.crm.deals.list',
                'icon' => 'activity',
                'matches' => ['employer.crm.deals.list'],
            ],
            [
                'label' => 'یادآوری‌ها',
                'route' => 'employer.crm.reminders.index',
                'icon' => 'calendar',
                'matches' => ['employer.crm.reminders.*'],
            ],
            [
                'label' => 'محصولات و خدمات',
                'route' => 'employer.crm.products.index',
                'icon' => 'document',
                'matches' => ['employer.crm.products.*'],
            ],
            [
                'label' => 'تنظیمات CRM',
                'route' => 'employer.crm.settings',
                'icon' => 'document',
                'matches' => ['employer.crm.settings'],
            ],
        ];
    }
}
