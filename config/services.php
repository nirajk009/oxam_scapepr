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

    'oxaam' => [
        'base_url' => env('OXAAM_BASE_URL', 'https://www.oxaam.com'),
        'homepage_path' => env('OXAAM_HOMEPAGE_PATH', '/'),
        'dashboard_path' => env('OXAAM_DASHBOARD_PATH', '/dashboard.php'),
        'login_path' => env('OXAAM_LOGIN_PATH', '/login.php'),
        'service_key' => env('OXAAM_SERVICE_KEY', 'cgai'),
        'max_session_uses' => (int) env('OXAAM_MAX_SESSION_USES', 300),
        'timeout' => (int) env('OXAAM_TIMEOUT', 20),
        'transport' => env('OXAAM_TRANSPORT', 'curl_binary'),
        'web_trigger' => env('OXAAM_WEB_TRIGGER', 'queue_worker'),
        'rotate_session_after_batch' => env('OXAAM_ROTATE_SESSION_AFTER_BATCH', true),
        'verify_ssl' => env('OXAAM_VERIFY_SSL', false),
        'curl_binary' => env('OXAAM_CURL_BINARY', 'curl.exe'),
        'user_agent' => env(
            'OXAAM_USER_AGENT',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            .'(KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'
        ),
    ],

];
