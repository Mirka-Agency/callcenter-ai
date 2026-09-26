<?php

namespace App\Support;

use App\Domain\Voip\Enums\CallStatus;

class UnconnectedCallSignals
{
    /**
     * True when this call must not be analyzed: it never became a conversation.
     * Includes outcomes that are still only ringing.
     */
    public static function isUnconnectedStatus(string $status): bool
    {
        return self::terminalStatus($status) !== null;
    }

    /**
     * A finished attempt that failed. Ringing is not finished, so ingestion must
     * keep treating it as call.started.
     */
    public static function failedToConnect(string $status): bool
    {
        return in_array(self::terminalStatus($status), [
            CallStatus::Missed,
            CallStatus::Busy,
            CallStatus::Failed,
            CallStatus::Cancelled,
        ], true);
    }

    public static function terminalStatus(string $status): ?CallStatus
    {
        $normalized = self::normalize($status);

        if ($normalized === '') {
            return null;
        }

        if (self::containsAny($normalized, ['noanswer', 'no answer', 'not answered', 'not answer', 'unanswered'])) {
            return CallStatus::Missed;
        }

        return match (true) {
            str_contains($normalized, 'busy') => CallStatus::Busy,
            str_contains($normalized, 'fail'), str_contains($normalized, 'congestion') => CallStatus::Failed,
            str_contains($normalized, 'cancel') => CallStatus::Cancelled,
            $normalized === 'initiated' => CallStatus::Initiated,
            str_contains($normalized, 'ring') => CallStatus::Ringing,
            in_array($normalized, ['missed', 'voicemail', 'voice mail', 'abandoned', 'abandon', 'rejected'], true),
            str_contains($normalized, 'miss'),
            str_contains($normalized, 'voicemail'),
            str_contains($normalized, 'abandon'),
            str_contains($normalized, 'reject') => CallStatus::Missed,
            default => null,
        };
    }

    /**
     * Billable talk time, when the payload actually reports it.
     * Zero means the other party never connected, even if ring time is long.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function explicitTalkSeconds(array $payload): ?int
    {
        foreach (['billsec', 'bill_sec', 'talk_time', 'talkTime', 'talksec', 'Billsec'] as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === '' || $payload[$key] === null) {
                continue;
            }

            if (! is_numeric($payload[$key])) {
                continue;
            }

            return max(0, (int) $payload[$key]);
        }

        return null;
    }

    private static function normalize(string $status): string
    {
        $status = strtolower(str_replace(['_', '-'], ' ', trim($status)));
        $status = preg_replace('/\s+/', ' ', $status) ?? $status;

        return trim($status);
    }

    /** @param  list<string>  $needles */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
