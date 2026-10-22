<?php

namespace App\Application\Blog\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class BlogMediaUrl
{
    public static function toPublicUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (Str::startsWith($value, ['http://', 'https://', '/storage/'])) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }

    public static function fromUploadState(mixed $state): ?string
    {
        if ($state === null || $state === '' || $state === []) {
            return null;
        }

        if (is_array($state)) {
            $state = array_values($state)[0] ?? null;
        }

        if (! is_string($state) || trim($state) === '') {
            return null;
        }

        return self::toPublicUrl(trim($state));
    }

    public static function toFormState(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (Str::startsWith($url, '/storage/')) {
            return ltrim(Str::after($url, '/storage/'), '/');
        }

        $publicRoot = rtrim((string) Storage::disk('public')->url(''), '/').'/';
        if (Str::startsWith($url, $publicRoot)) {
            return Str::after($url, $publicRoot);
        }

        return null;
    }
}
