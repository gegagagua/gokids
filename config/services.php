<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bog' => [
        'client_id' => env('BOG_CLIENT_ID', '10002133'),
        'secret' => env('BOG_SECRET', 'Xf9Q3KjhbEgG'),
        'public_key' => env('BOG_PUBLIC_KEY', '10002133'),
        'base_url' => env('BOG_BASE_URL', 'https://api.bog.ge'),
        'payment_url' => env('BOG_PAYMENT_URL', 'https://payment.bog.ge/payment'),
        'test_mode' => true,
    ],

];
