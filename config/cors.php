<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // A16: explicit verbs only — wildcard methods widen preflight trust.
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Two origins, two env vars, local + production only (ADR-0009, no
    // staging). FRONTEND_URL serves the mobile client web origins;
    // LANDING_URL serves the landing page (Inquiry POSTs). Both stay
    // comma-separated and per-env; mobile behavior unchanged.
    'allowed_origins' => array_values(array_unique(array_filter(array_map('trim', array_merge(
        explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173,http://127.0.0.1:5173')),
        explode(',', (string) env('LANDING_URL', 'http://localhost:5173')),
    ))))),

    'allowed_origins_patterns' => array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', '')))),

    // A16: explicit headers only — wildcard headers leak custom tokens.
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Origin', 'X-Requested-With', 'X-XSRF-TOKEN'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => filter_var(env('CORS_SUPPORTS_CREDENTIALS', false), FILTER_VALIDATE_BOOLEAN),

];
