<?php

$maddraxikonBaseUrl = rtrim((string) env(
    'MADDRAXIKON_BASE_URL',
    'https://de.maddraxikon.com'
), '/');
$configuredOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('COVER_RATINGS_ALLOWED_MEDIA_ORIGINS', $maddraxikonBaseUrl)),
)));

return [
    'enabled' => filter_var(
        env('COVER_RATINGS_ENABLED', false),
        FILTER_VALIDATE_BOOLEAN,
    ),
    'sync_enabled' => filter_var(
        env('COVER_RATINGS_SYNC_ENABLED', false),
        FILTER_VALIDATE_BOOLEAN,
    ),
    'results_min_votes' => max(
        1,
        (int) env('COVER_RATINGS_RESULTS_MIN_VOTES', 3),
    ),
    'sync' => [
        'batch_size' => min(
            50,
            max(1, (int) env('COVER_RATINGS_SYNC_BATCH_SIZE', 25)),
        ),
        'interval_hours' => max(
            1,
            (int) env('COVER_RATINGS_SYNC_INTERVAL_HOURS', 24),
        ),
    ],
    'images' => [
        'disk' => env('COVER_RATINGS_IMAGE_DISK', 'private'),
        'directory' => trim(
            (string) env('COVER_RATINGS_IMAGE_DIRECTORY', 'cover-ratings'),
            '/',
        ),
        'allowed_origins' => $configuredOrigins,
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        'max_bytes' => max(
            1024,
            (int) env('COVER_RATINGS_IMAGE_MAX_BYTES', 15 * 1024 * 1024),
        ),
        'max_pixels' => max(
            1_000_000,
            (int) env('COVER_RATINGS_IMAGE_MAX_PIXELS', 40_000_000),
        ),
        'small_width' => max(
            120,
            (int) env('COVER_RATINGS_IMAGE_SMALL_WIDTH', 360),
        ),
        'large_width' => max(
            360,
            (int) env('COVER_RATINGS_IMAGE_LARGE_WIDTH', 720),
        ),
        'webp_quality' => min(
            100,
            max(1, (int) env('COVER_RATINGS_IMAGE_WEBP_QUALITY', 82)),
        ),
        'connect_timeout' => max(
            1,
            (int) env('COVER_RATINGS_IMAGE_CONNECT_TIMEOUT', 5),
        ),
        'timeout' => max(
            1,
            (int) env('COVER_RATINGS_IMAGE_TIMEOUT', 20),
        ),
    ],
];
