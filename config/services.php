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

    // ProCredit E-commerce Payment Gateway (Internet Shop Integration v1.1). Quipu test/prod.
    // Common Name on certificate = merchant_id (ECOM_TEST222 in test). Cert files in storage/app/cert/ – used for TLS only, not installed on server (per bank).
    // Test: API https://3dss2test.quipu.de:8000/order | Merchant portal https://3dss2test.quipu.de:8004/ | TypeRid = 1
    'procredit' => [
        'merchant_id' => 'ECOM_TEST222', // Common Name on certificate (test). For prod, bank will provide.
        'order_endpoint' => 'https://3dss2test.quipu.de:8000/order', // Test env API URL
        'type_rid' => '1', // Order type in test is 1 (doc: TypeRid in test is 1)
        'verify_peer' => true, // SSL: verify server certificate (use false only for test if self-signed)
        'cert_path' => storage_path('app/cert/cert.pem'),
        'key_path' => storage_path('app/cert/key.pem'),
        'ca_path' => storage_path('app/cert/ca.pem'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
    ],

];
