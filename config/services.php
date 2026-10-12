<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Google reCAPTCHA v3 on the public and admin login pages.
    | RECAPTCHA_ENABLED defaults to on. On-prem is always off (see Recaptcha::enabled).
    | Verification stays off until both keys are set.
    | min_score is the lowest v3 score (0–1) accepted for the login action.
    */
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'enabled' => env('RECAPTCHA_ENABLED', true),
        'min_score' => env('RECAPTCHA_MIN_SCORE', 0.5),
    ],

    /*
    | sms.ir REST API. Keys live in the developers panel.
    | SMSIR_NOTIFY_MOBILE receives a copy of every consultation request.
    */
    'smsir' => [
        'api_key' => env('SMSIR_API_KEY'),
        'line_number' => env('SMSIR_LINE_NUMBER'),
        'base_url' => env('SMSIR_BASE_URL', 'https://api.sms.ir/v1'),
        'notify_mobile' => env('SMSIR_NOTIFY_MOBILE'),
    ],

];
