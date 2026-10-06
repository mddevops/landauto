<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Custom domains (Phase 7, D-111)
    |--------------------------------------------------------------------------
    |
    | Customers keep their registrar and DNS and only add the records shown in
    | «Домены»: a TXT ownership record plus A/AAAA (apex) or CNAME (www and
    | other subdomains) pointing to the shared ingress. Values come from the
    | environment only; nothing production-specific is hardcoded here.
    |
    */

    'cname_target' => strtolower(rtrim(trim((string) env('CUSTOM_DOMAIN_CNAME_TARGET', '')), '.')),

    'ipv4' => trim((string) env('CUSTOM_DOMAIN_IPV4', '')),

    'ipv6' => strtolower(trim((string) env('CUSTOM_DOMAIN_IPV6', ''))),

    'verification_prefix' => '_landflow-verification',

    'verification_value_prefix' => 'landflow-site-verification=',

    /*
    | DNS lookups: `system` uses the PHP resolver with bounded answers; `fake`
    | (testing/e2e only) answers from the application cache so no DNS query
    | leaves the machine.
    */

    'dns_driver' => env('CUSTOM_DOMAIN_DNS_DRIVER', 'system'),

    // Manual «Проверить DNS» requests per Site per minute.
    'check_per_minute' => (int) env('CUSTOM_DOMAIN_CHECK_PER_MINUTE', 6),

    // Pending domains are re-checked by the scheduler at most this often.
    'reconcile_after_minutes' => (int) env('CUSTOM_DOMAIN_RECONCILE_AFTER_MINUTES', 10),

    /*
    | SSL is always issued by Landflow infrastructure; the application stores
    | lifecycle metadata only. `none` never provisions, `command` runs the
    | configured provisioning script, `fake` (testing/e2e only) simulates it.
    */

    'ssl_driver' => env('CUSTOM_DOMAIN_SSL_DRIVER', 'none'),

    'ssl_command' => env('CUSTOM_DOMAIN_SSL_COMMAND'),

    'ssl_timeout' => (int) env('CUSTOM_DOMAIN_SSL_TIMEOUT', 180),

];
