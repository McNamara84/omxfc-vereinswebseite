<?php

declare(strict_types=1);

use Pest\Rector\Rules\SimplifyToLiteralBooleanRector;
use Pest\Rector\Rules\UseToBeEmptyRector;
use Pest\Rector\Rules\UseToMatchArrayRector;
use Pest\Rector\Rules\UseToMatchObjectRector;
use Pest\Rector\Rules\UseToThrowRector;
use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use Rector\TypeDeclaration\Rector\ClassMethod\BoolReturnTypeFromBooleanConstAndStrictReturnsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/tests/Unit',
        __DIR__.'/tests/Integration',
        __DIR__.'/tests/Feature',
        __DIR__.'/app/Support/UriSupport.php',
        __DIR__.'/app/Support/MaddraxikonIdentityHmacPeppers.php',
        __DIR__.'/app/Support/ScheduleInventory.php',
        __DIR__.'/app/Support/CoverRatings/CoverSyncInterval.php',
        __DIR__.'/app/Listeners/LogScheduledTaskFailure.php',
        __DIR__.'/app/View/Components/Concerns/HasAccessibleLabel.php',
    ])
    ->withCache(__DIR__.'/.cache/rector')
    ->withPhpSets(php85: true)
    ->withSkip([
        // toMatchArray() compares loosely; retain each original toBe()/toEqual() contract.
        UseToMatchArrayRector::class,
        // Empty literals and object identity also retain their original strict checks.
        UseToBeEmptyRector::class,
        SimplifyToLiteralBooleanRector::class,
        UseToMatchObjectRector::class,
        // Catch blocks may also verify messages and cleanup side effects.
        UseToThrowRector::class,
        // Keep Rector idempotent with Pint's constructor-call parentheses style.
        NewMethodCallWithoutParenthesesRector::class,
    ])
    ->withRules([
        BoolReturnTypeFromBooleanConstAndStrictReturnsRector::class,
    ])
    ->withSets([
        PestSetList::CODING_STYLE,
    ]);
