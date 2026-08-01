<?php

namespace App\Application\Voip\Services;

class AsteriskAmiCallTracker
{
    /** @var array<string, array<string, mixed>> */
    private array $calls = [];

    /**
     * @param  array<string, string>  $event
     * @return array<string, mixed>|null Final webhook-style payload when call should be ingested.
     */
    public function handle(array $event): ?array
    {
        $eventName = strtolower($event['Event'] ?? '');

        return match ($eventName) {
            'newchannel' => $this->handleNewChannel($event),
            'newstate' => $this->handleNewState($event),
            'dial', 'dialend' => $this->handleDial($event),
            'bridge', 'bridgeenter' => $this->handleBridge($event),
            'bridgeleave' => $this->handleBridgeLeave($event),
            'hangup' => $this->finalizeFromHangup($event),
            'cdr' => $this->finalizeFromCdr($event),
            default => null,
        };
    }

    /** @param array<string, string> $event */
    private function handleNewChannel(array $event): ?array
    {
        $call = $this->touch($event);
        $call['caller'] = $call['caller'] ?? $this->firstNonEmpty($event, ['CallerIDNum', 'CallerIDnum']);
        $call['context'] = $call['context'] ?? ($event['Context'] ?? null);
        $call['channel'] = $call['channel'] ?? ($event['Channel'] ?? null);
        $call['exten'] = $call['exten'] ?? ($event['Exten'] ?? null);

        $this->store($call);

        return null;
    }

    /** @param array<string, string> $event */
    private function handleNewState(array $event): ?array
    {
        $call = $this->touch($event);

        if (($event['ChannelStateDesc'] ?? '') === 'Up') {
            $call['answered'] = true;
        }

        $this->store($call);

        return null;
    }

    /** @param array<string, string> $event */
    private function handleDial(array $event): ?array
    {
        $call = $this->touch($event);
        $dialStatus = strtoupper((string) ($event['DialStatus'] ?? $event['Dialstatus'] ?? ''));

        if ($dialStatus === 'ANSWER') {
            $call['answered'] = true;
            $call['extension'] = $call['extension']
                ?? $this->firstNonEmpty($event, ['DestCallerIDNum', 'ConnectedLineNum', 'DialString', 'DestExten']);
        }

        $this->store($call);

        return null;
    }

    /** @param array<string, string> $event */
    private function handleBridge(array $event): ?array
    {
        $call = $this->touch($event);
        $call['answered'] = true;

        foreach ([$event['CallerID2'] ?? null, $event['ConnectedLine2'] ?? null, $event['Exten2'] ?? null] as $candidate) {
            if ($this->isLikelyExtension((string) $candidate)) {
                $call['extension'] = (string) $candidate;
                break;
            }
        }

        $this->store($call);

        return null;
    }

    /** @param array<string, string> $event */
    private function handleBridgeLeave(array $event): ?array
    {
        $this->touch($event);

        return null;
    }

    /** @param array<string, string> $event */
    private function finalizeFromHangup(array $event): ?array
    {
        $call = $this->touch($event);
        $call['ended'] = true;
        $call['hangup_cause'] = $event['Cause-txt'] ?? $event['Cause'] ?? null;
        $call['duration'] = $call['duration'] ?? $this->intOrNull($event['BillableSeconds'] ?? $event['Duration'] ?? null);

        $this->store($call);

        if ($call['ingested'] ?? false) {
            return null;
        }

        $payload = $this->buildPayload($call, $event);
        $call['ingested'] = true;
        $this->store($call);

        return $payload;
    }

    /** @param array<string, string> $event */
    private function finalizeFromCdr(array $event): ?array
    {
        $key = $this->resolveKey($event);
        $call = $this->calls[$key] ?? $this->baseCall($event);

        $call['caller'] = $call['caller'] ?? $this->firstNonEmpty($event, ['Source', 'CallerID', 'src']);
        $call['destination'] = $call['destination'] ?? $this->firstNonEmpty($event, ['Destination', 'dst']);
        $call['extension'] = $call['extension'] ?? $this->resolveExtension($call, $event);
        $call['duration'] = $this->intOrNull($event['Billsec'] ?? $event['billsec'] ?? null) ?? $call['duration'] ?? 0;
        $call['disposition'] = $event['Disposition'] ?? $call['disposition'] ?? null;
        $call['answered'] = strtoupper((string) ($call['disposition'] ?? '')) === 'ANSWERED' || ($call['answered'] ?? false);
        $call['ended'] = true;

        if ($call['ingested'] ?? false) {
            $this->store($call);

            return null;
        }

        $payload = $this->buildPayload($call, $event);
        $call['ingested'] = true;
        $this->store($call);

        return $payload;
    }

