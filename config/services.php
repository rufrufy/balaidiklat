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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'kirimchat' => [
        'base_url' => env('KIRIMCHAT_BASE_URL', 'https://api-prod.kirim.chat/api/v1/public'),
        'api_key' => env('KIRIMCHAT_API_KEY'),
        'webhook_secret' => env('KIRIMCHAT_WEBHOOK_SECRET'),
        'require_webhook_secret' => env('KIRIMCHAT_REQUIRE_WEBHOOK_SECRET', false),
    ],

    'eretribusi' => [
        'base_url' => env('ERETRIBUSI_BASE_URL'),
        'api_key' => env('ERETRIBUSI_API_KEY'),
        'send_path' => env('ERETRIBUSI_SEND_PATH', '/api/billing'),
    ],

    'bapenda' => [
        'base_url' => env('BAPENDA_BASE_URL', 'https://eretribusi.semarangkota.go.id'),
        'vcode' => env('BAPENDA_VCODE'),
        'token' => env('BAPENDA_TOKEN'),
        'store_path' => env('BAPENDA_STORE_PATH', '/api/v2/prod/retribusi/store'),
        'kode_opd' => env('BAPENDA_KODE_OPD', '3.1.03.01'),
        'kode_rekening' => env('BAPENDA_KODE_REKENING', '4.1.02.02.01.0005'),
        'rekening_id' => env('BAPENDA_REKENING_ID', '76'),
        'qris_base_url' => env('BAPENDA_QRIS_BASE_URL', 'http://103.101.52.67:13000'),
        'qris_path' => env('BAPENDA_QRIS_PATH', '/api/bapenda/getLinkQris'),
        'qris_user' => env('BAPENDA_QRIS_USER'),
        'qris_pass' => env('BAPENDA_QRIS_PASS'),
        'qris_proxy_url' => env('BAPENDA_QRIS_PROXY_URL', ''),
        'callback_token' => env('BAPENDA_CALLBACK_TOKEN'),
    ],

];
