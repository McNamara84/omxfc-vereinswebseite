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
    'nextcloud' => [
        'links' => [
            '2026' => env('NEXTCLOUD_LINK_2026', ''),
            '2025' => env('NEXTCLOUD_LINK_2025', 'https://cloud.maddrax-fanclub.de/s/jnGa6sEecKa3fiX'),
            '2024' => env('NEXTCLOUD_LINK_2024', 'https://cloud.maddrax-fanclub.de/s/tztWY5ML5XMRWPw'),
            '2023' => env('NEXTCLOUD_LINK_2023', 'https://cloud.maddrax-fanclub.de/s/jjpfnJbgStE8LcQ'),
        ],
    ],

    'paypal' => [
        'me_username' => env('PAYPAL_ME_USERNAME', 'OfficialMaddraxFanclub'),
        'fantreffen_email' => env('PAYPAL_FANTREFFEN_EMAIL', 'vorstand@maddrax-fanclub.de'),
    ],

    'fantreffen' => [
        'tshirt_deadline' => env('FANTREFFEN_TSHIRT_DEADLINE', '2026-02-28 23:59:59'),
        'min_form_time' => (int) env('FANTREFFEN_MIN_FORM_TIME', 3),
        'disable_rate_limit' => filter_var(env('FANTREFFEN_DISABLE_RATE_LIMIT', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'meetings' => [
        'zoom_links' => [
            'maddraxikon' => env('ZOOM_LINK_MADDRAXIKON'),
            'fanhoerbuch' => env('ZOOM_LINK_HOERBUECHER'),
            'mapdrax' => env('ZOOM_LINK_MAPDRAX'),
            'stammtisch' => env('ZOOM_LINK_STAMMTISCH'),
        ],
    ],

];
