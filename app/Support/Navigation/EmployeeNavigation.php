<?php

namespace App\Support\Navigation;

use App\Domain\Crm\OrganizationCrmMode;

class EmployeeNavigation
{
    public static function items(): array
    {
        return [
            ['label' => 'داشبورد', 'route' => 'employee.dashboard', 'icon' => 'home'],
            ['label' => 'عملکرد من', 'route' => 'employee.performance', 'icon' => 'chart'],
            ['label' => 'تماس‌های من', 'route' => 'employee.calls', 'icon' => 'phone'],
            [
                'label' => 'CRM',
                'key' => 'crm',
                'icon' => 'users',
                'children' => self::crmChildren(),
            ],
            ['label' => 'آپلود تماس', 'route' => 'employee.uploads', 'icon' => 'upload'],
            ['label' => 'صف تحلیل', 'route' => 'employee.processing-queue.index', 'icon' => 'cloud'],
            ['label' => 'ارزیابی مهارت فروش', 'route' => 'employee.coaching', 'icon' => 'sparkles'],
            ['label' => 'فعالیت اخیر', 'route' => 'employee.activity', 'icon' => 'activity'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function crmChildren(): array
    {
        $customers = [
            'label' => 'اشخاص و شرکت‌ها',
            'route' => 'employee.customers.index',
            'icon' => 'users',
            'matches' => ['employee.customers.*'],
        ];

        if (! CurrentOrganizationCrmMode::isConfigured()
            || CurrentOrganizationCrmMode::resolve() === OrganizationCrmMode::External) {
            return [$customers];
        }

        return [
            [
                'label' => 'آمار',
                'route' => 'employee.crm.overview',
                'icon' => 'chart',
                'matches' => ['employee.crm.overview'],
            ],
            $customers,
            [
                'label' => 'کاریز',
                'route' => 'employee.crm.deals.index',
                'icon' => 'analytics',
                'matches' => ['employee.crm.deals.index', 'employee.crm.deals.show'],
            ],
            [
                'label' => 'فهرست فرصت‌ها',
                'route' => 'employee.crm.deals.list',
                'icon' => 'activity',
                'matches' => ['employee.crm.deals.list'],
            ],
            [
                'label' => 'یادآوری‌ها',
                'route' => 'employee.crm.reminders.index',
                'icon' => 'calendar',
                'matches' => ['employee.crm.reminders.*'],
            ],
            [
                'label' => 'محصولات و خدمات',
                'route' => 'employee.crm.products.index',
                'icon' => 'document',
                'matches' => ['employee.crm.products.*'],
            ],
            [
                'label' => 'فاکتور و پیش فاکتور',
                'route' => 'employee.crm.invoices.index',
                'icon' => 'document',
                'matches' => ['employee.crm.invoices.*'],
            ],
        ];
    }
}
