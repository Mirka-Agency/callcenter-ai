<?php

namespace App\Application\Voip\Support;

use App\Domain\Voip\Enums\CallDirection;
use App\Models\VoipCallLog;
use App\Models\VoipWebhookLog;
use Illuminate\Database\Eloquent\Builder;

class VoipReportFilter
{
    /** @var list<string> */
    public const CALL_ID_PAYLOAD_KEYS = [
        'cuid',
        'unique_id',
        'uniqueid',
        'call_id',
        'external_call_id',
        'callId',
    ];

    /** @var list<string> */
    public const EXTENSION_PAYLOAD_KEYS = [
        'resolved_extension',
        'extension',
        'agent_extension',
        'internal_number',
        'exten',
    ];

    /** @var list<string> */
    public const DIRECTION_PAYLOAD_KEYS = [
        'direction',
        'call_direction',
        'call_type',
        'type',
    ];

    /**
     * @param  Builder<VoipCallLog>  $query
     * @return Builder<VoipCallLog>
     */
    public static function applyCallLogFilters(Builder $query, array $filters): Builder
    {
        $query = self::constrainCallLogsByCallId($query, self::stringValue($filters['call_id'] ?? null));
        $query = self::constrainCallLogsByExtension($query, self::stringValue($filters['extension'] ?? null));

        return self::constrainCallLogsByDirection($query, self::stringValue($filters['direction'] ?? null));
    }

    /**
     * @param  Builder<VoipWebhookLog>  $query
     * @return Builder<VoipWebhookLog>
     */
    public static function applyWebhookLogFilters(Builder $query, array $filters): Builder
    {
        $query = self::constrainWebhookLogsByCallId($query, self::stringValue($filters['call_id'] ?? null));
        $query = self::constrainWebhookLogsByExtension($query, self::stringValue($filters['extension'] ?? null));

        return self::constrainWebhookLogsByDirection($query, self::stringValue($filters['direction'] ?? null));
    }

    /**
     * @param  Builder<VoipCallLog>  $query
     * @return Builder<VoipCallLog>
     */
    public static function constrainCallLogsByCallId(Builder $query, ?string $callId): Builder
    {
        if ($callId === null) {
            return $query;
        }

        $like = self::likeValue($callId);

        return $query->where(function (Builder $group) use ($like): void {
            $group->where('external_call_id', 'like', $like);

            foreach (self::CALL_ID_PAYLOAD_KEYS as $key) {
                $group->orWhere('raw_payload->'.$key, 'like', $like);
            }
        });
    }

