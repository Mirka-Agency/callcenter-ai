<?php

namespace App\Support;

class PersonName
{
    /** @var list<string> */
    private const TITLES = [
        'آقای',
        'آقا',
        'خانم',
        'مهندس',
        'دکتر',
        'جناب',
    ];

    /**
     * Stable key for a full person name.
     * Single given names are ignored so common names are not merged together.
     */
    public static function matchKey(?string $name): ?string
    {
        $normalized = self::normalize($name);

        if ($normalized === null || ! str_contains($normalized, ' ') || mb_strlen($normalized) < 5) {
            return null;
        }

        return $normalized;
    }

    /** @return list<string> */
    public static function lookupNames(?string $name): array
    {
        $key = self::matchKey($name);

        if ($key === null) {
            return [];
        }

        $names = [trim((string) $name), $key];

        foreach (self::TITLES as $title) {
            $names[] = $title.' '.$key;
        }

        $unique = [];

        foreach ($names as $candidate) {
            $candidate = trim($candidate);

            if ($candidate !== '') {
                $unique[$candidate] = $candidate;
            }
        }

        return array_values($unique);
    }

    public static function normalize(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $name = str_replace(
            ['ي', 'ى', 'ك', 'ۀ', 'ة', 'ؤ', 'أ', 'إ', 'ٱ'],
            ['ی', 'ی', 'ک', 'ه', 'ه', 'و', 'ا', 'ا', 'ا'],
            $name,
        );
        $name = mb_strtolower($name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = preg_replace('/^(?:'.implode('|', self::TITLES).')\s+/u', '', $name) ?? $name;
        $name = trim($name);

        return $name !== '' ? $name : null;
    }
}
