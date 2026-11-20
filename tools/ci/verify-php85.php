<?php

declare(strict_types=1);

// This check runs before Composer dependencies are installed.
$minimumVersion = '8.5.13';

if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 5 || version_compare(PHP_VERSION, $minimumVersion, '<')) {
    fwrite(STDERR, sprintf("This job requires PHP 8.5 >= %s; running PHP %s.\n", $minimumVersion, PHP_VERSION));
    exit(1);
}

printf("Verified PHP %s (8.5 branch, minimum %s).\n", PHP_VERSION, $minimumVersion);
