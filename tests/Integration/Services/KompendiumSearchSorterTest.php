<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\User;
use App\Services\KompendiumSearchSorter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KompendiumSearchSorterTestFixtures::class);

covers(KompendiumSearchSorter::class);

test('normalisiert sortierung richtung und post filter bedarf', function (): void {
    $sorter = new KompendiumSearchSorter;
    expect($sorter->normalizeSort('ungueltig'))->toBe('relevance')
        ->and($sorter->normalizeSort('first_published'))->toBe('first_published')
        ->and($sorter->defaultDirectionForSort('relevance'))->toBe('desc')
        ->and($sorter->defaultDirectionForSort('first_published'))->toBe('asc')
        ->and($sorter->normalizeDirection('seitwaerts', 'first_published'))->toBe('asc')
        ->and($sorter->normalizeDirection('desc', 'first_published'))->toBe('desc')
        ->and($sorter->needsFullPostFilter('relevance', 'desc'))->toBeFalse()
        ->and($sorter->needsFullPostFilter('relevance', 'asc'))->toBeTrue()
        ->and($sorter->needsFullPostFilter('first_published', 'asc'))->toBeTrue()
        ->and($sorter->needsFullPostFilter('first_published', 'desc'))->toBeTrue();
});

test('relevanz sortierung belaesst oder dreht tnt reihenfolge', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $sorter = new KompendiumSearchSorter;
    $paths = ['romane/maddrax/001 - A.txt', 'romane/maddrax/002 - B.txt', 'romane/maddrax/003 - C.txt'];
    foreach ($paths as $index => $path) {
        $this->createRoman($user, $path, 'maddrax', $index + 1, chr(65 + $index), '2020-01-0'.($index + 1));
    }
    $descending = $sorter->orderPathsWithMetadata($paths, 'relevance', 'desc');
    $ascending = $sorter->orderPathsWithMetadata($paths, 'relevance', 'asc');
    expect($descending['paths'])->toBe($paths)
        ->and($ascending['paths'])->toBe(array_reverse($paths))
        ->and($descending['metadata'][$paths[0]]['erstveroeffentlichtAm'])->toBe('2020-01-01')
        ->and($descending['metadata'][$paths[0]]['erstveroeffentlichtAmFormatted'])->toBe('01.01.2020');
});

test('erstveroeffentlichung sortiert beide richtungen mit fallbacks', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $sorter = new KompendiumSearchSorter;
    $missing = 'romane/maddrax/099 - Ohne Datum.txt';
    $maddraxOne = 'romane/maddrax/001 - Maddrax Eins.txt';
    $maddraxTen = 'romane/maddrax/010 - Maddrax Zehn.txt';
    $missionMars = 'romane/missionmars/002 - Mission Zwei.txt';
    $newest = 'romane/maddrax/200 - Neuer Treffer.txt';
    $paths = [$newest, $missionMars, $missing, $maddraxTen, $maddraxOne];
    $this->createRoman($user, $missing, 'maddrax', 99, 'Ohne Datum');
    $this->createRoman($user, $maddraxOne, 'maddrax', 1, 'Maddrax Eins', '2020-01-01');
    $this->createRoman($user, $maddraxTen, 'maddrax', 10, 'Maddrax Zehn', '2020-01-01');
    $this->createRoman($user, $missionMars, 'missionmars', 2, 'Mission Zwei', '2020-01-01');
    $this->createRoman($user, $newest, 'maddrax', 200, 'Neuer Treffer', '2024-01-01');
    $ascending = $sorter->orderPathsWithMetadata($paths, 'first_published', 'asc');
    $descending = $sorter->orderPathsWithMetadata($paths, 'first_published', 'desc');
    expect($ascending['paths'])->toBe([$missing, $maddraxOne, $maddraxTen, $missionMars, $newest])
        ->and($descending['paths'])->toBe([$newest, $maddraxOne, $maddraxTen, $missionMars, $missing])
        ->and($ascending['metadata'][$missing]['erstveroeffentlichtAm'])->toBeNull()
        ->and($ascending['metadata'][$missing]['romanNrSort'])->toBe(99);
});

test('metadata lookup chunks large path lists and selects needed columns', function (): void {
    $paths = array_map(fn (int $number): string => sprintf('romane/maddrax/%03d - Chunk %d.txt', $number, $number), range(1, 1001));
    DB::flushQueryLog();
    DB::enableQueryLog();
    $result = (new KompendiumSearchSorter)->orderPathsWithMetadata($paths, 'relevance', 'desc');
    DB::disableQueryLog();
    $metadataQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'kompendium_romane'))->values();
    expect($result['paths'])->toBe($paths)
        ->and($metadataQueries)->toHaveCount(3);
    foreach ($metadataQueries as $query) {
        expect(count($query['bindings']))->toBeLessThanOrEqual(500);
    }
    $firstQuery = strtolower($metadataQueries->first()['query']);
    expect($firstQuery)->not->toContain('select *')
        ->toContain('dateipfad')
        ->toContain('serie')
        ->toContain('roman_nr')
        ->toContain('erstveroeffentlicht_am');
});
