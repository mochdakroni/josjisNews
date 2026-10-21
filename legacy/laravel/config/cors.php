<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Mengizinkan domain frontend web publik (misal Next.js / Nuxt / Astro)
    | untuk mengakses REST API /api/v1/* secara aman.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],

    // Pisahkan multiple domain dengan koma di .env:
    // CORS_ALLOWED_ORIGINS=https://josjisnews.com,https://www.josjisnews.com
    'allowed_origins' => array_filter(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')))
    ),

    // Opsional: dukung subdomain dinamis (mis. preview deployment)
    'allowed_origins_patterns' => array_filter([
        env('CORS_ALLOWED_ORIGIN_PATTERN'),
    ]),

    'allowed_headers' => [
        'Accept',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'Authorization',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    // Cache preflight OPTIONS request di browser selama 24 jam (86400 detik)
    'max_age' => (int) env('CORS_MAX_AGE', 86400),

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
