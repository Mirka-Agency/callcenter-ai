<?php

$extraBlockedHosts = array_values(array_filter(array_map(
    static fn (string $host): string => strtolower(trim($host)),
    explode(',', (string) env('LLM_BLOCKED_HOSTS', '')),
)));

return [

    /*
    |--------------------------------------------------------------------------
    | Remote LLM analysis
    |--------------------------------------------------------------------------
    |
    | Local laptops default to off so recordings and queue jobs cannot spend
    | production AvalAI credits. Production / on-prem (APP_ENV != local) stays on.
    |
    */

    'remote_enabled' => env('LLM_REMOTE_ENABLED') === null
        ? env('APP_ENV') !== 'local'
        : filter_var(env('LLM_REMOTE_ENABLED'), FILTER_VALIDATE_BOOLEAN),

    /*
    | Block AvalAI (and other listed hosts) even if a provider API key exists.
    | Defaults on for APP_ENV=local.
    */

    'block_avalai' => env('LLM_BLOCK_AVALAI') === null
        ? env('APP_ENV') === 'local'
        : filter_var(env('LLM_BLOCK_AVALAI'), FILTER_VALIDATE_BOOLEAN),

    'blocked_hosts' => array_values(array_unique(array_merge(
        ['api.avalai.ir', 'avalai.ir'],
        $extraBlockedHosts,
    ))),

    /*
    | Cache the static analysis system prompt when the provider supports it.
    | A failed cache create falls back to sending the prompt inline.
    */

    'prompt_cache' => env('LLM_PROMPT_CACHE') === null
        ? true
        : filter_var(env('LLM_PROMPT_CACHE'), FILTER_VALIDATE_BOOLEAN),

    /*
    | Below this confidence, a personal or empty call still gets the full analysis.
    */

    'triage_confidence' => (float) env('LLM_TRIAGE_CONFIDENCE', 0.75),

    /*
    | Used only when Models & Pricing has no transcription model selected.
    | A selected admin model is always sent as-is.
    */

    'transcription' => [
        'model' => env('LLM_TRANSCRIPTION_MODEL', 'whisper-1'),
        'language' => 'fa',
        'max_continuations' => 4,
        'segment_long_audio' => env('LLM_SEGMENT_LONG_AUDIO') === null
            ? true
            : filter_var(env('LLM_SEGMENT_LONG_AUDIO'), FILTER_VALIDATE_BOOLEAN),
        'segment_seconds' => 150,
        'segment_when_longer_than' => 180,
        'segment_overlap_seconds' => 5,
    ],

];
