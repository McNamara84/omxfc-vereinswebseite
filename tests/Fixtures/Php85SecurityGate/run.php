<?php

declare(strict_types=1);

namespace Tools\Ci;

// Only this subprocess fixture shadows runtime constants. The standalone gate
// falls back to PHP's built-in constants without accepting version overrides.
if (count($argv) !== 2) {
    fwrite(STDERR, "Usage: run.php <PHP version>\n");
    exit(2);
}

$version = $argv[1];
[$major, $minor] = explode('.', $version);
define(__NAMESPACE__.'\\PHP_VERSION', $version);
define(__NAMESPACE__.'\\PHP_MAJOR_VERSION', (int) $major);
define(__NAMESPACE__.'\\PHP_MINOR_VERSION', (int) $minor);

require dirname(__DIR__, 3).'/tools/ci/verify-php85.php';