    /** @param array<string, mixed> $call @param array<string, string> $event */
    private function buildPayload(array $call, array $event): array
    {
        $callId = (string) ($call['linkedid'] ?? $call['uniqueid'] ?? $event['Uniqueid'] ?? $event['UniqueID'] ?? '');
        $caller = (string) ($call['caller'] ?? $this->firstNonEmpty($event, ['CallerIDNum', 'Source', 'src']) ?? '');
        $destination = (string) ($call['destination'] ?? $call['exten'] ?? $this->firstNonEmpty($event, ['Exten', 'Destination', 'dst']) ?? '');
        $direction = $this->inferDirection($call, $event);
        $extension = (string) ($call['extension'] ?? $this->resolveExtension($call, $event, $direction) ?? '');
        $duration = (int) ($call['duration'] ?? 0);
        $status = (string) ($call['disposition'] ?? $call['hangup_cause'] ?? ($call['answered'] ?? false ? 'ANSWERED' : 'NO ANSWER'));

        if ($extension === '' && $direction === 'outbound' && $this->isLikelyExtension($caller)) {
            $extension = $caller;
        }

        if ($extension === '' && $direction === 'inbound' && $this->isLikelyExtension($destination)) {
            $extension = $destination;
        }

        return [
            'event' => 'call.ended',
            'call_id' => $callId,
            'uniqueid' => $call['uniqueid'] ?? null,
            'linkedid' => $call['linkedid'] ?? null,
            'direction' => $direction,
            'from' => $caller,
            'to' => $destination !== '' ? $destination : $extension,
            'extension' => $extension !== '' ? $extension : null,
            'status' => $status,
            'duration' => $duration,
            'started_at' => $call['started_at'] ?? null,
            'ended_at' => $call['ended_at'] ?? null,
            'ami_event' => $event['Event'] ?? null,
        ];
    }

    /** @param array<string, string> $event */
    private function touch(array $event): array
    {
        $key = $this->resolveKey($event);
        $call = $this->calls[$key] ?? $this->baseCall($event);

        $call['caller'] = $call['caller'] ?? $this->firstNonEmpty($event, ['CallerIDNum', 'CallerIDnum', 'Source']);
        $call['destination'] = $call['destination'] ?? $this->firstNonEmpty($event, ['ConnectedLineNum', 'Exten', 'Destination']);
        $call['extension'] = $call['extension'] ?? $this->resolveExtension($call, $event);

        return $call;
    }

    /** @param array<string, mixed> $call */
    private function store(array $call): void
    {
        $key = (string) ($call['key'] ?? $call['linkedid'] ?? $call['uniqueid'] ?? '');

        if ($key === '') {
            return;
        }

        $this->calls[$key] = $call;

        if (isset($call['uniqueid']) && $call['uniqueid'] !== $key) {
            $this->calls[(string) $call['uniqueid']] = $call;
        }
    }

    /** @param array<string, string> $event */
    private function baseCall(array $event): array
    {
        $uniqueid = $event['Uniqueid'] ?? $event['UniqueID'] ?? null;
        $linkedid = $event['Linkedid'] ?? $event['LinkedID'] ?? $uniqueid;

        return [
            'key' => $this->resolveKey($event),
            'uniqueid' => $uniqueid,
            'linkedid' => $linkedid,
            'answered' => false,
            'ingested' => false,
        ];
    }

    /** @param array<string, string> $event */
    private function resolveKey(array $event): string
    {
        return (string) ($event['Linkedid'] ?? $event['LinkedID'] ?? $event['Uniqueid'] ?? $event['UniqueID'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $call
     * @param  array<string, string>  $event
     */
    private function resolveExtension(array $call, array $event, ?string $direction = null): ?string
    {
        $direction ??= $this->inferDirection($call, $event);

        if ($direction === 'outbound') {
            $outboundCandidates = [
                $call['extension'] ?? null,
                $call['caller'] ?? null,
                $event['CallerIDNum'] ?? null,
                $event['Source'] ?? null,
                $event['src'] ?? null,
            ];

            foreach ($outboundCandidates as $candidate) {
                if ($this->isLikelyExtension((string) $candidate)) {
                    return (string) $candidate;
                }
            }
        }

        $candidates = [
            $call['extension'] ?? null,
            $event['ConnectedLineNum'] ?? null,
            $event['DestCallerIDNum'] ?? null,
            $event['DialString'] ?? null,
            $event['DestExten'] ?? null,
            $event['Exten'] ?? null,
            $call['destination'] ?? null,
            $event['Destination'] ?? null,
            $event['dst'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($this->isLikelyExtension((string) $candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $call @param array<string, string> $event */
    private function inferDirection(array $call, array $event): string
    {
        $context = strtolower((string) ($call['context'] ?? $event['Context'] ?? ''));

        if (str_contains($context, 'from-trunk') || str_contains($context, 'from-pstn') || str_contains($context, 'ext-queues')) {
            return 'inbound';
        }

        if (
            str_contains($context, 'from-internal')
            || str_contains($context, 'macro-dialout')
            || str_contains($context, 'outbound')
            || str_contains($context, 'dialout')
            || str_contains($context, 'out-trunk')
        ) {
            return 'outbound';
        }

        $caller = (string) ($call['caller'] ?? $this->firstNonEmpty($event, ['CallerIDNum', 'Source', 'src']) ?? '');
        $destination = (string) ($call['destination'] ?? $call['exten'] ?? $this->firstNonEmpty($event, ['Exten', 'Destination', 'dst']) ?? '');
        $extension = (string) ($call['extension'] ?? '');

        if ($this->isLikelyExtension($caller) && ($this->isLikelyMobile($destination) || ! $this->isLikelyExtension($destination))) {
            return 'outbound';
        }

        if ($this->isLikelyExtension($extension) && ! $this->isLikelyMobile($caller)) {
            return 'inbound';
        }

        if ($this->isLikelyExtension($caller) && ! $this->isLikelyMobile($extension)) {
            return 'outbound';
        }

        return 'inbound';
    }

    private function isLikelyExtension(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || ! ctype_digit($value)) {
            return false;
        }

        $length = strlen($value);

        return $length >= 2 && $length <= 6;
    }

    private function isLikelyMobile(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 10;
    }

    /** @param array<string, string> $event @param list<string> $keys */
    private function firstNonEmpty(array $event, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($event[$key] ?? ''));

            if ($value !== '' && strtolower($value) !== '<unknown>') {
                return $value;
            }
        }

        return null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
