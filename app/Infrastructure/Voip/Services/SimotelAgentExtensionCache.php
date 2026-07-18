<?php

namespace App\Infrastructure\Voip\Services;

use Illuminate\Support\Facades\Cache;

class SimotelAgentExtensionCache
{
    public const TTL_SECONDS = 3600;

    public function store(int $connectionId, string $callId, string $extension): void
    {
        $callId = trim($callId);
        $extension = trim($extension);

        if ($callId === '' || $extension === '') {
            return;
        }

        Cache::put($this->key($connectionId, $callId), $extension, self::TTL_SECONDS);
    }

    public function get(int $connectionId, string $callId): ?string
    {
        $callId = trim($callId);

        if ($callId === '') {
            return null;
        }

        $extension = Cache::get($this->key($connectionId, $callId));

        return is_string($extension) && $extension !== '' ? $extension : null;
    }

    private function key(int $connectionId, string $callId): string
    {
        return "simotel:agent:{$connectionId}:{$callId}";
    }
}
