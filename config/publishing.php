<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Publish-time renderer (ADR-006)
    |--------------------------------------------------------------------------
    |
    | A Publish runs the compiled React renderer once with Node. Visitor
    | requests never run Node; they are served from stored HTML artifacts.
    |
    */

    'node_binary' => env('PUBLISHING_NODE_BINARY', 'node'),

    'renderer' => base_path('bootstrap/ssr/render-server.js'),

    'render_timeout' => (int) env('PUBLISHING_RENDER_TIMEOUT', 60),

    /*
    | A Publication still in progress after this many minutes is treated as
    | abandoned (e.g. a crashed worker), so it no longer blocks new Publishes.
    */

    'stale_after_minutes' => 15,

    /*
    |--------------------------------------------------------------------------
    | Public hosts (ADR-006 §9)
    |--------------------------------------------------------------------------
    |
    | Published Sites are served on {subdomain}.{public_domain}, e.g.
    | dealer.localhost locally and dealer.landflow.me in production.
    |
    */

    'public_domain' => strtolower((string) env('LANDFLOW_PUBLIC_DOMAIN', 'localhost')),

    'public_scheme' => env('LANDFLOW_PUBLIC_SCHEME', 'http'),

    // Optional port for generated public URLs only (local `php artisan serve`); empty in production.
    'public_port' => env('LANDFLOW_PUBLIC_PORT'),

    /*
    | Seconds a published Page artifact may stay in the application cache. Keys
    | include the version, so a new Publish never needs a cache flush.
    */

    'cache_ttl' => (int) env('PUBLISHING_CACHE_TTL', 3600),

];
