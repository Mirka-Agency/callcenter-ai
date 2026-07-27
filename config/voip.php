<?php

use App\Infrastructure\Voip\Adapters\CustomVoipAdapter;
use App\Infrastructure\Voip\Adapters\NovatelVoipAdapter;
use App\Infrastructure\Voip\Adapters\NullVoipAdapter;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;

return [

    'adapter_class' => env('VOIP_ADAPTER_CLASS'),

    'default_adapter' => env('VOIP_DEFAULT_ADAPTER', NovatelVoipAdapter::class),

    'fallback_adapter' => env('VOIP_FALLBACK_ADAPTER', NullVoipAdapter::class),

    'adapters' => [
        'novatel' => env('VOIP_ADAPTER_NOVATEL', NovatelVoipAdapter::class),
        'simotel' => env('VOIP_ADAPTER_SIMOTEL', SimotelVoipAdapter::class),
        'custom' => env('VOIP_ADAPTER_CUSTOM', CustomVoipAdapter::class),
    ],

    'default_polling_interval_seconds' => (int) env('VOIP_DEFAULT_POLLING_INTERVAL', 30),

    'min_polling_interval_seconds' => (int) env('VOIP_MIN_POLLING_INTERVAL', 10),

    'max_polling_interval_seconds' => (int) env('VOIP_MAX_POLLING_INTERVAL', 60),

    // Queue *name* (e.g. default), not the QUEUE_CONNECTION driver (database/redis).
    'queue' => env('VOIP_QUEUE', 'default'),

    // After IncomingCall, wait before Quick Search for answered/missed (~1–2 min).
    'simotel_outcome_resolve_delay_seconds' => (int) env('VOIP_SIMOTEL_OUTCOME_DELAY', 90),

    // Retry Quick Search when CDR is not indexed yet.
    'simotel_outcome_retry_seconds' => (int) env('VOIP_SIMOTEL_OUTCOME_RETRY', 60),

    'ami_reconnect_delay_seconds' => (int) env('VOIP_AMI_RECONNECT_DELAY', 5),

    // AMI requires outbound TCP to the customer's Asterisk (port 5038). Enable on LAN/on-prem only.
    'ami_enabled' => filter_var(env('VOIP_AMI_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

];
