<?php

declare(strict_types=1);

namespace Tools\Ci;

// This check runs before Composer dependencies are installed.
$minimumVersion = '8.6.0';

if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 6
    || ! preg_match('/^8\.6\.\d+$/D', PHP_VERSION)
    || version_compare(PHP_VERSION, $minimumVersion, '<')) {
    fwrite(STDERR, sprintf("This job requires stable PHP 8.6 >= %s; running PHP %s.\n", $minimumVersion, PHP_VERSION));
    exit(1);
}

printf("Verified PHP %s (stable 8.6 branch, minimum %s).\n", PHP_VERSION, $minimumVersion);
