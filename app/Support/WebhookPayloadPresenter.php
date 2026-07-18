<?php

namespace App\Support;

class WebhookPayloadPresenter
{
    private const REDACTED = '[REDACTED]';

    private const TRUNCATED = '[TRUNCATED]';

    private const MAX_DEPTH = 10;

    private const MAX_ITEMS_PER_LEVEL = 100;

    private const MAX_OUTPUT_BYTES = 50_000;

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'authorization',
        'proxyauthorization',
        'cookie',
        'setcookie',
        'password',
        'passwd',
        'pwd',
        'secret',
        'clientsecret',
        'apisecret',
        'apikey',
        'accesskey',
        'privatekey',
        'signature',
        'xsignature',
        'token',
        'accesstoken',
        'refreshtoken',
        'authtoken',
        'bearertoken',
        'webhooktoken',
    ];

    public function format(?array $payload): string
    {
        $json = json_encode(
            $this->redact($payload ?? []),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        if (! is_string($json)) {
            return '{}';
        }

        if (strlen($json) <= self::MAX_OUTPUT_BYTES) {
            return $json;
        }

        return mb_strcut($json, 0, self::MAX_OUTPUT_BYTES, 'UTF-8')
            ."\n".self::TRUNCATED;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, string>
     */
    public function highlights(?array $payload): array
    {
        if ($payload === null || $payload === []) {
            return [];
        }

        $keys = [
            'event_name',
            'event',
            'src',
            'dst',
            'did',
            'exten',
            'cuid',
            'unique_id',
            'uniqueid',
            'disposition',
            'state',
            'type',
            'direction',
            'participant',
            'resolved_extension',
            'billsec',
            'duration',
        ];

        $highlights = [];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if ($value === null || $value === '') {
                continue;
            }

            if (is_scalar($value)) {
                $highlights[$key] = (string) $value;
            }
        }

        return $highlights;
    }

    private function redact(mixed $value, int $depth = 0): mixed
    {
        if ($depth >= self::MAX_DEPTH) {
            return self::TRUNCATED;
        }

        if (is_array($value)) {
            $redacted = [];
            $itemCount = 0;

            foreach ($value as $key => $item) {
                if ($itemCount >= self::MAX_ITEMS_PER_LEVEL) {
                    $redacted['__truncated__'] = self::TRUNCATED;

                    break;
                }

                $redacted[$key] = is_string($key) && $this->isSensitiveKey($key)
                    ? self::REDACTED
                    : $this->redact($item, $depth + 1);
                $itemCount++;
            }

            return $redacted;
        }

        if (is_string($value)) {
            return $this->redactSensitiveUrlParameters($value);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));

        return in_array($normalized, self::SENSITIVE_KEYS, true)
            || str_ends_with($normalized, 'password')
            || str_ends_with($normalized, 'secret');
    }

    private function redactSensitiveUrlParameters(string $value): string
    {
        return (string) preg_replace_callback(
            '/([?&](?:token|access_token|api_key|apikey|signature|secret|password)=)[^&#\s]*/i',
            static fn (array $matches): string => $matches[1].self::REDACTED,
            $value,
        );
    }
}
