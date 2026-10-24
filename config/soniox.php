<?php

return [
    'api_key' => env('SONIOX_API_KEY'),
    'base_url' => env('SONIOX_BASE_URL', 'https://api.soniox.com/v1'),
    'model' => env('SONIOX_MODEL', 'stt-async-v5'),
    'poll_interval_seconds' => 5,
    'deadline_seconds' => (int) env('SONIOX_DEADLINE_SECONDS', 3600),
];
