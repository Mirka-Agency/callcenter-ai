<?php

return [

    /*
    |--------------------------------------------------------------------------
    | On-prem single-employer bootstrap (OnPremSeeder)
    |--------------------------------------------------------------------------
    |
    | Used when installing one organization on a customer LAN.
    | See docs/on-prem.md and .env.onprem.example.
    |
    | docker-compose.onprem.yml sets ONPREM_ENABLED=true for a single-organization install.
    |
    */

    'enabled' => filter_var(env('ONPREM_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'admin_name' => env('ONPREM_ADMIN_NAME', 'Super Admin'),
    'admin_email' => env('ONPREM_ADMIN_EMAIL', 'admin@example.com'),
    'admin_password' => env('ONPREM_ADMIN_PASSWORD', 'password'),

    'employer_name' => env('ONPREM_EMPLOYER_NAME', 'مدیر شرکت'),
    'employer_email' => env('ONPREM_EMPLOYER_EMAIL', 'employer@example.com'),
    'employer_password' => env('ONPREM_EMPLOYER_PASSWORD', 'password'),

    'org_title' => env('ONPREM_ORG_TITLE', 'شرکت محلی'),
    'employer_can_manage_integrations' => filter_var(
        env('ONPREM_EMPLOYER_CAN_MANAGE_INTEGRATIONS', true),
        FILTER_VALIDATE_BOOLEAN,
    ),

];
