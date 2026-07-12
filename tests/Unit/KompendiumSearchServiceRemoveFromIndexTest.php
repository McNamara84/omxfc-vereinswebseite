<?php

namespace Tests\Unit;

use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\EngineManager;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;

class KompendiumSearchServiceRemoveFromIndexTest extends TestCase
{
    use RefreshDatabase;

    private KompendiumSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new KompendiumSearchService;
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_ignores_missing_typesense_documents_when_removing_from_the_index(): void
    {
        config(['scout.driver' => 'typesense']);

        $engine = Mockery::mock();
        $engine->shouldReceive('delete')
            ->once()
            ->andThrow(new ObjectNotFound('Document not found'));

        $engineManager = Mockery::mock(EngineManager::class);
        $engineManager->shouldReceive('engine')->once()->andReturn($engine);
        $this->app->instance(EngineManager::class, $engineManager);

        $this->service->removeFromIndex('romane/maddrax/001 - Test.txt');

        $this->assertTrue(true);
    }

    #[Test]
    public function it_reraises_other_typesense_errors_when_removing_from_the_index(): void
    {
        config(['scout.driver' => 'typesense']);

        $engine = Mockery::mock();
        $engine->shouldReceive('delete')
            ->once()
            ->andThrow(new TypesenseClientError('Unauthorized'));

        $engineManager = Mockery::mock(EngineManager::class);
        $engineManager->shouldReceive('engine')->once()->andReturn($engine);
        $this->app->instance(EngineManager::class, $engineManager);

        $this->expectException(TypesenseClientError::class);
        $this->expectExceptionMessage('Unauthorized');

        $this->service->removeFromIndex('romane/maddrax/001 - Test.txt');
    }
}
