<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Romantausch;

use App\Services\Romantausch\SwapMatchingService;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(SwapMatchingServiceTestFixtures::class);

beforeEach(function (): void {
    $this->service = app(SwapMatchingService::class);
});

test('build book key format', function (): void {
    $key = $this->service->buildBookKey('Maddrax', 42);
    expect($key)->toEqual('Maddrax::42');
});

test('build book key with special series', function (): void {
    $key = $this->service->buildBookKey('Das Volk der Tiefe', 1);
    expect($key)->toEqual('Das Volk der Tiefe::1');
});

test('build book key consistency', function (): void {
    // Gleiche Eingaben sollten immer gleiche Keys erzeugen
    $key1 = $this->service->buildBookKey('Series', 10);
    $key2 = $this->service->buildBookKey('Series', 10);
    expect($key2)->toEqual($key1);
});

test('build book key different for different series', function (): void {
    $key1 = $this->service->buildBookKey('SeriesA', 1);
    $key2 = $this->service->buildBookKey('SeriesB', 1);
    expect($key2)->not->toEqual($key1);
});

test('build book key different for different numbers', function (): void {
    $key1 = $this->service->buildBookKey('Series', 1);
    $key2 = $this->service->buildBookKey('Series', 2);
    expect($key2)->not->toEqual($key1);
});
