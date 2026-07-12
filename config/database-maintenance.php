<?php

return [
    'enabled' => env('DATABASE_MAINTENANCE_ENABLED', false),

    'dump_binary' => env('DATABASE_DUMP_BINARY', 'mariadb-dump'),
    'client_binary' => env('DATABASE_CLIENT_BINARY', 'mariadb'),

    'storage_root' => storage_path('app/private/database-maintenance'),

    'max_upload_mb' => env('DATABASE_RESTORE_MAX_UPLOAD_MB', 100),
    'proxy_limit_mb' => env('DATABASE_RESTORE_PROXY_LIMIT_MB', 110),
    'max_uncompressed_mb' => env('DATABASE_RESTORE_MAX_UNCOMPRESSED_MB', 1024),
    'multipart_overhead_mb' => env('DATABASE_RESTORE_MULTIPART_OVERHEAD_MB', 2),
    'storage_free_space_ratio' => env('DATABASE_RESTORE_STORAGE_FREE_SPACE_RATIO', 0.5),

    'pre_restore_retention_days' => env('DATABASE_PRE_RESTORE_RETENTION_DAYS', 7),
    'restore_confirmation_text' => env('DATABASE_RESTORE_CONFIRMATION_TEXT', 'DATENBANK WIEDERHERSTELLEN'),
    'process_timeout_seconds' => env('DATABASE_RESTORE_PROCESS_TIMEOUT', 300),

    'require_mysql_like_connection' => env('DATABASE_MAINTENANCE_REQUIRE_MYSQL_LIKE_CONNECTION', true),
    'require_omxfc_dump_marker' => env('DATABASE_RESTORE_REQUIRE_OMXFC_DUMP_MARKER', false),
];
