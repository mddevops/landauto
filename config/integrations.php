<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery retry policy
    |--------------------------------------------------------------------------
    |
    | Seconds to wait before each retry after a transient failure. The first
    | attempt is immediate; after the last delay the delivery is failed.
    |
    */

    'retry_delays' => array_values(array_map('intval', array_filter(
        explode(',', (string) env('INTEGRATIONS_RETRY_DELAYS', '60,300,900,3600')),
        fn (string $value): bool => trim($value) !== '',
    ))),

    // A delivery stuck in `processing` longer than this (lost worker) is rescheduled.
    'stale_processing_minutes' => (int) env('INTEGRATIONS_STALE_PROCESSING_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Outbound HTTP (webhook / custom API / test connection)
    |--------------------------------------------------------------------------
    |
    | Plain http:// destinations are refused unless explicitly allowed for a
    | local or testing environment. Redirects are never followed.
    |
    */

    'http' => [
        'connect_timeout' => (int) env('INTEGRATIONS_HTTP_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('INTEGRATIONS_HTTP_TIMEOUT', 10),
        'max_response_bytes' => (int) env('INTEGRATIONS_HTTP_MAX_RESPONSE_BYTES', 1048576),
        'allow_plain_http' => (bool) env('INTEGRATIONS_HTTP_ALLOW_PLAIN', false),
        'user_agent' => 'Landflow-Delivery/1.0',
    ],

    'test_connection_per_minute' => (int) env('INTEGRATIONS_TEST_CONNECTION_PER_MINUTE', 5),

    // Fake DNS + CRM transport for browser E2E; honoured only in the testing/e2e environments.
    'e2e_fake' => (bool) env('INTEGRATIONS_E2E_FAKE', false),

];
