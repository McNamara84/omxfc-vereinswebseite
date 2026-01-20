<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Page Layout (Livewire 4)
    |--------------------------------------------------------------------------
    | The view that will be used as the layout when rendering a single component
    | as an entire page via `Route::livewire()`.
    */
    'component_layout' => 'layouts.app',

    /*
    |--------------------------------------------------------------------------
    | Class Namespace
    |--------------------------------------------------------------------------
    */
    'class_namespace' => 'App\\Livewire',

    /*
    |--------------------------------------------------------------------------
    | Class Path
    |--------------------------------------------------------------------------
    */
    'class_path' => app_path('Livewire'),

    /*
    |--------------------------------------------------------------------------
    | View Path
    |--------------------------------------------------------------------------
    */
    'view_path' => resource_path('views/livewire'),

    /*
    |--------------------------------------------------------------------------
    | Make Command (Livewire 4)
    |--------------------------------------------------------------------------
    | Default configuration for `php artisan make:livewire`.
    | Using 'class' matches v3 behavior (class-based components).
    */
    'make_command' => [
        'type' => 'class', // Options: 'sfc', 'mfc', 'class'
        'emoji' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Smart Wire Keys (Livewire 4)
    |--------------------------------------------------------------------------
    | Helps prevent wire:key issues on deeply nested components.
    */
    'smart_wire_keys' => true,

    /*
    |--------------------------------------------------------------------------
    | Temporary File Uploads
    |--------------------------------------------------------------------------
    */
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => null,
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a', 'jpg', 'jpeg',
            'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Render On Redirect
    |--------------------------------------------------------------------------
    */
    'render_on_redirect' => false,

    /*
    |--------------------------------------------------------------------------
    | Legacy Model Binding
    |--------------------------------------------------------------------------
    */
    'legacy_model_binding' => false,

    /*
    |--------------------------------------------------------------------------
    | Auto-inject Frontend Assets
    |--------------------------------------------------------------------------
    | Livewire 4 auto-injects its bundled Alpine.js and Livewire scripts.
    | We enable this to ensure Alpine is available for all components.
    */
    'inject_assets' => true,

    /*
    |--------------------------------------------------------------------------
    | Navigate (SPA mode)
    |--------------------------------------------------------------------------
    */
    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#2299dd',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTML Morph Markers
    |--------------------------------------------------------------------------
    */
    'inject_morph_markers' => true,

    /*
    |--------------------------------------------------------------------------
    | Pagination Theme
    |--------------------------------------------------------------------------
    */
    'pagination_theme' => 'tailwind',
];
