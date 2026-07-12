<?php

namespace Tests\Unit;

use App\Models\RomanExcerpt;
use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\TypesenseEngine;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;

class KompendiumSearchServiceIndexExistsTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    private KompendiumSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing-kompendium-search-service');
        File::ensureDirectoryExists($this->testStoragePath);

        config(['scout.tntsearch.storage' => $this->testStoragePath]);

        $this->service = new KompendiumSearchService;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_returns_false_for_non_supported_drivers_even_if_a_legacy_index_file_exists(): void
    {
        config(['scout.driver' => 'null']);

        $indexName = (new RomanExcerpt)->searchableAs();
        file_put_contents($this->testStoragePath.DIRECTORY_SEPARATOR.$indexName.'.index', 'legacy-index');

        $engineManager = Mockery::mock(EngineManager::class);
        $engineManager->shouldNotReceive('engine');
        $this->app->instance(EngineManager::class, $engineManager);

        $this->assertFalse($this->service->indexExists());
    }

    #[Test]
    public function it_uses_the_legacy_index_check_only_for_tntsearch(): void
    {
        config(['scout.driver' => 'tntsearch']);

        $indexName = (new RomanExcerpt)->searchableAs();
        file_put_contents($this->testStoragePath.DIRECTORY_SEPARATOR.$indexName.'.index', 'legacy-index');

        $engineManager = Mockery::mock(EngineManager::class);
        $engineManager->shouldNotReceive('engine');
        $this->app->instance(EngineManager::class, $engineManager);

        $this->assertTrue($this->service->indexExists());
    }

    #[Test]
    public function it_returns_false_when_the_typesense_collection_is_missing(): void
    {
        config(['scout.driver' => 'typesense']);

        $collection = new class
        {
            public function retrieve(): void
            {
                throw new ObjectNotFound('Collection not found');
            }
        };

        $collections = new class($collection)
        {
            public function __construct(
                private readonly object $collection,
            ) {}

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

        $this->assertFalse($this->service->indexExists());
    }

    #[Test]
    public function it_reraises_typesense_errors_that_are_not_collection_not_found(): void
    {
        config(['scout.driver' => 'typesense']);

        $collection = new class
        {
            public function retrieve(): void
            {
                throw new TypesenseClientError('Unauthorized');
            }
        };

        $collections = new class($collection)
        {
            public function __construct(
                private readonly object $collection,
            ) {}

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

        $this->expectException(TypesenseClientError::class);
        $this->expectExceptionMessage('Unauthorized');

        $this->service->indexExists();
    }
}