    /**
     * @param  Builder<VoipCallLog>  $query
     * @return Builder<VoipCallLog>
     */
    public static function constrainCallLogsByExtension(Builder $query, ?string $extension): Builder
    {
        if ($extension === null) {
            return $query;
        }

        $like = self::likeValue($extension);

        return $query->where(function (Builder $group) use ($like): void {
            foreach (self::EXTENSION_PAYLOAD_KEYS as $index => $key) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $group->{$method}('raw_payload->'.$key, 'like', $like);
            }

            $group->orWhere('source_number', 'like', $like)
                ->orWhere('destination_number', 'like', $like);
        });
    }

    /**
     * @param  Builder<VoipCallLog>  $query
     * @return Builder<VoipCallLog>
     */
    public static function constrainCallLogsByDirection(Builder $query, ?string $direction): Builder
    {
        if ($direction === null || CallDirection::tryFrom($direction) === null) {
            return $query;
        }

        return $query->where('direction', $direction);
    }

    /**
     * @param  Builder<VoipWebhookLog>  $query
     * @return Builder<VoipWebhookLog>
     */
    public static function constrainWebhookLogsByCallId(Builder $query, ?string $callId): Builder
    {
        if ($callId === null) {
            return $query;
        }

        $like = self::likeValue($callId);

        return $query->where(function (Builder $group) use ($like): void {
            foreach (self::CALL_ID_PAYLOAD_KEYS as $index => $key) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $group->{$method}('payload->'.$key, 'like', $like);
            }
        });
    }

    /**
     * @param  Builder<VoipWebhookLog>  $query
     * @return Builder<VoipWebhookLog>
     */
    public static function constrainWebhookLogsByExtension(Builder $query, ?string $extension): Builder
    {
        if ($extension === null) {
            return $query;
        }

        $like = self::likeValue($extension);

        return $query->where(function (Builder $group) use ($like): void {
            $group->where('resolved_extension', 'like', $like);

            foreach (self::EXTENSION_PAYLOAD_KEYS as $key) {
                $group->orWhere('payload->'.$key, 'like', $like);
            }
        });
    }

    /**
     * @param  Builder<VoipWebhookLog>  $query
     * @return Builder<VoipWebhookLog>
     */
    public static function constrainWebhookLogsByDirection(Builder $query, ?string $direction): Builder
    {
        if ($direction === null || CallDirection::tryFrom($direction) === null) {
            return $query;
        }

        $aliases = self::directionAliases($direction);

        return $query->where(function (Builder $group) use ($aliases, $direction): void {
            foreach (self::DIRECTION_PAYLOAD_KEYS as $index => $key) {
                $method = $index === 0 ? 'whereIn' : 'orWhereIn';
                $group->{$method}('payload->'.$key, $aliases);
            }

            $group->orWhereExists(function ($sub) use ($direction): void {
                $sub->selectRaw('1')
                    ->from('voip_call_logs')
                    ->whereColumn(
                        'voip_call_logs.organization_voip_connection_id',
                        'voip_webhook_logs.organization_voip_connection_id',
                    )
                    ->where('voip_call_logs.direction', $direction)
                    ->where(function ($match): void {
                        foreach (self::CALL_ID_PAYLOAD_KEYS as $key) {
                            $match->orWhereRaw(
                                'voip_call_logs.external_call_id = json_extract(voip_webhook_logs.payload, ?)',
                                ['$.'.$key],
                            );
                        }
                    });
            });
        });
    }

    /** @param  array<string, mixed>|null  $payload */
    public static function callIdFromPayload(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        foreach (self::CALL_ID_PAYLOAD_KEYS as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) || is_numeric($value)) {
                $normalized = trim((string) $value);

                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        return null;
    }

    /** @param  array<string, mixed>|null  $payload */
    public static function directionFromPayload(?array $payload): ?CallDirection
    {
        if ($payload === null) {
            return null;
        }

        foreach (self::DIRECTION_PAYLOAD_KEYS as $key) {
            $raw = $payload[$key] ?? null;

            if (! is_string($raw) && ! is_numeric($raw)) {
                continue;
            }

            $mapped = self::mapDirection((string) $raw);

            if ($mapped !== null) {
                return $mapped;
            }
        }

        return null;
    }

    public static function extensionFromCallLog(VoipCallLog $log): ?string
    {
        $payload = is_array($log->raw_payload) ? $log->raw_payload : [];

        foreach (self::EXTENSION_PAYLOAD_KEYS as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) || is_numeric($value)) {
                $normalized = trim((string) $value);

                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        $candidate = $log->direction === CallDirection::Inbound
            ? $log->destination_number
            : $log->source_number;

        if (! is_string($candidate) && ! is_numeric($candidate)) {
            return null;
        }

        $normalized = trim((string) $candidate);

        if ($normalized === '' || strlen(preg_replace('/\D+/', '', $normalized) ?? '') > 6) {
            return null;
        }

        return $normalized;
    }

    public static function mapDirection(string $direction): ?CallDirection
    {
        $normalized = strtolower(str_replace(['_', '-'], ' ', trim($direction)));

        return match (true) {
            in_array($normalized, ['inbound', 'incoming', 'in'], true) => CallDirection::Inbound,
            in_array($normalized, ['outbound', 'outgoing', 'out'], true) => CallDirection::Outbound,
            default => CallDirection::tryFrom(strtolower(trim($direction))),
        };
    }

    /** @return array<string, string> */
    public static function directionOptions(): array
    {
        return collect(CallDirection::cases())
            ->mapWithKeys(fn (CallDirection $direction): array => [$direction->value => $direction->label()])
            ->all();
    }

    /** @return list<string> */
    private static function directionAliases(string $direction): array
    {
        return match ($direction) {
            CallDirection::Inbound->value => ['inbound', 'incoming', 'in'],
            CallDirection::Outbound->value => ['outbound', 'outgoing', 'out'],
            default => [$direction],
        };
    }

    private static function stringValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private static function likeValue(string $value): string
    {
        return '%'.addcslashes($value, '%_\\').'%';
    }
}
