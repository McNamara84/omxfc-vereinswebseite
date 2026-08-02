<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/tests/Unit/MaddraxikonIdentityHmacPeppersTest.php',
        __DIR__.'/tests/Unit/UriSupportTest.php',
    ])
    ->withSets([
        PestSetList::CODING_STYLE,
    ]);
