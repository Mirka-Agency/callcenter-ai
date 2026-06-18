<?php

namespace App\Support;

use InvalidArgumentException;

class SampleConversationAnalysisCache
{
    private const DIRECTORY = 'data/sample-analyses';

    public static function has(string $sampleId): bool
    {
        return is_file(self::path($sampleId));
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $sampleId): array
    {
        $path = self::path($sampleId);

        if (! is_file($path)) {
            throw new InvalidArgumentException("Cached sample analysis [{$sampleId}] was not found.");
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return $payload;
    }

    private static function path(string $sampleId): string
    {
        return database_path(self::DIRECTORY.'/'.$sampleId.'.json');
    }
}
