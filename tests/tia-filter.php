<?php

declare(strict_types=1);

// Match dependency directories that Pest's TIA SourceScope already discards.
// auto_prepend_file applies this before Composer, Pest and Laravel are compiled.
if (function_exists('xdebug_set_filter')) {
    $root = dirname(__DIR__).DIRECTORY_SEPARATOR;
    xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_EXCLUDE, [
        $root.'vendor'.DIRECTORY_SEPARATOR,
        $root.'node_modules'.DIRECTORY_SEPARATOR,
    ]);
}
