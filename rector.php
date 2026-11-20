<?php

declare(strict_types=1);

use Pest\Rector\Rules\ConvertAssertToExpectRector;
use Pest\Rector\Rules\SimplifyToLiteralBooleanRector;
use Pest\Rector\Rules\UseToBeEmptyRector;
use Pest\Rector\Rules\UseToMatchArrayRector;
use Pest\Rector\Rules\UseToMatchObjectRector;
use Pest\Rector\Rules\UseToThrowRector;
use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use Rector\Php86\Rector\FuncCall\MinMaxToClampRector;
use Rector\TypeDeclaration\Rector\ClassMethod\BoolReturnTypeFromBooleanConstAndStrictReturnsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/bootstrap/providers.php',
        __DIR__.'/config',
        __DIR__.'/database/factories',
        __DIR__.'/database/migrations',
        __DIR__.'/database/seeders',
        __DIR__.'/lang',
        __DIR__.'/routes',
        __DIR__.'/tests',
        __DIR__.'/tools',
        __DIR__.'/public/index.php',
        __DIR__.'/artisan',
        __DIR__.'/server.php',
    ])
    ->withCache(__DIR__.'/.cache/rector')
    ->withPhpSets(php86: true)
    ->withSkip([
        // Keep assertion diagnostics and argument side effects in shared fixtures.
        ConvertAssertToExpectRector::class,
        // clamp() throws on inverted bounds and handles NAN differently. Review
        // each migration manually instead of rewriting arbitrary nested bounds.
        MinMaxToClampRector::class,
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
