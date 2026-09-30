<?php

namespace App\Services;

use App\Models\Call;
use App\Models\ConversationAnalysis;

class CustomerPhoneResolver
{
    public function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits !== '' ? $digits : null;
    }

    /**
     * Digit forms that should be treated as the same Iranian number.
     * 0912…, 912…, +98 912… and 0098 912… match each other.
     *
     * @return list<string>
     */
    public function equivalentKeys(?string $phone): array
    {
        $digits = $this->normalize($phone);

        if ($digits === null) {
            return [];
        }

        $keys = [$digits];
        $national = $this->iranNationalNumber($digits);

        if ($national !== null) {
            $keys[] = $national;
            $keys[] = '0'.$national;
            $keys[] = '98'.$national;
            $keys[] = '0098'.$national;
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  list<string|null>  $phones
     * @return list<string>
     */
    public function equivalentKeysForMany(array $phones): array
    {
        $keys = [];

        foreach ($phones as $phone) {
            foreach ($this->equivalentKeys($phone) as $key) {
                $keys[$key] = $key;
            }
        }

        return array_values($keys);
    }

    public function resolveFromCall(Call $call): ?string
    {
        $candidates = match ($call->direction) {
            'inbound' => [$call->caller_number, $call->customer_phone],
            'outbound' => [$call->receiver_number, $call->customer_phone],
            default => [$call->customer_phone, $call->caller_number, $call->receiver_number],
        };

        foreach ($candidates as $candidate) {
            if ($this->normalize($candidate)) {
                return trim((string) $candidate);
            }
        }

        return null;
    }

    public function resolveFromAnalysis(ConversationAnalysis $analysis): ?string
    {
        $call = $analysis->call;
        if ($call) {
            return $this->resolveFromCall($call);
        }

        $identityPhone = trim((string) ($analysis->customer_identity_json['phone_number'] ?? ''));

        return $identityPhone !== '' ? $identityPhone : null;
    }

    private function iranNationalNumber(string $digits): ?string
    {
        $national = $digits;

        if (str_starts_with($national, '0098')) {
            $national = substr($national, 4);
        } elseif (str_starts_with($national, '98') && strlen($national) >= 12) {
            $national = substr($national, 2);
        }

        if (str_starts_with($national, '0')) {
            $national = substr($national, 1);
        }

        if (strlen($national) !== 10) {
            return null;
        }

        return $national;
    }
}
