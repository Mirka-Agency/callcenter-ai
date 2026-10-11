<?php

namespace App\Support\Seeding;

/**
 * Sample CRM pipelines and owners shown for demo organizations.
 * These stand in for a live Didar response so the CRM screen is usable offline.
 */
final class DemoCrmCatalog
{
    public const PIPELINE_ID = 'demo-sales';

    public const STAGE_ID = 'demo-proposal';

    public const OWNER_ID = 'demo-owner-1';

    /**
     * @return list<array{id: string, title: string, stages: list<array{id: string, title: string, index: int}>}>
     */
    public static function pipelines(): array
    {
        return [
            [
                'id' => self::PIPELINE_ID,
                'title' => 'فروش سازمانی',
                'stages' => [
                    ['id' => 'demo-new', 'title' => 'سرنخ جدید', 'index' => 0],
                    ['id' => self::STAGE_ID, 'title' => 'پیشنهاد ارسال شد', 'index' => 1],
                    ['id' => 'demo-negotiation', 'title' => 'مذاکره', 'index' => 2],
                    ['id' => 'demo-won', 'title' => 'برنده', 'index' => 3],
                ],
            ],
            [
                'id' => 'demo-support',
                'title' => 'پشتیبانی و تمدید',
                'stages' => [
                    ['id' => 'demo-ticket', 'title' => 'ثبت درخواست', 'index' => 0],
                    ['id' => 'demo-follow', 'title' => 'پیگیری', 'index' => 1],
                    ['id' => 'demo-closed', 'title' => 'بسته‌شده', 'index' => 2],
                ],
            ],
        ];
    }

    /**
     * @return list<array{id: string, name: string, email: string}>
     */
    public static function users(): array
    {
        return [
            ['id' => self::OWNER_ID, 'name' => 'مدیر فروش دمو', 'email' => 'sales.owner@demo.local'],
            ['id' => 'demo-owner-2', 'name' => 'کارشناس ارشد دمو', 'email' => 'senior.agent@demo.local'],
        ];
    }
}
