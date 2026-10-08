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

];
