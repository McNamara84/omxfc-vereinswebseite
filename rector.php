<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\BoolReturnTypeFromBooleanConstAndStrictReturnsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/tests/Unit/MaddraxikonIdentityHmacPeppersTest.php',
        __DIR__.'/tests/Unit/UriSupportTest.php',
        __DIR__.'/app/Support/UriSupport.php',
        __DIR__.'/app/Support/MaddraxikonIdentityHmacPeppers.php',
        __DIR__.'/app/Support/ScheduleInventory.php',
        __DIR__.'/app/Support/CoverRatings/CoverSyncInterval.php',
        __DIR__.'/app/Listeners/LogScheduledTaskFailure.php',
        __DIR__.'/app/View/Components/Concerns/HasAccessibleLabel.php',
    ])
    ->withCache(__DIR__.'/.cache/rector')
    ->withPhpSets(php85: true)
    ->withRules([
        BoolReturnTypeFromBooleanConstAndStrictReturnsRector::class,
    ])
    ->withSets([
        PestSetList::CODING_STYLE,
    ]);
