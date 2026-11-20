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

    'allowed_methods' => ['*'],

    // Only the Nuxt SPA. A wildcard is not allowed together with credentials, and would let any
    // site make cookie-authenticated requests.
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // The SPA downloads CSV exports with fetch and names the file from this header.
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    // Required for the Sanctum session and XSRF-TOKEN cookies.
    'supports_credentials' => true,

];
