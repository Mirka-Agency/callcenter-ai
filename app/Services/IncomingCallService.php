<?php

namespace App\Services;

use App\Domain\Call\Enums\IncomingCallStatus;
use App\Events\IncomingCallReceived;
use App\Models\IncomingCallSession;
use App\Models\Organization;

class IncomingCallService
{
    public function __construct(
        private CustomerIntelligenceContextBuilder $contextBuilder,
    ) {}

    public function register(array $payload): IncomingCallSession
    {
        $organizationId = (int) $payload['organization_id'];
        $callerNumber = (string) ($payload['caller_number'] ?? $payload['from'] ?? '');
        $customerPhone = (string) ($payload['customer_phone'] ?? $callerNumber);
        $customerName = $payload['customer_name'] ?? null;
        $externalCallId = $payload['external_call_id'] ?? $payload['call_id'] ?? null;

        if (! $callerNumber) {
            throw new \InvalidArgumentException('caller_number is required.');
        }

        Organization::query()->findOrFail($organizationId);

        if ($externalCallId) {
            $existing = IncomingCallSession::query()
                ->where('organization_id', $organizationId)
                ->where('external_call_id', $externalCallId)
                ->where('status', IncomingCallStatus::Ringing)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // Simotel often sends IncomingCall twice: once with cuid, once without.
        // Collapse same-number rings within the popup window.
        if ($callerNumber !== '') {
            $existingByNumber = IncomingCallSession::query()
                ->where('organization_id', $organizationId)
                ->where('caller_number', $callerNumber)
                ->where('status', IncomingCallStatus::Ringing)
                ->where('ring_started_at', '>=', now()->subMinutes(5))
                ->latest('id')
                ->first();

            if ($existingByNumber) {
                if ($externalCallId && blank($existingByNumber->external_call_id)) {
                    $existingByNumber->update([
                        'external_call_id' => $externalCallId,
                        'voip_call_log_id' => $payload['voip_call_log_id'] ?? $existingByNumber->voip_call_log_id,
                        'organization_voip_connection_id' => $payload['organization_voip_connection_id']
                            ?? $existingByNumber->organization_voip_connection_id,
                    ]);
                }

                return $existingByNumber->fresh() ?? $existingByNumber;
            }
        }

        $context = $this->contextBuilder->build($organizationId, $customerPhone, $customerName);

        $session = IncomingCallSession::query()->create([
            'organization_id' => $organizationId,
            'organization_voip_connection_id' => $payload['organization_voip_connection_id'] ?? null,
            'voip_call_log_id' => $payload['voip_call_log_id'] ?? null,
            'external_call_id' => $externalCallId,
            'caller_number' => $callerNumber,
            'customer_name' => $context['customer_name'] ?? $customerName,
            'customer_phone' => $customerPhone,
            'direction' => $payload['direction'] ?? 'inbound',
            'status' => IncomingCallStatus::Ringing,
            'customer_context_json' => $context['customer_context'],
            'recommended_actions_json' => $context['recommended_actions'],
            'recent_actions_json' => $context['recent_actions'],
            'customer_timeline_json' => $context['timeline'],
            'ring_started_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        $session->load('organization');

        try {
            broadcast(new IncomingCallReceived($session));
        } catch (\Throwable $e) {
            report($e);
        }

        return $session;
    }
}
