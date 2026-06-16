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
                'title' => 'پیگیری پیشنهاد فروش',
                'description' => 'پیگیری پس از ارسال پیشنهاد قیمت به مشتری بالقوه',
                'category' => 'فروش',
                'filename' => '01-sales-follow-up.mp3',
            ],
            [
                'id' => 'sales-subscription-renewal',
                'title' => 'فروش و تمدید اشتراک',
                'description' => 'تماس فروش برای ترغیب مشتری به تمدید یا ارتقای اشتراک',
                'category' => 'فروش',
                'filename' => '02-sales-subscription-renewal.mp3',
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
