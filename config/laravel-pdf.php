<?php

return [
    'driver' => env('LARAVEL_PDF_DRIVER', 'dompdf'),

    'dompdf' => [
        'font_dir' => storage_path('app/private/pdf-fonts'),
        'font_cache' => storage_path('app/private/pdf-fonts'),
        'is_remote_enabled' => env('LARAVEL_PDF_DOMPDF_REMOTE_ENABLED', false),
        'chroot' => env('LARAVEL_PDF_DOMPDF_CHROOT'),
    ],
];
