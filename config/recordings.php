<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recording Storage Disk
    |--------------------------------------------------------------------------
    |
    | Disk used for call recording uploads and downloads. Use "s3" for cloud
    | storage or "local" for private on-server storage. Signed playback URLs
    | are generated automatically for private disks.
    |
    | When AWS_BUCKET is set and RECORDINGS_DISK is not provided, "s3" is used.
    |
    */

    'disk' => env('RECORDINGS_DISK', env('AWS_BUCKET') ? 's3' : env('FILESYSTEM_DISK', 'local')),

    /*
    |--------------------------------------------------------------------------
    | Signed Playback URL Lifetime (minutes)
    |--------------------------------------------------------------------------
    */

    'playback_url_ttl_minutes' => (int) env('RECORDINGS_PLAYBACK_URL_TTL', 120),

    /*
    |--------------------------------------------------------------------------
    | Audio Retention Period (days)
    |--------------------------------------------------------------------------
    |
    | Countdown starts after AI analysis completes — not at upload time.
    | Recordings without expires_at are never purged by the retention job.
    |
    */

    'retention_days' => (int) env('RECORDINGS_RETENTION_DAYS', 10),

];
