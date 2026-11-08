<?php

declare(strict_types=1);

use Pest\TestSuite;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Tests\Support\PhpunitMetadata;

PhpunitMetadata::method(test('native PHPUnit method metadata reaches the generated Pest method', function (): void {
    $method = new ReflectionMethod($this, $this->name());

    expect($method->getAttributes(WithoutErrorHandler::class))->toHaveCount(1);
}), WithoutErrorHandler::class);

test('native PHPUnit class metadata reaches the generated Pest class', function (): void {
    $class = new ReflectionClass($this);

    expect($class->getAttributes(CoversNothing::class))->toHaveCount(1)
        ->and($class->getAttributes(Large::class))->toHaveCount(1);

    // An excluded file has no factory; metadata must not recreate it or fail.
    $unregistered = dirname(__DIR__).'/Support/PhpunitMetadata.php';
    $repository = TestSuite::getInstance()->tests;
    expect($repository->get($unregistered))->toBeNull();
    PhpunitMetadata::onFile($unregistered, Large::class);
    expect($repository->get($unregistered))->toBeNull();
});

PhpunitMetadata::onFile(__FILE__, CoversNothing::class);
PhpunitMetadata::onFile(__FILE__, Large::class);
