<?php

return [
    'locale' => env('ROMANTAUSCH_LOCALE', env('APP_LOCALE', 'de')),
    'fallback_locale' => env('ROMANTAUSCH_FALLBACK_LOCALE', env('APP_FALLBACK_LOCALE', 'de')),

    /*
    |--------------------------------------------------------------------------
    | Bundle-Vorschau Kompakt-Schwellenwert
    |--------------------------------------------------------------------------
    |
    | Ab dieser Anzahl von Buchnummern wechselt die Frontend-Vorschau von
    | individueller Auflistung (1, 2, 3, ...) zu kompakten Bereichen (1-20, ...).
    |
    | Verwendung in Blade-Views:
    |   window.COMPACT_THRESHOLD = {{ config('romantausch.compact_threshold') }};
    |
    */
    'compact_threshold' => env('ROMANTAUSCH_COMPACT_THRESHOLD', 20),
];
