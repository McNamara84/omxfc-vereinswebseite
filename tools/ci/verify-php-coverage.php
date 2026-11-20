<?php

declare(strict_types=1);

namespace Tools\Ci;

if (phpversion('xdebug') !== '3.6.0alpha1'
    || ! function_exists('xdebug_info')
    || ! in_array('coverage', xdebug_info('mode'), true)) {
    fwrite(STDERR, "Tests require the pinned Xdebug 3.6.0alpha1 with coverage enabled.\n");
    exit(1);
}

echo "Verified pinned Xdebug 3.6.0alpha1 with coverage enabled.\n";
