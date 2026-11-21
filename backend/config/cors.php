<?php

/*
 | The SPA is served from the same origin as the API (gateway in production, Vite proxy in dev),
 | so no cross-origin access is allowed by default. Set CORS_ALLOWED_ORIGINS (comma-separated)
 | only if a client on another origin (e.g. a mobile web view) needs it.
 */
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept', 'X-Requested-With'],

    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 600,

    'supports_credentials' => false,
];
