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

];
