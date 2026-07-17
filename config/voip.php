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

];
