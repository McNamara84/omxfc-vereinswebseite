<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Http\Controllers\KompendiumController;
use App\Services\KompendiumSearchService;
use App\Services\KompendiumService;
use App\Services\RewardService;
use PHPUnit\Framework\Attributes\CoversMethod;
use Tests\Support\PhpunitMetadata;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KompendiumPathValidationTestFixtures::class);

beforeEach(function (): void {
    // Controller mit Stubs erstellen (keine Expectations nötig)
    $this->controller = new KompendiumController($this->createStub(KompendiumService::class), $this->createStub(KompendiumSearchService::class), $this->createStub(RewardService::class));
});

test('accepts valid roman path', function (): void {
    expect($this->isValidRomanPath('romane/maddrax/001 - Der Gott aus dem Eis.txt'))->toBeTrue();
});

test('accepts path with nested directories', function (): void {
    expect($this->isValidRomanPath('romane/maddrax/zyklus-1/001 - Titel.txt'))->toBeTrue();
});

test('accepts path with periods in filename', function (): void {
    expect($this->isValidRomanPath('romane/maddrax/001 - Dr. Jones.txt'))->toBeTrue();
});

test('accepts path with period in series name', function (): void {
    // Ein Serienname wie "series.name" sollte erlaubt sein
    expect($this->isValidRomanPath('romane/series.name/001 - Titel.txt'))->toBeTrue();
});

test('rejects simple parent directory traversal', function (): void {
    $this->expectRejectedPathWarning();
    expect($this->isValidRomanPath('../.env'))->toBeFalse();
});

test('rejects traversal at path start', function (): void {
    $this->expectRejectedPathWarning();
    expect($this->isValidRomanPath('romane/../../../.env'))->toBeFalse();
});

test('rejects traversal in middle of path', function (): void {
    $this->expectRejectedPathWarning();
    expect($this->isValidRomanPath('romane/maddrax/../../../etc/passwd'))->toBeFalse();
});

test('rejects current directory reference', function (): void {
    $this->expectRejectedPathWarning();
    expect($this->isValidRomanPath('./romane/maddrax/001 - Titel.txt'))->toBeFalse();
});

test('rejects hidden traversal with backslashes', function (): void {
    $this->expectRejectedPathWarning();
    expect($this->isValidRomanPath('romane\..\..\..\secret.txt'))->toBeFalse();
});

test('rejects path not starting with romane', function (): void {
    $this->expectRejectedPathWarning();
    expect($this->isValidRomanPath('andere/ordner/datei.txt'))->toBeFalse();
});

test('rejects absolute path', function (): void {
    $this->expectRejectedPathWarning();
    expect($this->isValidRomanPath('/etc/passwd'))->toBeFalse();
});

test('rejects windows absolute path', function (): void {
    $this->expectRejectedPathWarning();
    expect($this->isValidRomanPath('C:\Windows\System32\config'))->toBeFalse();
});

test('rejects similar but not exact base path', function (): void {
    // "romane_fake" startet nicht mit "romane/"
    $this->expectRejectedPathWarning();
    expect($this->isValidRomanPath('romane_fake/maddrax/001.txt'))->toBeFalse();
});

PhpunitMetadata::onFile(__FILE__, CoversMethod::class, KompendiumController::class, 'isValidRomanPath');
