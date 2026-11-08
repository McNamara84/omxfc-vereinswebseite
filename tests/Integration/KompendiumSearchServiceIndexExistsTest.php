<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\RomanExcerpt;
use App\Services\KompendiumSearchService;
use Illuminate\Support\Facades\File;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\TypesenseEngine;
use Mockery;
use Tests\TestCase;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;

pest()->extend(TestCase::class)->use(KompendiumSearchServiceIndexExistsTestFixtures::class);

beforeEach(function (): void {
    $this->testStoragePath = base_path('storage/testing-kompendium-search-service');
    File::ensureDirectoryExists($this->testStoragePath);
    config(['scout.tntsearch.storage' => $this->testStoragePath]);
    $this->service = new KompendiumSearchService;
});

afterEach(function (): void {
    File::deleteDirectory($this->testStoragePath);
    Mockery::close();
});

test('it returns false for non supported drivers even if a legacy index file exists', function (): void {
    config(['scout.driver' => 'null']);
    $indexName = (new RomanExcerpt)->searchableAs();
    file_put_contents($this->testStoragePath.DIRECTORY_SEPARATOR.$indexName.'.index', 'legacy-index');
    $engineManager = Mockery::mock(EngineManager::class);
    $engineManager->shouldNotReceive('engine');
    $this->app->instance(EngineManager::class, $engineManager);
    expect($this->service->indexExists())->toBeFalse();
});

test('it uses the legacy index check only for tntsearch', function (): void {
    config(['scout.driver' => 'tntsearch']);
    $indexName = (new RomanExcerpt)->searchableAs();
    file_put_contents($this->testStoragePath.DIRECTORY_SEPARATOR.$indexName.'.index', 'legacy-index');
    $engineManager = Mockery::mock(EngineManager::class);
    $engineManager->shouldNotReceive('engine');
    $this->app->instance(EngineManager::class, $engineManager);
    expect($this->service->indexExists())->toBeTrue();
});

test('it returns false when the typesense collection is missing', function (): void {
    config(['scout.driver' => 'typesense']);
    $collection = new class
    {
        public function retrieve(): never
        {
            throw new ObjectNotFound('Collection not found');
        }
    };
    $collections = new readonly class($collection)
    {
        public function __construct(private object $collection) {}

        public function __get(string $name): object
        {
            return $this->collection;
        }
    };
    $engine = Mockery::mock(TypesenseEngine::class);
    $engine->shouldReceive('getCollections')->once()->andReturn($collections);
    $engineManager = Mockery::mock(EngineManager::class);
    $engineManager->shouldReceive('engine')->once()->andReturn($engine);
    $this->app->instance(EngineManager::class, $engineManager);
    expect($this->service->indexExists())->toBeFalse();
});

test('it reraises typesense errors that are not collection not found', function (): void {
    config(['scout.driver' => 'typesense']);
    $collection = new class
    {
        public function retrieve(): never
        {
            throw new TypesenseClientError('Unauthorized');
        }
    };
    $collections = new readonly class($collection)
    {
        public function __construct(private object $collection) {}

        public function __get(string $name): object
        {
            return $this->collection;
        }
    };
    $engine = Mockery::mock(TypesenseEngine::class);
    $engine->shouldReceive('getCollections')->once()->andReturn($collections);
    $engineManager = Mockery::mock(EngineManager::class);
    $engineManager->shouldReceive('engine')->once()->andReturn($engine);
    $this->app->instance(EngineManager::class, $engineManager);
    expect(fn () => $this->service->indexExists())->toThrow(TypesenseClientError::class, 'Unauthorized');
});
