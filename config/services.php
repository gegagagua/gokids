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

    // ProCredit E-commerce Payment Gateway (Internet Shop Integration v1.1). All values here – no .env.
    // order_endpoint: placeholder below – replace with real URL from bank (test/prod). No SSH – only HTTPS + TLS client certs.
    'procredit' => [
        'merchant_id' => 'YourMerchantId', // CN of client certificate, from bank (max 20 chars)
        'order_endpoint' => 'https://api.bank.com/order', // TODO: set real endpoint from bank (e.g. https://ecommerce-test.example.com/order)
        'cert_path' => base_path('certs/cert.pem'),
        'key_path' => base_path('certs/key.pem'),
        'ca_path' => base_path('certs/ca.pem'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
    ],

];
