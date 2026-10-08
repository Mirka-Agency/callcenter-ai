<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\Cache;

/**
 * Bumped when a call is analyzed so rolling 30-day counts are not served from
 * a cache filled before that analysis existed.
 */
class AnalyzedCallVolumeRevision
{
    public static function token(int $organizationId): string
    {
        return (string) Cache::get(self::key($organizationId), 0);
    }

    public static function bump(int $organizationId): void
    {
        $key = self::key($organizationId);

        Cache::forever($key, (int) Cache::get($key, 0) + 1);
    }

    private static function key(int $organizationId): string
    {
        return 'analyzed-call-volume-revision:'.$organizationId;
    }
}
