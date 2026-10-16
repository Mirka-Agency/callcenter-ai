<?php

namespace App\Application\Customer;

use App\Models\ConversationAnalysis;
use App\Services\CustomerPhoneResolver;
use App\Services\Reports\DefinedExtensionCallConstraint;
use App\Support\PhoneInspection;

/**
 * Picks the external party from call metadata only.
 * Spoken numbers from the model are never the identity key.
 */
class ExternalPartyPhoneResolver
{
    public function __construct(
        private CustomerPhoneResolver $phones,
        private DefinedExtensionCallConstraint $extensions,
    ) {}

    public function resolve(ConversationAnalysis $analysis): PhoneInspection
    {
        $call = $analysis->call;

        if (! $call) {
            return new PhoneInspection(null, null, null, false, 'missing_call');
        }

        $raw = match ($call->direction) {
            'inbound' => $call->caller_number,
            'outbound' => $call->receiver_number,
            default => null,
        };

        if ($raw === null || trim((string) $raw) === '') {
            return new PhoneInspection($raw, null, null, false, 'missing_external_party');
        }

        $inspection = $this->phones->inspect($raw);

        if (! $inspection->isValid) {
            return $inspection;
        }

        $counterparty = match ($call->direction) {
            'inbound' => $call->receiver_number,
            'outbound' => $call->caller_number,
            default => null,
        };

        if ($this->overlaps($raw, $counterparty) || $this->belongsToOrganization((int) $analysis->organization_id, $raw)) {
            return new PhoneInspection($raw, $inspection->normalized, $inspection->country, false, 'organization_number');
        }

        return $inspection;
    }

    private function overlaps(?string $left, ?string $right): bool
    {
        $leftKeys = $this->phones->equivalentKeys($left);
        $rightKeys = $this->phones->equivalentKeys($right);

        return $leftKeys !== [] && array_intersect($leftKeys, $rightKeys) !== [];
    }

    private function belongsToOrganization(int $organizationId, string $phone): bool
    {
        $keys = $this->phones->equivalentKeys($phone);

        foreach ($this->extensions->extensionNumbersByConnection($organizationId) as $numbers) {
            foreach ($numbers as $number) {
                if (array_intersect($keys, $this->phones->equivalentKeys((string) $number)) !== []) {
                    return true;
                }
            }
        }

        return false;
    }
}
