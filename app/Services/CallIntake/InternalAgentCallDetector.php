<?php

namespace App\Services\CallIntake;

use App\Domain\Call\Enums\ConversationSource;
use App\Models\Call;
use App\Models\VoipCallLog;
use App\Services\Reports\DefinedExtensionCallConstraint;

class InternalAgentCallDetector
{
    public function __construct(private DefinedExtensionCallConstraint $extensions) {}

    /**
     * Both parties are extensions registered to employees of this organization.
     */
    public function matches(Call $call): bool
    {
        if ($call->source !== ConversationSource::Voip) {
            return false;
        }

        $call->loadMissing('voipCallLog');
        $log = $call->voipCallLog;
        $connectionId = (int) ($log?->organization_voip_connection_id ?? $call->organization_voip_connection_id);
        $numbers = $this->numbersFor((int) $call->organization_id, $connectionId);

        if ($numbers === []) {
            return false;
        }

        $source = trim((string) ($log?->source_number ?? $call->caller_number));
        $destination = trim((string) ($log?->destination_number ?? $call->receiver_number));

        return $source !== ''
            && $destination !== ''
            && in_array($source, $numbers, true)
            && in_array($destination, $numbers, true);
    }

    public function matchesLog(VoipCallLog $log): bool
    {
        $numbers = $this->numbersFor((int) $log->organization_id, (int) $log->organization_voip_connection_id);

        if ($numbers === []) {
            return false;
        }

        $source = trim((string) $log->source_number);
        $destination = trim((string) $log->destination_number);

        return $source !== ''
            && $destination !== ''
            && in_array($source, $numbers, true)
            && in_array($destination, $numbers, true);
    }

    /**
     * @return list<string>
     */
    private function numbersFor(int $organizationId, int $connectionId): array
    {
        $sets = $this->extensions->extensionNumbersByConnection($organizationId);

        if ($connectionId > 0 && isset($sets[$connectionId])) {
            return $sets[$connectionId];
        }

        return array_values(array_unique(array_merge(...array_values($sets ?: [[]]))));
    }
}
