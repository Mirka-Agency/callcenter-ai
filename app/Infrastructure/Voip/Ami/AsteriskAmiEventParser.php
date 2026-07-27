<?php

namespace App\Infrastructure\Voip\Ami;

class AsteriskAmiEventParser
{
    /**
     * @return array<string, string>
     */
    public function parseBlock(string $block): array
    {
        $event = [];

        foreach (preg_split('/\r\n|\n|\r/', trim($block)) as $line) {
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $event[trim($key)] = trim($value);
        }

        return $event;
    }

    public function eventName(array $event): string
    {
        return strtolower($event['Event'] ?? $event['EventName'] ?? '');
    }

    public function isResponse(array $event): bool
    {
        return isset($event['Response']);
    }

    public function isSuccess(array $event): bool
    {
        return ($event['Response'] ?? '') === 'Success';
    }
}
