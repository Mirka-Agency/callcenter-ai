<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum analyzable call duration (seconds)
    |--------------------------------------------------------------------------
    |
    | VoIP calls shorter than this are not downloaded or analyzed. Covers
    | dial-tone-only attempts and instant hangups with no real conversation.
    | Set to 0 to disable the duration gate.
    |
    */

    'min_analyzable_duration_seconds' => (int) env('INTELLIGENCE_MIN_ANALYZABLE_DURATION_SECONDS', 10),

];
