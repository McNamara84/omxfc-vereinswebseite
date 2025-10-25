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

    'hcaptcha' => [
        'enabled' => env('HCAPTCHA_ENABLED', false),
        'sitekey' => env('HCAPTCHA_SITEKEY'),
        'secret' => env('HCAPTCHA_SECRET'),
        'endpoint' => env('HCAPTCHA_ENDPOINT', 'https://hcaptcha.com/siteverify'),
        'timeout' => env('HCAPTCHA_TIMEOUT', 5),
        'threshold' => env('HCAPTCHA_THRESHOLD'),
        'signature_ttl' => env('HCAPTCHA_SIGNATURE_TTL', 300),
        'bypass_token' => env('HCAPTCHA_BYPASS_TOKEN'),
        'rate_limit_per_minute' => env('HCAPTCHA_RATE_LIMIT_PER_MINUTE', 12),
    ],

    'contact' => [
        'email' => env('CONTACT_EMAIL', 'vorstand@maddrax-fanclub.de'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'nextcloud' => [
        'links' => [
            '2025' => env('NEXTCLOUD_LINK_2025', ''),
            '2024' => env('NEXTCLOUD_LINK_2024', ''),
            '2023' => env('NEXTCLOUD_LINK_2023', ''),
        ],
    ],

];
