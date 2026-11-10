<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support contact (X-013)
    |--------------------------------------------------------------------------
    |
    | Shown in account-security notices (email change). Must be a real reachable
    | mailbox or URL before production; never invent a fake contact.
    |
    */

    'support_email' => env('SUPPORT_EMAIL'),

    'support_url' => env('SUPPORT_URL'),

    /*
    |--------------------------------------------------------------------------
    | HTTPS / HSTS
    |--------------------------------------------------------------------------
    */

    'force_https' => (bool) env('FORCE_HTTPS', false),

    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated IPs/CIDRs, or "*" behind a known load balancer. Empty means
    | trust no proxies (correct for local development without a reverse proxy).
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

];
