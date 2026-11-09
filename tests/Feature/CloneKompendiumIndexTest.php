<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RomanExcerpt;
use Laravel\Scout\EngineManager;
use Mockery;
use Tests\TestCase;
use Typesense\Collection;
use Typesense\Exceptions\ObjectAlreadyExists;
use Typesense\Exceptions\ObjectNotFound;

pest()->extend(TestCase::class)->use(CloneKompendiumIndexTestFixtures::class);

test('lexical versions preserve the original index and scout prefix', function (): void {
    config(['scout.prefix' => 'test_', 'kompendium.search.index_variant' => 'lexical', 'kompendium.search.index_version' => 1]);
    expect((new RomanExcerpt)->searchableAs())->toBe('test_roman_excerpts')
        ->and(RomanExcerpt::indexNameFor('lexical', 2))->toBe('test_roman_excerpts_lexical_v2')
        ->and(RomanExcerpt::indexNameFor('hybrid', 2))->toBe('test_roman_excerpts_hybrid_v2');
    config(['kompendium.search.index_version' => 2]);
    expect((new RomanExcerpt)->searchableAs())->toBe('test_roman_excerpts_lexical_v2');
});

test('invalid versions cannot contact typesense', function (): void {
    $this->mock(EngineManager::class)->shouldNotReceive('engine');
    foreach (['0', '-1', '1', '2.5', 'abc', '9999999999999999999999999'] as $version) {
        $this->artisan('kompendium:clone-index', ['version' => $version])->assertExitCode(1);
    }
});

test('verified clone does not activate or remove either collection', function (): void {
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
    expect(config('kompendium.search.index_version'))->toBe(1);
});

test('missing source and existing target fail without overwriting', function (): void {
    $collections = $this->collections();
    $source = Mockery::mock(Collection::class);
    $collections->shouldReceive('offsetGet')->with('roman_excerpts')->twice()->andReturn($source);
    $source->shouldReceive('retrieve')->once()->andThrow(new ObjectNotFound('missing'));
    $source->shouldReceive('retrieve')->once()->andReturn(['num_documents' => 2, 'fields' => []]);
    $collections->shouldReceive('create')->once()->andThrow(new ObjectAlreadyExists('exists'));
    $this->artisan('kompendium:clone-index', ['version' => '2'])->assertExitCode(1);
    $this->artisan('kompendium:clone-index', ['version' => '2'])->assertExitCode(1);
});

test('document count mismatch blocks promotion', function (): void {
    $collections = $this->collections();
    $source = Mockery::mock(Collection::class);
    $target = Mockery::mock(Collection::class);
    $collections->shouldReceive('offsetGet')->with('roman_excerpts')->twice()->andReturn($source);
    $collections->shouldReceive('offsetGet')->with('roman_excerpts_lexical_v2')->once()->andReturn($target);
    $source->shouldReceive('retrieve')->twice()->andReturn(['num_documents' => 2, 'fields' => []]);
    $target->shouldReceive('retrieve')->once()->andReturn(['num_documents' => 1, 'fields' => []]);
    $collections->shouldReceive('create')->once()->andReturn([]);
    $this->artisan('kompendium:clone-index', ['version' => '2'])->assertExitCode(1);
    expect(config('kompendium.search.index_version'))->toBe(1);
});

test('failed snapshot blocks promotion and preserves the active index', function (): void {
    $collections = $this->collections(snapshotSucceeds: false);
    $source = Mockery::mock(Collection::class);
    $target = Mockery::mock(Collection::class);
    $schema = ['num_documents' => 2, 'fields' => []];
    $collections->shouldReceive('offsetGet')->with('roman_excerpts')->twice()->andReturn($source);
    $collections->shouldReceive('offsetGet')->with('roman_excerpts_lexical_v2')->once()->andReturn($target);
    $source->shouldReceive('retrieve')->twice()->andReturn($schema);
    $target->shouldReceive('retrieve')->once()->andReturn($schema);
    $collections->shouldReceive('create')->once()->andReturn([]);
    $source->shouldNotReceive('delete');
    $target->shouldNotReceive('delete');
    $this->artisan('kompendium:clone-index', ['version' => '2'])->assertExitCode(1);
    expect(config('kompendium.search.index_version'))->toBe(1);
});
