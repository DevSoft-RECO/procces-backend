<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration - APP HIJA (SADEC)
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // ---------------------------------------------------------
        // 1. ENTORNO LOCAL (Desarrollo)
        // ---------------------------------------------------------
        'http://localhost:5173',
        'http://localhost:5177', // Tu App Hija Local

        // ---------------------------------------------------------
        // 2. ENTORNO PRODUCCIÓN (Ecosistema Yaman Kutx)
        // ---------------------------------------------------------
        'https://portal.yamankutx.com.gt',       // Indispensable: Permite que la Madre hable con la Hija
        'https://api-portal.yamankutx.com.gt',   // El Backend de la Madre
        'https://sadec.yamankutx.com.gt',        // La propia App
    ],

    'allowed_origins_patterns' => [
        // PATRÓN COMODÍN: Autoriza a cualquier hermano del mismo ecosistema con HTTPS
        '#^https://.*\.yamankutx\.com\.gt$#',
        '#^https://yamankutx\.com\.gt$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400, // Aumentamos a 24 horas para evitar peticiones OPTIONS repetitivas

    // CRÍTICO: Permite que el Portal y Sadec compartan cookies de sesión y tokens
    'supports_credentials' => true,

];
