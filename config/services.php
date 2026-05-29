<?php

return [
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN', null),
        'secret' => env('MAILGUN_SECRET', null),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN', null),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID', null),
        'secret' => env('AWS_SECRET_ACCESS_KEY', null),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', null),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', null),
        'redirect' => env('GOOGLE_REDIRECT_URL', null),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', null),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
    ],

    'ecobot' => [
        'endpoint' => env('ECOBOT_ENDPOINT', 'https://api.ecobot.ai/v1'),
        'api_key' => env('ECOBOT_API_KEY', null),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY', null),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'site_url' => env('OPENROUTER_SITE_URL', 'https://mindful-au.local'),
        'site_name' => env('OPENROUTER_SITE_NAME', 'Mindful AU'),
        'chat_model' => env('OPENROUTER_CHAT_MODEL', 'mistralai/mistral-7b-instruct:free'),
        'core_model' => env('OPENROUTER_CORE_MODEL', 'qwen/qwen3-next-80b-a3b-thinking'),
        'heavy_analysis_model' => env('OPENROUTER_HEAVY_ANALYSIS_MODEL', 'deepseek/deepseek-v4-pro'),
        'speed_model' => env('OPENROUTER_SPEED_MODEL', 'liquid/lfm-2.5-1.2b-thinking:free'),
    ],

    'ai' => [
        'provider_timeout_seconds' => env('AI_PROVIDER_TIMEOUT_SECONDS', 8),
        'provider_connect_timeout_seconds' => env('AI_PROVIDER_CONNECT_TIMEOUT_SECONDS', 5),
        'external_diagnostics_enabled' => env('AI_EXTERNAL_DIAGNOSTICS_ENABLED', false),
        'admin_ml_student_limit' => env('AI_ADMIN_ML_STUDENT_LIMIT', 2000),
    ],
];
