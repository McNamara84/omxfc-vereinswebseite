<?php

namespace Tests\Feature;

use App\Models\RomanExcerpt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\TypesenseEngine;
use Mockery;
use Tests\TestCase;
use Typesense\Collection;
use Typesense\Collections;
use Typesense\Exceptions\ObjectAlreadyExists;
use Typesense\Exceptions\ObjectNotFound;

class CloneKompendiumIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_lexical_versions_preserve_the_original_index_and_scout_prefix(): void
    {
        config(['scout.prefix' => 'test_', 'kompendium.search.index_variant' => 'lexical', 'kompendium.search.index_version' => 1]);
        $this->assertSame('test_roman_excerpts', (new RomanExcerpt)->searchableAs());
        $this->assertSame('test_roman_excerpts_lexical_v2', RomanExcerpt::indexNameFor('lexical', 2));
        $this->assertSame('test_roman_excerpts_hybrid_v2', RomanExcerpt::indexNameFor('hybrid', 2));
        config(['kompendium.search.index_version' => 2]);
        $this->assertSame('test_roman_excerpts_lexical_v2', (new RomanExcerpt)->searchableAs());
    }

    public function test_invalid_versions_cannot_contact_typesense(): void
    {
        $this->mock(EngineManager::class)->shouldNotReceive('engine');
        foreach (['0', '-1', '1', '2.5', 'abc', '9999999999999999999999999'] as $version) {
            $this->artisan('kompendium:clone-index', ['version' => $version])->assertExitCode(1);
        }
    }

    public function test_verified_clone_does_not_activate_or_remove_either_collection(): void
    {
        $collections = $this->collections();
        $source = Mockery::mock(Collection::class);
        $target = Mockery::mock(Collection::class);
        $schema = ['num_documents' => 2, 'fields' => [['name' => 'title', 'type' => 'string', 'locale' => 'de']]];
        $collections->shouldReceive('offsetGet')->with('roman_excerpts')->twice()->andReturn($source);
        $source->shouldReceive('retrieve')->twice()->andReturn($schema);
        $collections->shouldReceive('create')->once()->with(['name' => 'roman_excerpts_lexical_v2'], ['src_name' => 'roman_excerpts', 'copy_documents' => 'true'])->andReturn([]);
        $collections->shouldReceive('offsetGet')->with('roman_excerpts_lexical_v2')->once()->andReturn($target);
        $target->shouldReceive('retrieve')->once()->andReturn($schema);
        $source->shouldNotReceive('delete');
        $target->shouldNotReceive('delete');
        $this->artisan('kompendium:clone-index', ['version' => '2'])->assertExitCode(0);
        $this->assertSame(1, config('kompendium.search.index_version'));
    }

    public function test_missing_source_and_existing_target_fail_without_overwriting(): void
    {
        $collections = $this->collections();
        $source = Mockery::mock(Collection::class);
        $collections->shouldReceive('offsetGet')->with('roman_excerpts')->twice()->andReturn($source);
        $source->shouldReceive('retrieve')->once()->andThrow(new ObjectNotFound('missing'));
        $source->shouldReceive('retrieve')->once()->andReturn(['num_documents' => 2, 'fields' => []]);
        $collections->shouldReceive('create')->once()->andThrow(new ObjectAlreadyExists('exists'));
        $this->artisan('kompendium:clone-index', ['version' => '2'])->assertExitCode(1);
        $this->artisan('kompendium:clone-index', ['version' => '2'])->assertExitCode(1);
    }

    public function test_document_count_mismatch_blocks_promotion(): void
    {
        $collections = $this->collections();
        $source = Mockery::mock(Collection::class);
        $target = Mockery::mock(Collection::class);
        $collections->shouldReceive('offsetGet')->with('roman_excerpts')->twice()->andReturn($source);
        $collections->shouldReceive('offsetGet')->with('roman_excerpts_lexical_v2')->once()->andReturn($target);
        $source->shouldReceive('retrieve')->twice()->andReturn(['num_documents' => 2, 'fields' => []]);
        $target->shouldReceive('retrieve')->once()->andReturn(['num_documents' => 1, 'fields' => []]);
        $collections->shouldReceive('create')->once()->andReturn([]);
        $this->artisan('kompendium:clone-index', ['version' => '2'])->assertExitCode(1);
        $this->assertSame(1, config('kompendium.search.index_version'));
    }

    private function collections(): Collections
    {
        config(['scout.driver' => 'typesense', 'scout.prefix' => '', 'kompendium.search.index_variant' => 'lexical', 'kompendium.search.index_version' => 1]);
        $collections = Mockery::mock(Collections::class);
        $engine = Mockery::mock(TypesenseEngine::class);
        $engine->shouldReceive('getCollections')->andReturn($collections);
        $this->mock(EngineManager::class)->shouldReceive('engine')->andReturn($engine);

        return $collections;
    }
}
