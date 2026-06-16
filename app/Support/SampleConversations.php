<?php

namespace App\Support;

class SampleConversations
{
    private const DIRECTORY = 'samples/conversations';

    /**
     * @return list<array{id: string, title: string, description: string, category: string, filename: string}>
     */
    private static function definitions(): array
    {
        return [
            [
                'id' => 'sales-follow-up',
                'title' => 'پیگیری فروش',
                'description' => 'پیگیری پس از ارسال پیشنهاد قیمت به مشتری بالقوه',
                'category' => 'فروش',
                'filename' => '01-sales-follow-up.mp3',
            ],
            [
                'id' => 'support-complaint',
                'title' => 'رسیدگی به شکایت',
                'description' => 'مکالمه پشتیبانی برای بررسی و حل نارضایتی مشتری',
                'category' => 'پشتیبانی',
                'filename' => '02-support-complaint.mp3',
            ],
            [
                'id' => 'new-customer-welcome',
                'title' => 'خوش‌آمدگویی مشتری جدید',
                'description' => 'معرفی خدمات و راهنمایی اولیه برای مشتری تازه‌ثبت‌نام‌شده',
                'category' => 'فروش',
                'filename' => '03-new-customer-welcome.mp3',
            ],
            [
                'id' => 'subscription-renewal',
                'title' => 'تمدید اشتراک',
                'description' => 'تماس یادآوری و ترغیب به تمدید اشتراک در حال اتمام',
                'category' => 'فروش',
                'filename' => '04-subscription-renewal.mp3',
            ],
            [
                'id' => 'technical-guidance',
                'title' => 'راهنمایی فنی',
                'description' => 'پاسخ به سؤال فنی مشتری و راهنمایی گام‌به‌گام',
                'category' => 'پشتیبانی',
                'filename' => '05-technical-guidance.mp3',
            ],
        ];
    }

    /**
     * @return list<array{id: string, title: string, description: string, category: string, filename: string, absolute_path: string, available: bool}>
     */
    public static function all(): array
    {
        return array_map(function (array $definition): array {
            $absolutePath = public_path(self::DIRECTORY.'/'.$definition['filename']);

            return [
                ...$definition,
                'absolute_path' => $absolutePath,
                'available' => is_file($absolutePath),
            ];
        }, self::definitions());
    }

    /**
     * @return array{id: string, title: string, description: string, category: string, filename: string, absolute_path: string, available: bool}|null
     */
    public static function find(string $id): ?array
    {
        foreach (self::all() as $sample) {
            if ($sample['id'] === $id) {
                return $sample;
            }
        }

        return null;
    }
}
