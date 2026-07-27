<?php

namespace App\Infrastructure\Voip\Adapters;

use App\Domain\Voip\DTOs\ExtensionData;
use App\Domain\Voip\DTOs\MakeCallData;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\ValueObjects\VoipOperationResult;
use App\Infrastructure\Voip\Ami\AsteriskAmiClient;
use App\Infrastructure\Voip\Support\WebhookPayloadNormalizer;

class CustomVoipAdapter extends AbstractVoipAdapter
{
    private const UNSUPPORTED = 'Operation is not supported by the custom VoIP adapter.';

    public function __construct(
        private WebhookPayloadNormalizer $normalizer = new WebhookPayloadNormalizer,
    ) {}

    public function getProviderCode(): VoipProviderCode
    {
        return VoipProviderCode::Custom;
    }

    public function testConnection(): VoipOperationResult
    {
        $ami = $this->config->settings->extra['ami'] ?? [];

        if (($ami['host'] ?? '') !== '') {
            return $this->testAmiConnection($ami);
        }

        return VoipOperationResult::success(
            message: 'Custom VoIP webhook provider is ready. Send POST requests to the inbound webhook URL.',
        );
    }

    /** @param array<string, mixed> $ami */
    private function testAmiConnection(array $ami): VoipOperationResult
    {
        $host = trim((string) ($ami['host'] ?? ''));
        $port = (int) ($ami['port'] ?? 5038);
        $username = trim((string) ($this->config->credentials->amiUsername ?? $this->config->credentials->username ?? ''));
        $password = (string) ($this->config->credentials->amiPassword ?? $this->config->credentials->password ?? '');

        if ($host === '' || $username === '' || $password === '') {
            return VoipOperationResult::failure('AMI host, username, and password are required.');
        }

        $client = new AsteriskAmiClient;

        try {
            $client->connect($host, $port, min(10, $this->config->settings->timeout));
            $client->login($username, $password);
            $client->disconnect();

            return VoipOperationResult::success(
                message: "AMI login successful on {$host}:{$port} as {$username}.",
            );
        } catch (\Throwable $exception) {
            return VoipOperationResult::failure('AMI connection failed: '.$exception->getMessage());
        }
    }

    public function makeCall(MakeCallData $call): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function hangupCall(string $callId): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function getCallDetails(string $callId): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function getCallRecording(string $callId): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function getActiveCalls(): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function createExtension(ExtensionData $extension): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function updateExtension(string $extensionId, ExtensionData $extension): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function getExtensions(): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function normalizeWebhook(array $payload): NormalizedWebhookEvent
    {
        return $this->normalizer->normalize(
            payload: $payload,
            fieldMapping: $this->config->settings->webhookFieldMapping,
            provider: $this->getProviderCode()->value,
        );
    }

    /** @return array<string, mixed> */
    public static function sampleWebhookPayload(): array
    {
        return [
            'event' => 'call.ended',
            'call_id' => 'call-123',
            'direction' => 'inbound',
            'from' => '09121234567',
            'to' => '101',
            'status' => 'completed',
            'recording_url' => 'http://10.0.0.20/recordings/call-123.wav',
            'started_at' => '2026-07-16T10:00:00+03:30',
            'ended_at' => '2026-07-16T10:05:00+03:30',
            'duration' => 300,
            'extension' => '101',
        ];
    }

    /** @return array{organization_id: int, caller_number: string, customer_phone: string, external_call_id: string, direction: string} */
    public static function sampleIncomingCallPayload(int $organizationId): array
    {
        return [
            'organization_id' => $organizationId,
            'caller_number' => '09121234567',
            'customer_phone' => '09121234567',
            'external_call_id' => '1730000000.1',
            'direction' => 'inbound',
        ];
    }

    public static function sampleCurlCommand(string $webhookUrl): string
    {
        $payload = json_encode(self::sampleWebhookPayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return <<<BASH
# Run this FROM the Asterisk server (must reach APP_URL on the LAN).
# Expect HTTP 202. Then check «تماس‌های اخیر» in the employer VoIP page.

curl -sS -w "\\nHTTP %{http_code}\\n" -X POST \\
  -H 'Content-Type: application/json' \\
  -d '{$payload}' \\
  '{$webhookUrl}'
BASH;
    }

    public static function sampleDialplan(string $webhookUrl): string
    {
        $escapedUrl = str_replace("'", "'\\''", $webhookUrl);

        return <<<DIALPLAN
; ============================================================
; Callcenter CDR webhook — paste into extensions.conf
; Asterisk may be on a DIFFERENT machine than the app server.
; The Asterisk host must reach the webhook URL over the LAN.
; ============================================================
; 1) Put this [callcenter-cdr] context somewhere loaded by Asterisk
; 2) In EVERY inbound context that handles agent calls, add:
;      same => n,Goto(callcenter-cdr,s,1)
;    OR include the hangup handler below with "exten => h"
; 3) dialplan reload
; ============================================================

