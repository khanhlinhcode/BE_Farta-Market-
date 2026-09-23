<?php

$aiChatDriver = env('AI_CHAT_DRIVER', 'groq');
$aiChatModel = match ($aiChatDriver) {
    'groq' => 'openai/gpt-oss-20b',
    'ollama' => 'qwen3:4b',
    default => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
};
$aiChatBaseUrl = match ($aiChatDriver) {
    'groq' => 'https://api.groq.com/openai/v1',
    'ollama' => 'http://127.0.0.1:11434',
    default => env('ANTHROPIC_API_URL', 'https://api.anthropic.com'),
};

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

    'cloudinary' => [
        'base_url' => env('CLOUDINARY_API_BASE_URL', 'https://api.cloudinary.com'),
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],

    'anthropic' => [
        'driver' => env('AI_CHAT_DRIVER', 'anthropic'),
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        'base_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com'),
    ],

    'ai_chat' => [
        'enabled' => filter_var(env('AI_CHAT_ENABLED', true), FILTER_VALIDATE_BOOL),
        'driver' => $aiChatDriver,
        'key' => $aiChatDriver === 'groq' ? env('GROQ_API_KEY') : env('ANTHROPIC_API_KEY'),
        'model' => env('AI_CHAT_MODEL', $aiChatModel),
        'base_url' => env('AI_CHAT_BASE_URL', $aiChatBaseUrl),
        'timeout' => (int) env('AI_CHAT_TIMEOUT', 15),
        'keep_alive' => env('AI_CHAT_KEEP_ALIVE', '30m'),
        'orchestration' => env('AI_CHAT_ORCHESTRATION', 'router'),
        'product_search_mode' => env('AI_PRODUCT_SEARCH_MODE', 'database'),
        'vector_search_enabled' => filter_var(env('AI_VECTOR_SEARCH_ENABLED', false), FILTER_VALIDATE_BOOL),
        'prompt_version' => env('AI_CHAT_PROMPT_VERSION', 'catalog-v2'),
        'debug_log' => filter_var(env('AI_CHAT_DEBUG_LOG', false), FILTER_VALIDATE_BOOL),
    ],

    'sepay' => [
        'bank_code' => env('SEPAY_BANK_CODE'),
        'account_number' => env('SEPAY_ACCOUNT_NUMBER'),
        'account_holder' => env('SEPAY_ACCOUNT_HOLDER'),
        'webhook_secret' => env('SEPAY_WEBHOOK_SECRET'),
        'payment_prefix' => env('SEPAY_PAYMENT_PREFIX', 'FM'),
        'payment_ttl_minutes' => (int) env('SEPAY_PAYMENT_TTL_MINUTES', 30),
        'webhook_tolerance_seconds' => (int) env('SEPAY_WEBHOOK_TOLERANCE_SECONDS', 300),
        'qr_base_url' => env('SEPAY_QR_BASE_URL', 'https://vietqr.app/img'),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://127.0.0.1:5173'),
    ],

    'analytics' => [
        'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 90),
        'token_ttl_minutes' => (int) env('ANALYTICS_TOKEN_TTL_MINUTES', 30),
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ANALYTICS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://127.0.0.1:5173')))
        ))),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
        'required' => env('APP_ENV', 'production') === 'production'
            || filter_var(env('TURNSTILE_REQUIRED', false), FILTER_VALIDATE_BOOL),
        'guest_order_ttl_minutes' => (int) env('GUEST_ORDER_TTL_MINUTES', 120),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
