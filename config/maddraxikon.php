<?php

use App\Support\MaddraxikonIdentityHmacPeppers;

$baseUrl = rtrim((string) env('MADDRAXIKON_BASE_URL', 'https://de.maddraxikon.com'), '/');
$identityHmacPeppers = MaddraxikonIdentityHmacPeppers::parse(
    (string) env('MADDRAXIKON_IDENTITY_HMAC_PEPPERS', ''),
);

return [
    'wiki_key' => env('MADDRAXIKON_WIKI_KEY', 'maddraxikon-de'),
    'base_url' => $baseUrl,
    'api_url' => $baseUrl.'/api.php',
    'consent_version' => env('MADDRAXIKON_CONSENT_VERSION', '2026-07-18'),
    'identity_hmac_peppers' => $identityHmacPeppers,

    'oauth' => [
        'authorize_url' => $baseUrl.'/rest.php/oauth2/authorize',
        'token_url' => $baseUrl.'/rest.php/oauth2/access_token',
        'profile_url' => $baseUrl.'/rest.php/oauth2/resource/profile',
    ],

    'features' => [
        'linking_enabled' => filter_var(
            env('MADDRAXIKON_LINKING_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'sync_enabled' => filter_var(
            env('MADDRAXIKON_SYNC_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'awards_enabled' => filter_var(
            env('MADDRAXIKON_AWARDS_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    'allowed_namespaces' => [0, 10, 14, 102, 106, 108, 112, 420],

    'expected_namespace_names' => [
        0 => '',
        10 => 'Vorlage',
        14 => 'Kategorie',
        102 => 'Attribut',
        106 => 'Formular',
        108 => 'Konzept',
        112 => 'SMW/Schema',
        420 => 'GeoJson',
    ],

    'article_namespace' => 0,
    'minimum_article_bytes' => 500,
    'session_window_minutes' => 30,
    'evaluation_delay_hours' => 24,
    'daily_point_cap' => 10,
    'timezone' => 'Europe/Berlin',

    'evaluation' => [
        'source_batch_size' => min(
            1000,
            max(
                1,
                (int) env('MADDRAXIKON_EVALUATION_SOURCE_BATCH_SIZE', 100)
            )
        ),
        'api_batch_size' => min(
            50,
            max(1, (int) env('MADDRAXIKON_EVALUATION_API_BATCH_SIZE', 50))
        ),
    ],

    'sync' => [
        'interval_minutes' => 15,
        'overlap_minutes' => 10,
        'max_window_minutes' => max(
            1,
            (int) env('MADDRAXIKON_SYNC_MAX_WINDOW_MINUTES', 360)
        ),
        // Configure this no higher than the wiki's guaranteed $wgRCMaxAge.
        'recent_changes_retention_days' => max(
            1,
            (int) env('MADDRAXIKON_RECENT_CHANGES_RETENTION_DAYS', 30)
        ),
        'recovery_max_window_days' => max(
            1,
            (int) env('MADDRAXIKON_RECOVERY_MAX_WINDOW_DAYS', 90)
        ),
        'usercontribs_batch_size' => min(
            50,
            max(1, (int) env('MADDRAXIKON_USERCONTRIBS_BATCH_SIZE', 50))
        ),
    ],

    'monitoring' => [
        'pending_stale_hours' => max(
            1,
            (int) env('MADDRAXIKON_MONITOR_PENDING_STALE_HOURS', 26)
        ),
        'import_stale_minutes' => max(
            1,
            (int) env('MADDRAXIKON_MONITOR_IMPORT_STALE_MINUTES', 60)
        ),
        'scheduler_stale_minutes' => max(
            1,
            (int) env('MADDRAXIKON_MONITOR_SCHEDULER_STALE_MINUTES', 5)
        ),
        'queue_backlog_limit' => max(
            1,
            (int) env('MADDRAXIKON_MONITOR_QUEUE_BACKLOG_LIMIT', 100)
        ),
        'queue_oldest_minutes' => max(
            1,
            (int) env('MADDRAXIKON_MONITOR_QUEUE_OLDEST_MINUTES', 30)
        ),
        'consecutive_failure_limit' => max(
            1,
            (int) env('MADDRAXIKON_MONITOR_CONSECUTIVE_FAILURE_LIMIT', 3)
        ),
    ],

    'privacy' => [
        'correction_audit_retention_days' => max(
            365,
            (int) env('MADDRAXIKON_CORRECTION_AUDIT_RETENTION_DAYS', 3650)
        ),
    ],

    'http' => [
        'user_agent' => env(
            'MADDRAXIKON_USER_AGENT',
            'OMXFC-Vereinswebsite/1.0 (https://maddrax-fanclub.de; info@maddraxikon.com)'
        ),
        'connect_timeout' => (int) env('MADDRAXIKON_HTTP_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('MADDRAXIKON_HTTP_TIMEOUT', 15),
        'attempts' => (int) env('MADDRAXIKON_HTTP_ATTEMPTS', 3),
        'retry_delay_ms' => (int) env('MADDRAXIKON_HTTP_RETRY_DELAY_MS', 500),
        'retry_max_delay_ms' => (int) env(
            'MADDRAXIKON_HTTP_RETRY_MAX_DELAY_MS',
            5000
        ),
        'maxlag' => (int) env('MADDRAXIKON_API_MAXLAG', 5),
    ],
];
