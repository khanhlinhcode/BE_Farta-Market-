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

    'anthropic' => [
        'driver' => env('AI_CHAT_DRIVER', 'anthropic'),
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        'base_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com'),
    ],

    'ai_chat' => [
        'driver' => env('AI_CHAT_DRIVER', 'anthropic'),
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AI_CHAT_MODEL', env('ANTHROPIC_MODEL', 'claude-sonnet-4-6')),
        'base_url' => env('AI_CHAT_BASE_URL', env('ANTHROPIC_API_URL', 'https://api.anthropic.com')),
        'timeout' => (int) env('AI_CHAT_TIMEOUT', 15),
        'keep_alive' => env('AI_CHAT_KEEP_ALIVE', '30m'),
    ],

    'vnpay' => [
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'return_url' => env('VNPAY_RETURN_URL')
            ?: rtrim(env('APP_URL', 'http://127.0.0.1:8000'), '/').'/api/payment/vnpay-return',
        'frontend_url' => env('FRONTEND_URL'),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://127.0.0.1:5173'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
