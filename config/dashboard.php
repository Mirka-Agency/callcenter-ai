<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Insight lists reset (employer dashboard)
    |--------------------------------------------------------------------------
    |
    | Trading opportunities, satisfied/dissatisfied customers, and forgotten
    | follow-ups only include analyses with analyzed_at at/after this time.
    | Use an ISO-8601 datetime. Empty / unset = no extra cutoff (tests).
    |
    */

    'insight_lists_since' => env('DASHBOARD_INSIGHT_LISTS_SINCE', '2026-09-22T00:00:00+03:30'),

];
