<?php

namespace App\Application\Blog\Support;

final class Normalizer
{
    public static function normalize(string $value): string
    {
        $value = trim($value);
        $value = str_replace(["\u{200C}", 'ي', 'ك'], [' ', 'ی', 'ک'], $value);

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