[callcenter-cdr]
; Preferred: dedicated hangup handler (extension "h")
exten => h,1,NoOp(Callcenter CDR webhook)
 same => n,Set(CC_CALL_ID=\${UNIQUEID})
 same => n,Set(CC_FROM=\${CALLERID(num)})
 same => n,Set(CC_TO=\${CDR(dst)})
 same => n,Set(CC_STATUS=\${DIALSTATUS})
 same => n,Set(CC_DURATION=\${CDR(billsec)})
 same => n,Set(CC_RECORDING=)
 ; If you use MixMonitor, set an HTTP URL the APP server can download, e.g.:
 ; same => n,Set(CC_RECORDING=http://10.0.0.20/monitor/\${UNIQUEID}.wav)
 same => n,System(curl -sS -X POST -H 'Content-Type: application/json' --max-time 5 -d '{"event":"call.ended","call_id":"\${CC_CALL_ID}","direction":"inbound","from":"\${CC_FROM}","to":"\${CC_TO}","status":"\${CC_STATUS}","duration":\${CC_DURATION},"extension":"\${CC_TO}","recording_url":"\${CC_RECORDING}"}' '{$escapedUrl}')
 same => n,Hangup()

; Example inbound queue/context — ADD "exten => h" to YOUR real context:
; [from-trunk]
; exten => _X.,1,NoOp(Inbound)
;  same => n,Dial(PJSIP/101,30)
;  same => n,Hangup()
; exten => h,1,Goto(callcenter-cdr,h,1)
DIALPLAN;
    }

    public static function sampleRingingDialplan(string $incomingCallUrl, int $organizationId, string $webhookToken): string
    {
        $escapedUrl = str_replace("'", "'\\''", $incomingCallUrl);
        $escapedToken = str_replace("'", "'\\''", $webhookToken);

        return <<<DIALPLAN
; ============================================================
; Live agent popup (ringing) — OPTIONAL
; POST /api/voip/incoming-call when the call starts ringing.
; Put this BEFORE Dial() in your inbound context.
; ============================================================

exten => _X.,1,NoOp(Notify callcenter popup)
 same => n,System(curl -sS -X POST -H 'Content-Type: application/json' -H 'X-Voip-Webhook-Token: {$escapedToken}' --max-time 3 -d '{"organization_id":{$organizationId},"caller_number":"\${CALLERID(num)}","customer_phone":"\${CALLERID(num)}","external_call_id":"\${UNIQUEID}","direction":"inbound"}' '{$escapedUrl}')
 same => n,Dial(PJSIP/\${EXTEN},30)
 same => n,Hangup()
DIALPLAN;
    }

    public static function sampleIncomingCallCurl(string $incomingCallUrl, int $organizationId, string $webhookToken): string
    {
        $payload = json_encode(
            self::sampleIncomingCallPayload($organizationId),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        );

        return <<<BASH
# Live popup test (from Asterisk server). Expect HTTP 202.

curl -sS -w "\\nHTTP %{http_code}\\n" -X POST \\
  -H 'Content-Type: application/json' \\
  -H 'X-Voip-Webhook-Token: {$webhookToken}' \\
  -d '{$payload}' \\
  '{$incomingCallUrl}'
BASH;
    }

    public static function sampleManagerConfig(string $amiUsername = 'callcenter-ai', string $amiPassword = 'CHANGE_ME_STRONG_PASSWORD'): string
    {
        return <<<CONF
; Paste into /etc/asterisk/manager_custom.conf (Issabel / FreePBX)
; Then ensure manager.conf includes: #include manager_custom.conf
; Reload: asterisk -rx "manager reload"
; Verify: asterisk -rx "manager show user {$amiUsername}"

[{$amiUsername}]
secret = {$amiPassword}
deny=0.0.0.0/0.0.0.0
permit=10.0.0.0/255.0.0.0
read = system,call,log,verbose,command,agent,user,config,dtmf,reporting,cdr,dialplan
write = none
CONF;
    }

    public static function sampleAmiVerifyCommand(string $amiUsername = 'callcenter-ai'): string
    {
        return <<<BASH
# Verify AMI user exists and permissions (run on Asterisk server):
asterisk -rx "manager show user {$amiUsername}"

# Test TCP port from app server (replace HOST):
nc -zv HOST 5038
BASH;
    }

    public static function sampleShellHelper(string $webhookUrl): string
    {
        $escapedUrl = str_replace("'", "'\\''", $webhookUrl);

        return <<<BASH
#!/usr/bin/env bash
# Save as /usr/local/bin/callcenter-cdr.sh && chmod +x /usr/local/bin/callcenter-cdr.sh
# Dialplan:
#   same => n,System(/usr/local/bin/callcenter-cdr.sh "\${UNIQUEID}" "\${CALLERID(num)}" "\${CDR(dst)}" "\${DIALSTATUS}" "\${CDR(billsec)}" "\${CC_RECORDING}")

set -euo pipefail
WEBHOOK_URL='{$escapedUrl}'
CALL_ID="\${1:-}"
FROM="\${2:-}"
TO="\${3:-}"
STATUS="\${4:-}"
DURATION="\${5:-0}"
RECORDING="\${6:-}"

curl -sS -X POST -H 'Content-Type: application/json' --max-time 5 \\
  -d "\$(jq -n \\
    --arg call_id "\$CALL_ID" \\
    --arg from "\$FROM" \\
    --arg to "\$TO" \\
    --arg status "\$STATUS" \\
    --arg recording_url "\$RECORDING" \\
    --argjson duration "\$DURATION" \\
    '{event:"call.ended",call_id:\$call_id,direction:"inbound",from:\$from,to:\$to,status:\$status,duration:\$duration,extension:\$to,recording_url:\$recording_url}')" \\
  "\$WEBHOOK_URL"
BASH;
    }
}
