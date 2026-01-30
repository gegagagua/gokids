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
    // Doc: Bank provides Terminal identifier (MerchantID), Endpoint URL, Signed certificate. merchant_id is NOT sent in API – bank identifies you by the CN of your client certificate (cert.pem). Leave merchant_id as placeholder until bank gives you the value (for reference: CSR Common Name must match it, max 20 chars).
    // order_endpoint: replace with real URL from bank (test first, then prod). Cert files: storage/app/certs/
    'procredit' => [
        'merchant_id' => '', // Optional. Bank will provide (Terminal identifier). Cert CN must match this; leave empty until then.
        'order_endpoint' => 'https://api.bank.com/order', // TODO: set real endpoint from bank after test env is ready
        'cert_path' => storage_path('app/certs/cert.pem'),
        'key_path' => storage_path('app/certs/key.pem'),
        'ca_path' => storage_path('app/certs/ca.pem'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
    ],

];
