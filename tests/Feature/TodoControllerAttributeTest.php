<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\TodoController;
use App\Models\Todo;
use Illuminate\Auth\Middleware\Authorize as AuthorizeMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Attributes\Controllers\Authorize as AuthorizeAttribute;
use ReflectionMethod;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

covers(TodoController::class);

test('create und store verwenden laravel 13 authorize attribute fuer create', function (): void {
    $createAttributes = (new ReflectionMethod(TodoController::class, 'create'))->getAttributes(AuthorizeAttribute::class);
    $storeAttributes = (new ReflectionMethod(TodoController::class, 'store'))->getAttributes(AuthorizeAttribute::class);
    expect($createAttributes)->toHaveCount(1)
        ->and($storeAttributes)->toHaveCount(1);
    $create = $createAttributes[0]->newInstance();
    $store = $storeAttributes[0]->newInstance();
    expect($create->middleware)->toBe(AuthorizeMiddleware::using('create', Todo::class))
        ->and($store->middleware)->toBe(AuthorizeMiddleware::using('create', Todo::class));
});

test('verify verwendet laravel 13 authorize attribute fuer verify', function (): void {
    $verifyAttributes = (new ReflectionMethod(TodoController::class, 'verify'))->getAttributes(AuthorizeAttribute::class);
    expect($verifyAttributes)->toHaveCount(1);
    $verify = $verifyAttributes[0]->newInstance();
    expect($verify->middleware)->toBe(AuthorizeMiddleware::using('verify', Todo::class));
});
