<?php

namespace App\Infrastructure\Voip\Adapters;

use App\Contracts\ProvidesEmployeeIntegrationMeta;
use App\Domain\Voip\DTOs\ExtensionData;
use App\Domain\Voip\DTOs\MakeCallData;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Domain\Voip\ValueObjects\VoipOperationResult;
use App\Infrastructure\Voip\Clients\SimotelApiClient;
use App\Infrastructure\Voip\Services\SimotelAgentExtensionCache;
use Carbon\Carbon;

class SimotelVoipAdapter extends AbstractVoipAdapter implements ProvidesEmployeeIntegrationMeta
{
    public const RECORDING_URL_PREFIX = 'simotel://';

    private const UNSUPPORTED = 'Operation is not supported by the Simotel VoIP adapter.';

    private const DID_DIGIT_THRESHOLD = 8;

    private ?SimotelApiClient $client = null;

    private ?SimotelAgentExtensionCache $agentCache = null;

    /** @var array<string, VoipOperationResult> */
    private array $callDetailsCache = [];

    public static function employeeIntegrationMetaDefinitions(): array
    {
        return [
            [
                'key' => 'extension',
                'name' => 'شماره داخلی',
                'field_type' => 'text',
                'is_required' => true,
                'placeholder' => '101',
                'help_text' => 'داخلی واقعی سیموتل (مثل 101). برای کارشناس فقط تلفن فیزیکی، همان شماره فیزیکی یا مقدار نگاشت داخلی را ثبت کنید — نه DID کل سازمان مگر مالک آن خط باشد.',
                'sort_order' => 1,
            ],
        ];
    }

    public function getProviderCode(): VoipProviderCode
    {
        return VoipProviderCode::Simotel;
    }

    public function testConnection(): VoipOperationResult
    {
        // Prefer quick/search (official report API) over legacy reports/CDR.
        $response = $this->client()->post('reports/quick/search', [
            'conditions' => [
                'from' => '',
                'to' => '',
                'cuid' => '',
            ],
            'date_range' => [
                'from' => now()->subMinute()->format('Y-m-d H:i'),
                'to' => now()->format('Y-m-d H:i'),
            ],
            'pagination' => [
                'start' => 0,
                'count' => 1,
                'sorting' => (object) [],
            ],
            'alike' => 'true',
        ]);

        $json = $response->json();

        if ($this->isAccessDeniedPayload(is_array($json) ? $json : null)) {
            return VoipOperationResult::failure(
                error: 'Simotel API key is valid but Access denied for reports/quick/search. '
                    .'In Simotel: enable permission reports/quick for this API user, and set username/password (Basic Auth) if required.',
                data: array_merge(is_array($json) ? $json : [], ['denied' => true, 'permission' => 'reports/quick']),
            );
        }

        if ($response->failed() || $this->isSimotelFailurePayload($json)) {
            return $this->parseHttpFailure(
                message: is_array($json)
                    ? (string) ($json['message'] ?? $json['error'] ?? $response->body())
                    : $response->body(),
                data: is_array($json) ? $json : null,
            );
        }

        return VoipOperationResult::success(
            message: 'Simotel VoIP connection successful.',
            data: is_array($json) ? $json : [],
        );
    }

    public function makeCall(MakeCallData $call): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    public function hangupCall(string $callId): VoipOperationResult
    {
        return VoipOperationResult::failure(self::UNSUPPORTED);
    }

    /**
     * Resolve call details via official Quick Search by cuid, then Quick Info.
     * Optionally widen with date_range / caller when cuid-only returns empty (common for delayed CDR index).
     *
     * @see https://simotel.com/wiki/fa/developers/simotelapi/v4/report/quick_search/
     */
    public function getCallDetails(string $callId): VoipOperationResult
    {
        return $this->callDetailsCache[$callId] ??= $this->fetchCallDetails($callId);
    }

    /**
     * @param  array{started_at?: ?\DateTimeInterface|string|null, source_number?: ?string}  $context
     */
    public function getCallDetailsWithContext(string $callId, array $context = []): VoipOperationResult
    {
        $cacheKey = $callId.'|'.md5((string) json_encode($context));

        return $this->callDetailsCache[$cacheKey] ??= $this->fetchCallDetails($callId, $context);
    }

