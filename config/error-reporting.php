<?php

return [
    'enabled' => env('ERROR_REPORTING_ENABLED', false),

    'environment' => 'production',

    'throttle_seconds' => (int) env('ERROR_REPORTING_THROTTLE_SECONDS', 900),
    'throttle_count_ttl_seconds' => (int) env('ERROR_REPORTING_THROTTLE_COUNT_TTL_SECONDS', 604800),

    'queue' => env('ERROR_REPORTING_QUEUE', 'default'),
    'job_delay_seconds' => (int) env('ERROR_REPORTING_JOB_DELAY_SECONDS', 5),

    'log_path' => env('ERROR_REPORTING_LOG_PATH', storage_path('logs/laravel.log')),
    'max_log_scan_kb' => (int) env('ERROR_REPORTING_MAX_LOG_SCAN_KB', 5120),
    'max_attachment_kb' => (int) env('ERROR_REPORTING_MAX_ATTACHMENT_KB', 512),

    'response_header' => env('ERROR_REPORTING_RESPONSE_HEADER', 'X-Request-ID'),

    'sensitive_keys' => [
        'access_token',
        'api_key',
        'apikey',
        'auth',
        'authorization',
        'client_secret',
        'cookie',
        'csrf',
        'database_url',
        'db_password',
        'mail_password',
        'oauth_token',
        'passwd',
        'password',
        'password_confirmation',
        'pwd',
        'refresh_token',
        'remember_token',
        'secret',
        'session',
        'token',
        'x-csrf-token',
        'x-xsrf-token',
    ],
];
