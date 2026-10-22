<?php

namespace App\Application\Blog\Support;

use Illuminate\Validation\ValidationException;

final class SafeUrlValidator
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    public function optional(?string $url, string $field, int $max = 1000): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (mb_strlen($url) > $max) {
            throw ValidationException::withMessages([
                $field => __('filament.blog.validation.url_too_long'),
            ]);
        }

        if (! $this->sanitizer->isSafeUrl($url)) {
            throw ValidationException::withMessages([
                $field => __('filament.blog.validation.unsafe_url'),
            ]);
        }

        return $url;
    }
}
