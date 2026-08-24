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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'snap_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions',
        'api_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com',
        // URL library Snap.js untuk frontend. WAJIB satu lingkungan dengan
        // snap_url di atas: token yang dibuat di server produksi tidak dapat
        // dibuka oleh snap.js sandbox, dan sebaliknya.
        'snap_js_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js',
        // Batas waktu panggilan ke Midtrans, dalam detik. Yang menunggu jawaban
        // Midtrans bukan proses latar melainkan permintaan halaman yang sedang
        // dibuka pengguna, jadi menunggu lama sama saja dengan halaman macet.
        // Tanpa nilai ini yang berlaku adalah bawaan Guzzle yang jauh lebih
        // panjang (temuan V8-04).
        'timeout' => (int) env('MIDTRANS_TIMEOUT', 5),
        'connect_timeout' => (int) env('MIDTRANS_CONNECT_TIMEOUT', 3),
    ],

];
