<?php

namespace App\Support;

class PersonalCall
{
    public const DEFAULT_REASON = 'موضوع مکالمه به کار سازمان مربوط نبود.';

    public static function isFlag(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }

        if (is_numeric($raw)) {
            return (int) $raw === 1;
        }

        if (is_string($raw)) {
            return in_array(mb_strtolower(trim($raw)), ['1', 'true', 'yes', 'بله', 'درست'], true);
        }

        return false;
    }

    public static function reason(mixed $raw, bool $isPersonal): string
    {
        if (! $isPersonal) {
            return '';
        }

        $reason = trim((string) $raw);

        if (mb_strlen($reason) > 400) {
            $reason = mb_substr($reason, 0, 400);
        }

        return $reason !== '' ? $reason : self::DEFAULT_REASON;
    }
}
