<?php

namespace App\Support\Navigation;

class EmployeeNavigation
{
    public static function items(): array
    {
        return [
            ['label' => 'داشبورد', 'route' => 'employee.dashboard', 'icon' => 'home'],
            ['label' => 'عملکرد من', 'route' => 'employee.performance', 'icon' => 'chart'],
            ['label' => 'تماس‌های من', 'route' => 'employee.calls', 'icon' => 'phone'],
            ['label' => 'مشتریان', 'route' => 'employee.customers.index', 'icon' => 'users'],
            ['label' => 'آپلود تماس', 'route' => 'employee.uploads', 'icon' => 'upload'],
            ['label' => 'صف تحلیل', 'route' => 'employee.processing-queue.index', 'icon' => 'cloud'],
            ['label' => 'ارزیابی مهارت فروش', 'route' => 'employee.coaching', 'icon' => 'sparkles'],
            ['label' => 'فعالیت اخیر', 'route' => 'employee.activity', 'icon' => 'activity'],
        ];
    }
}
