<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\TypesenseEngine;
use Mockery;
use Typesense\Collections;
use Typesense\Operations;

trait CloneKompendiumIndexTestFixtures
{
    use RefreshDatabase;

    private function collections(bool $snapshotSucceeds = true): Collections
    {
        config(['scout.driver' => 'typesense', 'scout.prefix' => '', 'kompendium.search.index_variant' => 'lexical', 'kompendium.search.index_version' => 1]);
        $collections = Mockery::mock(Collections::class);
        $engine = Mockery::mock(TypesenseEngine::class);
        $engine->shouldReceive('getCollections')->andReturn($collections);
        $operations = Mockery::mock(Operations::class);
        $operations->shouldReceive('perform')->with('snapshot')->andReturn(['success' => $snapshotSucceeds]);
        $engine->shouldReceive('getOperations')->andReturn($operations);
        $this->mock(EngineManager::class)->shouldReceive('engine')->andReturn($engine);

        return $collections;
    }
}
