<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\KompendiumSearchService;
use Laravel\Scout\EngineManager;
use Mockery;
use Tests\TestCase;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;

pest()->extend(TestCase::class)->use(KompendiumSearchServiceRemoveFromIndexTestFixtures::class);

beforeEach(function (): void {
    $this->service = new KompendiumSearchService;
});

afterEach(function (): void {
    Mockery::close();
});

test('it ignores missing typesense documents when removing from the index', function (): void {
    config(['scout.driver' => 'typesense']);
    $engine = Mockery::mock();
    $engine->shouldReceive('delete')->once()->andThrow(new ObjectNotFound('Document not found'));
    $engineManager = Mockery::mock(EngineManager::class);
    $engineManager->shouldReceive('engine')->once()->andReturn($engine);
    $this->app->instance(EngineManager::class, $engineManager);
    $this->service->removeFromIndex('romane/maddrax/001 - Test.txt');
    expect(true)->toBeTrue();
});

test('it reraises other typesense errors when removing from the index', function (): void {
    config(['scout.driver' => 'typesense']);
    $engine = Mockery::mock();
    $engine->shouldReceive('delete')->once()->andThrow(new TypesenseClientError('Unauthorized'));
    $engineManager = Mockery::mock(EngineManager::class);
    $engineManager->shouldReceive('engine')->once()->andReturn($engine);
    $this->app->instance(EngineManager::class, $engineManager);
    expect(fn () => $this->service->removeFromIndex('romane/maddrax/001 - Test.txt'))->toThrow(TypesenseClientError::class, 'Unauthorized');
});