    /** @param  array{started_at?: mixed, source_number?: ?string}  $context */
    private function fetchCallDetails(string $callId, array $context = []): VoipOperationResult
    {
        // Docs: for call lookup by cuid, omit date_range and only pass cuid.
        // Docs also use X-APIKEY + Basic Auth together.
        $search = $this->client()->post('reports/quick/search', [
            'conditions' => [
                'from' => '',
                'to' => '',
                'cuid' => $callId,
            ],
            'pagination' => [
                'start' => 0,
                'count' => 20,
                'sorting' => (object) [],
            ],
            'alike' => 'true',
        ]);

        $searchJson = $search->json();
        if ($this->isAccessDeniedPayload(is_array($searchJson) ? $searchJson : null)) {
            return VoipOperationResult::failure(
                error: (string) ($searchJson['message'] ?? 'Access denied: /reports/quick/search'),
                data: [
                    'denied' => true,
                    'permission' => 'reports/quick',
                    'quick_search' => $searchJson,
                ],
            );
        }

        if ($search->successful()
            && ! $this->isSimotelFailurePayload(is_array($searchJson) ? $searchJson : null)
            && $this->responseHasCallRows(is_array($searchJson) ? $searchJson : null)) {
            return VoipOperationResult::success(
                externalId: $callId,
                data: $this->normalizeCallDetailsPayload($searchJson ?? []),
                message: 'Call details retrieved from Simotel quick/search.',
            );
        }

        $around = $this->normalizeTimestamp($context['started_at'] ?? null);
        if ($around !== null) {
            try {
                $center = Carbon::parse($around);
                $ranged = $this->client()->post('reports/quick/search', [
                    'conditions' => [
                        'from' => '',
                        'to' => '',
                        'cuid' => $callId,
                    ],
                    'date_range' => [
                        'from' => $center->copy()->subHours(6)->format('Y-m-d H:i'),
                        'to' => $center->copy()->addHours(6)->format('Y-m-d H:i'),
                    ],
                    'pagination' => [
                        'start' => 0,
                        'count' => 20,
                        'sorting' => (object) [],
                    ],
                    'alike' => 'true',
                ]);

                if ($ranged->successful()
                    && ! $this->isSimotelFailurePayload($ranged->json())
                    && $this->responseHasCallRows($ranged->json())) {
                    return VoipOperationResult::success(
                        externalId: $callId,
                        data: $this->normalizeCallDetailsPayload($ranged->json() ?? []),
                        message: 'Call details retrieved from Simotel quick/search (date_range).',
                    );
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        $sourceNumber = isset($context['source_number']) ? trim((string) $context['source_number']) : '';
        if ($sourceNumber !== '' && $around !== null) {
            try {
                $center = Carbon::parse($around);
                $byNumber = $this->client()->post('reports/quick/search', [
                    'conditions' => [
                        'from' => $sourceNumber,
                        'to' => '',
                        'cuid' => '',
                    ],
                    'date_range' => [
                        'from' => $center->copy()->subMinutes(30)->format('Y-m-d H:i'),
                        'to' => $center->copy()->addMinutes(30)->format('Y-m-d H:i'),
                    ],
                    'pagination' => [
                        'start' => 0,
                        'count' => 50,
                        'sorting' => (object) [],
                    ],
                    'alike' => 'true',
                ]);

                if ($byNumber->successful()
                    && ! $this->isSimotelFailurePayload($byNumber->json())
                    && $this->responseHasCallRows($byNumber->json())) {
                    $rows = $this->extractCallRows($byNumber->json());
                    $matched = $this->pickRowMatchingCallIdOrNumber($rows, $callId, $sourceNumber);

                    if ($matched !== null) {
                        return VoipOperationResult::success(
                            externalId: $callId,
                            data: ['rows' => [$matched], 'raw' => $byNumber->json()],
                            message: 'Call details retrieved from Simotel quick/search (caller+time).',
                        );
                    }
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        $info = $this->client()->post('reports/quick/info', [
            'cuid' => $callId,
        ]);

        if ($info->successful()
            && ! $this->isSimotelFailurePayload($info->json())
            && $this->responseHasCallRows($info->json())) {
            return VoipOperationResult::success(
                externalId: $callId,
                data: $this->normalizeCallDetailsPayload($info->json() ?? []),
                message: 'Call details retrieved from Simotel quick/info.',
            );
        }

        return VoipOperationResult::failure(
            error: $search->json('message')
                ?? $info->json('message')
                ?? 'Call details not found in Simotel.',
            data: [
                'quick_search' => $search->json(),
                'quick_info' => $info->json(),
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function pickRowMatchingCallIdOrNumber(array $rows, string $callId, string $sourceNumber): ?array
    {
        foreach ($rows as $row) {
            foreach (['cuid', 'unique_id', 'uniqueid'] as $key) {
                if (! empty($row[$key]) && (string) $row[$key] === $callId) {
                    return $row;
                }
            }
        }

        foreach ($rows as $row) {
            $src = isset($row['src']) ? (string) $row['src'] : '';
            if ($src !== '' && $src === $sourceNumber) {
                return $row;
            }
        }

        return $rows[0] ?? null;
    }

    /**
     * @see https://simotel.com/wiki/fa/developers/simotelapi/v4/report/audio_download/
     */
    public function getCallRecording(string $callId): VoipOperationResult
    {
        $file = $this->recordingFilenameFromIdentifier($callId);

        if ($file === '' || ! $this->looksLikeRecordingFilename($file)) {
            $resolved = $this->resolveRecordingFilenameFromApi($callId);
            if ($resolved !== null) {
                $file = $resolved;
            }
        }

        $response = $this->client()->downloadAudio($file);

        if ($response->failed() || $this->isSimotelFailurePayload($response->json())) {
            return $this->parseHttpFailure(
                message: $response->json('message')
                    ?? $response->json('error')
                    ?? (is_string($response->json('message')) ? $response->json('message') : null)
                    ?? 'Simotel audio download failed.',
                data: $response->json() ?? ['body_preview' => mb_substr($response->body(), 0, 200)],
            );
        }

        $body = $response->body();
        $contentType = (string) ($response->header('Content-Type') ?? '');

        if ($body === '' || $this->looksLikeJsonErrorBody($body, $contentType)) {
            return VoipOperationResult::failure(
                error: 'Simotel audio download returned an empty or non-audio response.',
                data: [
                    'content_type' => $contentType,
                    'body_preview' => mb_substr($body, 0, 200),
                ],
            );
        }

        return VoipOperationResult::success(
            externalId: $file,
            data: [
                'body' => $body,
                'mime_type' => str_contains(strtolower($contentType), 'audio')
                    ? $contentType
                    : 'audio/mpeg',
            ],
            message: 'Call recording retrieved from Simotel.',
        );
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
        $eventName = strtolower(trim((string) ($payload['event_name'] ?? $payload['event'] ?? '')));
        $eventName = preg_replace('/\s+/', ' ', $eventName) ?? $eventName;
        $eventNameCompact = str_replace(' ', '', $eventName);

        if ($eventName === 'cdr') {
            return $this->normalizeCdr($payload);
        }

        if ($eventName === 'new state') {
            return $this->normalizeNewState($payload);
        }

        // Docs: IncomingCall / Incoming Call — caller number + entry_point + cuid/unique_id.
        // @see https://simotel.com/wiki/en/developers/simotelwebhooks/events/incoming_call/
        if ($eventNameCompact === 'incomingcall') {
            return $this->normalizeIncomingCall($payload);
        }

        return new NormalizedWebhookEvent(
            type: VoipWebhookEventType::Unknown,
            rawPayload: $payload,
            provider: $this->getProviderCode()->value,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizeIncomingCall(array $payload): NormalizedWebhookEvent
    {
        $callId = $this->extractCallId($payload);
        $number = isset($payload['number']) ? trim((string) $payload['number']) : '';
        $entryPoint = isset($payload['entry_point']) ? trim((string) $payload['entry_point']) : '';

        return new NormalizedWebhookEvent(
            type: VoipWebhookEventType::CallStarted,
            callId: $callId,
            direction: CallDirection::Inbound,
            sourceNumber: $number !== '' ? $number : null,
            destinationNumber: $entryPoint !== '' ? $entryPoint : null,
            status: CallStatus::Ringing,
            startedAt: now()->toDateTimeString(),
            rawPayload: $payload,
            provider: $this->getProviderCode()->value,
        );
    }

    private function normalizeNewState(array $payload): NormalizedWebhookEvent
    {
        $callId = $this->extractCallId($payload);
        $exten = isset($payload['exten']) ? trim((string) $payload['exten']) : '';
        $state = strtolower(trim((string) ($payload['state'] ?? '')));
        $direction = $this->mapNewStateDirection((string) ($payload['direction'] ?? ''));

        if ($state === 'inuse' && $callId !== null && $exten !== '') {
            $this->agentCache()->store($this->config->connectionId, $callId, $exten);
        }

        return new NormalizedWebhookEvent(
            type: VoipWebhookEventType::AgentStateChanged,
            callId: $callId,
            direction: $direction,
            sourceNumber: isset($payload['participant']) ? (string) $payload['participant'] : null,
            destinationNumber: $exten !== '' ? $exten : null,
            status: match ($state) {
                'inuse' => CallStatus::Answered,
                'ringing' => CallStatus::Ringing,
                default => null,
            },
            extension: $exten !== '' ? $exten : null,
            rawPayload: $payload,
            provider: $this->getProviderCode()->value,
        );
    }

    private function normalizeCdr(array $payload): NormalizedWebhookEvent
    {
        $disposition = strtolower(str_replace(['_', '-'], ' ', (string) ($payload['disposition'] ?? '')));
        $callId = $this->extractCallId($payload);
        $recordingUrl = $this->resolveRecordingUrl($payload, $callId);

        [$type, $status] = $this->mapDisposition($disposition, $recordingUrl !== null);

        $direction = $this->mapDirection((string) ($payload['type'] ?? ''));
        $duration = isset($payload['duration'])
            ? (int) $payload['duration']
            : (isset($payload['billsec']) ? (int) $payload['billsec'] : null);

        $startedAt = $this->normalizeTimestamp(
            $payload['starttime'] ?? $payload['start_time'] ?? null,
        );
        $endedAt = $this->normalizeTimestamp(
            $payload['endtime'] ?? $payload['end_time'] ?? null,
        );

        $src = isset($payload['src']) ? (string) $payload['src'] : null;
        $dst = isset($payload['dst']) ? (string) $payload['dst'] : null;
        $did = isset($payload['did']) ? (string) $payload['did'] : null;

        $extension = $this->resolveAgentExtension(
            direction: $direction,
            src: $src,
            dst: $dst,
            did: $did,
            callId: $callId,
        );

        return new NormalizedWebhookEvent(
            type: $type,
            callId: $callId,
            direction: $direction,
            sourceNumber: $src,
            destinationNumber: $dst,
            status: $status,
            recordingUrl: $recordingUrl,
            extension: $extension,
            startedAt: $startedAt,
            endedAt: $endedAt,
            duration: $duration,
            rawPayload: $payload,
            provider: $this->getProviderCode()->value,
        );
    }

    /**
     * CDR `record` is the official audio filename.
     * If missing, try Quick Search by cuid.
     *
     * @see https://simotel.com/wiki/fa/developers/simotelwebhooks/events/cdr/
     * @see https://simotel.com/wiki/fa/developers/simotelapi/v4/report/audio_download/
     */
    private function resolveRecordingUrl(array $payload, ?string $callId): ?string
    {
        $record = $payload['record'] ?? null;
        if (is_string($record) && trim($record) !== '') {
            return self::RECORDING_URL_PREFIX.trim($record);
        }

        if ($callId === null) {
            return null;
        }

        $disposition = strtolower(str_replace(['_', '-'], ' ', (string) ($payload['disposition'] ?? '')));
        if (str_contains($disposition, 'no answer')
            || str_contains($disposition, 'noanswer')
            || str_contains($disposition, 'busy')) {
            return null;
        }

        if (! str_contains($disposition, 'answer')) {
            return null;
        }

        $fromApi = $this->resolveRecordingFilenameFromApi($callId);

        return $fromApi !== null ? self::RECORDING_URL_PREFIX.$fromApi : null;
    }

    private function resolveRecordingFilenameFromApi(string $callId): ?string
    {
        $result = $this->getCallDetails($callId);

        if (! $result->success) {
            return null;
        }

        foreach ($this->extractCallRows($result->data) as $row) {
            foreach (['record', 'recording', 'file', 'audio'] as $key) {
                $value = $row[$key] ?? null;
                if (is_string($value) && $this->looksLikeRecordingFilename($value)) {
                    return trim($value);
                }
            }
        }

        return null;
    }

    private function resolveAgentExtension(
        ?CallDirection $direction,
        ?string $src,
        ?string $dst,
        ?string $did,
        ?string $callId,
    ): ?string {
        $candidate = $direction === CallDirection::Inbound ? $dst : $src;

        if ($callId !== null) {
            $cached = $this->agentCache()->get($this->config->connectionId, $callId);
            if ($cached !== null) {
                $candidate = $cached;
            } elseif ($this->looksLikeDid($candidate, $did)) {
                $fromApi = $this->resolveExtensionFromApi($callId, $did);
                if ($fromApi !== null) {
                    $candidate = $fromApi;
                    $this->agentCache()->store($this->config->connectionId, $callId, $fromApi);
                }
            }
        }

        $mapped = $this->applyExtensionMapping($candidate, $dst, $did, $src);
        if ($mapped !== null) {
            return $mapped;
        }

        return $candidate !== null && $candidate !== '' ? $candidate : null;
    }

    private function resolveExtensionFromApi(string $callId, ?string $did): ?string
    {
        $result = $this->getCallDetails($callId);

        if (! $result->success) {
            return null;
        }

        foreach ($this->extractCallRows($result->data) as $row) {
            $rowDst = isset($row['dst']) ? (string) $row['dst'] : null;
            if ($rowDst === null || $rowDst === '') {
                continue;
            }

            if (! $this->looksLikeDid($rowDst, $did)) {
                return $rowDst;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $payload */
    private function normalizeCallDetailsPayload(array $payload): array
    {
        return [
            'rows' => $this->extractCallRows($payload),
            'raw' => $payload,
        ];
    }

    /** @param  array<string, mixed>|null  $payload
     * @return list<array<string, mixed>>
     */
    private function extractCallRows(?array $payload): array
    {
        if ($payload === null) {
            return [];
        }

        $data = $payload['data'] ?? null;

        if (is_array($data) && array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
            return array_values(array_filter($data['data'], 'is_array'));
        }

        if (isset($payload['rows']) && is_array($payload['rows'])) {
            return array_values(array_filter($payload['rows'], 'is_array'));
        }

        return [];
    }

    /** @param  array<string, mixed>|null  $payload */
    private function responseHasCallRows(?array $payload): bool
    {
        return $this->extractCallRows($payload) !== [];
    }

    private function applyExtensionMapping(?string ...$keys): ?string
    {
        $mapping = $this->config->settings->extensionMapping;

        if ($mapping === []) {
            return null;
        }

        foreach ($keys as $key) {
            if ($key === null || $key === '') {
                continue;
            }

            if (isset($mapping[$key]) && is_scalar($mapping[$key]) && (string) $mapping[$key] !== '') {
                return (string) $mapping[$key];
            }
        }

        return null;
    }

    private function looksLikeDid(?string $number, ?string $did = null): bool
    {
        if ($number === null || $number === '') {
            return false;
        }

        if ($did !== null && $did !== '' && $number === $did) {
            return true;
        }

        $digits = preg_replace('/\D+/', '', $number) ?? '';

        return strlen($digits) >= self::DID_DIGIT_THRESHOLD;
    }

    /** @param  array<string, mixed>  $payload */
    private function extractCallId(array $payload): ?string
    {
        foreach (['cuid', 'unique_id', 'uniqueid'] as $key) {
            if (! empty($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return null;
    }

    /** @return array{0: VoipWebhookEventType, 1: CallStatus} */
    private function mapDisposition(string $disposition, bool $hasRecording): array
    {
        // Normalize first so NOANSWER / no_answer / "NO ANSWER" share one path.
        // Important: check "noanswer" before "answer" — otherwise NOANSWER matches answer.
        return match (true) {
            str_contains($disposition, 'no answer'),
            str_contains($disposition, 'noanswer') => [VoipWebhookEventType::CallMissed, CallStatus::Missed],
            str_contains($disposition, 'busy') => [VoipWebhookEventType::CallMissed, CallStatus::Busy],
            str_contains($disposition, 'failed'),
            str_contains($disposition, 'congestion'),
            str_contains($disposition, 'cancel') => [VoipWebhookEventType::CallMissed, CallStatus::Failed],
            str_contains($disposition, 'answer') => [
                VoipWebhookEventType::CallEnded,
                CallStatus::Completed,
            ],
            $hasRecording => [VoipWebhookEventType::CallEnded, CallStatus::Completed],
            default => [VoipWebhookEventType::CallEnded, CallStatus::Completed],
        };
    }

    /**
     * Map a Quick Search / CDR row into a terminal webhook event.
     *
     * @param  array<string, mixed>  $row
     */
    public function normalizeOutcomeFromCallRow(string $callId, array $row): NormalizedWebhookEvent
    {
        $disposition = strtolower(str_replace(['_', '-'], ' ', (string) ($row['disposition'] ?? '')));
        $record = $row['record'] ?? $row['recording'] ?? $row['file'] ?? $row['audio'] ?? null;
        $recordingUrl = is_string($record) && trim($record) !== ''
            ? self::RECORDING_URL_PREFIX.trim($record)
            : null;

        $billsec = isset($row['billsec']) ? (int) $row['billsec'] : null;
        $duration = isset($row['duration'])
            ? (int) $row['duration']
            : $billsec;

        // Talk time > 0 means the call was answered even if disposition is oddly empty.
        if ($disposition === '' && ($billsec ?? 0) > 0) {
            $disposition = 'answered';
        }

        [$type, $status] = $this->mapDisposition($disposition, $recordingUrl !== null || ($billsec ?? 0) > 0);

        // Empty disposition + no talk time: leave unresolved for the job to retry / later miss.
        // Do NOT force Missed here — that falsely rejected answered calls when API rows lagged.
        if ($disposition === '' && $recordingUrl === null && ($billsec ?? 0) <= 0) {
            $type = VoipWebhookEventType::CallStarted;
            $status = CallStatus::Ringing;
        }

        return new NormalizedWebhookEvent(
            type: $type,
            callId: $callId,
            direction: $this->mapDirection((string) ($row['type'] ?? 'incoming')),
            sourceNumber: isset($row['src']) ? (string) $row['src'] : null,
            destinationNumber: isset($row['dst']) ? (string) $row['dst'] : (isset($row['did']) ? (string) $row['did'] : null),
            status: $status,
            recordingUrl: $recordingUrl,
            startedAt: $this->normalizeTimestamp($row['starttime'] ?? $row['start'] ?? $row['start_time'] ?? null),
            endedAt: $this->normalizeTimestamp($row['endtime'] ?? $row['end'] ?? $row['end_time'] ?? null),
            duration: $duration,
            rawPayload: $row,
            provider: $this->getProviderCode()->value,
        );
    }

    private function mapDirection(string $type): ?CallDirection
    {
        return match (strtolower(trim($type))) {
            'incoming', 'inbound' => CallDirection::Inbound,
            'outgoing', 'outbound' => CallDirection::Outbound,
            'local', 'feature', 'no defined', '' => CallDirection::Inbound,
            default => CallDirection::tryFrom(strtolower($type)),
        };
    }

    private function mapNewStateDirection(string $direction): ?CallDirection
    {
        return match (strtolower(trim($direction))) {
            'in', 'incoming', 'inbound' => CallDirection::Inbound,
            'out', 'outgoing', 'outbound' => CallDirection::Outbound,
            default => null,
        };
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return $value;
        }
    }

    private function recordingFilenameFromIdentifier(string $identifier): string
    {
        if (str_starts_with($identifier, self::RECORDING_URL_PREFIX)) {
            return substr($identifier, strlen(self::RECORDING_URL_PREFIX));
        }

        return $identifier;
    }

    private function looksLikeRecordingFilename(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        return (bool) preg_match('/\.(mp3|wav|ogg|gsm|sln|ulaw|alaw)$/i', $value);
    }

    /** @param  array<string, mixed>|null  $payload */
    private function isSimotelFailurePayload(?array $payload): bool
    {
        if ($payload === null || ! array_key_exists('success', $payload)) {
            return false;
        }

        $success = $payload['success'];

        return $success === false
            || $success === 0
            || $success === '0'
            || (is_numeric($success) && (int) $success < 1);
    }

    /** @param  array<string, mixed>|null  $payload */
    public function isAccessDeniedPayload(?array $payload): bool
    {
        if ($payload === null) {
            return false;
        }

        $message = strtolower((string) ($payload['message'] ?? $payload['error'] ?? ''));

        if ($message !== '' && (str_contains($message, 'access denied') || str_contains($message, 'ip is not permitted'))) {
            return true;
        }

        return ($payload['denied'] ?? false) === true;
    }

    public function isAccessDeniedResult(VoipOperationResult $result): bool
    {
        if ($result->success) {
            return false;
        }

        if (($result->data['denied'] ?? false) === true) {
            return true;
        }

        return $this->isAccessDeniedPayload($result->data);
    }

    private function looksLikeJsonErrorBody(string $body, string $contentType): bool
    {
        if (str_contains(strtolower($contentType), 'json')) {
            return true;
        }

        $trimmed = ltrim($body);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }

    private function client(): SimotelApiClient
    {
        return $this->client ??= new SimotelApiClient(
            credentials: $this->config->credentials,
            settings: $this->config->settings,
        );
    }

    private function agentCache(): SimotelAgentExtensionCache
    {
        return $this->agentCache ??= app(SimotelAgentExtensionCache::class);
    }
}
