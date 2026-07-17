<?php

return [

    'name' => env('APP_NAME', 'PVE Panel'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'frontend_url' => env('FRONTEND_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    'timezone' => 'Asia/Shanghai',

    'locale' => 'zh_CN',

    'fallback_locale' => 'en',

    'faker_locale' => 'zh_CN',

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => 'file',
    ],

    'providers' => \Illuminate\Support\ServiceProvider::defaultProviders()->toArray(),

    'aliases' => [],
];
