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

    'sppra' => [
        'url' => 'https://esppra.co.sz/sppra/tender.php',
    ],

    'assistant_ai' => [
        'provider' => env('AI_PROVIDER', 'nvidia'),
        'remote_enabled' => filter_var(env('AI_REMOTE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'context_record_limit' => (int) env('AI_CONTEXT_RECORD_LIMIT', 500),
        'nvidia' => [
            'base_url' => env('NVIDIA_API_BASE_URL', 'https://integrate.api.nvidia.com/v1'),
            'api_key' => env('NVIDIA_API_KEY'),
            'model' => env('NVIDIA_AI_MODEL', 'deepseek-ai/deepseek-v4-pro'),
            'verify' => env('AI_HTTP_VERIFY', true),
            'temperature' => (float) env('AI_TEMPERATURE', 0.8),
            'top_p' => (float) env('AI_TOP_P', 0.95),
            'max_tokens' => (int) env('AI_MAX_TOKENS', 2048),
            'timeout' => (int) env('AI_TIMEOUT_SECONDS', 60),
        ],
    ],

];
