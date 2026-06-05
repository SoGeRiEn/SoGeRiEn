<?php
declare(strict_types=1);

return [
    'app' => [
        'domain' => 'https://example.com',
        'sogerien_domain' => 'https://example.com',
        'show_errors' => true,
        'keys_dir' => __DIR__ . '/../runtime/keys',
        'cache_dir' => __DIR__ . '/../runtime/cache',
    ],
    'db' => [
        'alias' => 'front',
        'host' => '127.0.0.1',
        'port' => '5432',
        'name' => 'sogerien',
        'user' => 'sogerien',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'smtp' => [
        'host' => '',
        'port' => 587,
        'encryption' => 'tls',
        'user' => '',
        'pass' => '',
    ],
    'apis' => [
        'infatica' => [
            'base_url' => 'https://api.infatica.io',
            'client_base_url' => 'https://dashboard.infatica.io/includes/api/client',
            'scraper_base_url' => 'https://scrape.infatica.io',
            'api_key' => '',
            'residential_api_key' => '',
            'mobile_api_key' => '',
            'isp_api_key' => '',
            'dc_api_key' => '',
            'scraper_api_key' => '',
            'client_login' => '',
            'client_password' => '',
        ],
        'stripe' => [
            'publishable_key' => '',
            'secret_key' => '',
            'webhook_secret' => '',
        ],
        'google_oauth' => [
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => 'https://example.com/auth/google/callback',
        ],
    ],
];

