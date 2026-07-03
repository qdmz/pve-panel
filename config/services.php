<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxmox Configuration
    |--------------------------------------------------------------------------
    */

    'proxmox' => [
        'default_timeout' => env('PROXMOX_TIMEOUT', 30),
        'default_port'    => env('PROXMOX_PORT', 8006),
        'ssl_verify'      => env('PROXMOX_SSL_VERIFY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Epay Payment Gateway Configuration
    |--------------------------------------------------------------------------
    */

    'epay' => [
        'api_url' => env('EPAY_API_URL', ''),
        'pid'     => env('EPAY_PID', ''),
        'key'     => env('EPAY_KEY', ''),
    ],

];
