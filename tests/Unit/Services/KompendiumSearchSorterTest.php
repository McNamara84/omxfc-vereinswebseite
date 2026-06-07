<?php

namespace Tests\Unit\Services;

use App\Models\KompendiumRoman;
use App\Models\User;
use App\Services\KompendiumSearchSorter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(KompendiumSearchSorter::class)]
class KompendiumSearchSorterTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalisiert_sortierung_richtung_und_post_filter_bedarf(): void
    {
        $sorter = new KompendiumSearchSorter;

        $this->assertSame('relevance', $sorter->normalizeSort('ungueltig'));
        $this->assertSame('first_published', $sorter->normalizeSort('first_published'));

        $this->assertSame('desc', $sorter->defaultDirectionForSort('relevance'));
        $this->assertSame('asc', $sorter->defaultDirectionForSort('first_published'));
        $this->assertSame('asc', $sorter->normalizeDirection('seitwaerts', 'first_published'));
        $this->assertSame('desc', $sorter->normalizeDirection('desc', 'first_published'));

        $this->assertFalse($sorter->needsFullPostFilter('relevance', 'desc'));
        $this->assertTrue($sorter->needsFullPostFilter('relevance', 'asc'));
        $this->assertTrue($sorter->needsFullPostFilter('first_published', 'asc'));
        $this->assertTrue($sorter->needsFullPostFilter('first_published', 'desc'));
    }

    public function test_relevanz_sortierung_belaesst_oder_dreht_tnt_reihenfolge(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $sorter = new KompendiumSearchSorter;
        $paths = [
            'romane/maddrax/001 - A.txt',
            'romane/maddrax/002 - B.txt',
            'romane/maddrax/003 - C.txt',
        ];

        foreach ($paths as $index => $path) {
            $this->createRoman($user, $path, 'maddrax', $index + 1, chr(65 + $index), '2020-01-0'.($index + 1));
        }

        $descending = $sorter->orderPathsWithMetadata($paths, 'relevance', 'desc');
        $ascending = $sorter->orderPathsWithMetadata($paths, 'relevance', 'asc');

        $this->assertSame($paths, $descending['paths']);
        $this->assertSame(array_reverse($paths), $ascending['paths']);
        $this->assertSame('2020-01-01', $descending['metadata'][$paths[0]]['erstveroeffentlichtAm']);
        $this->assertSame('01.01.2020', $descending['metadata'][$paths[0]]['erstveroeffentlichtAmFormatted']);
    }

    public function test_erstveroeffentlichung_sortiert_beide_richtungen_mit_fallbacks(): void
    {
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

        $this->assertSame([$missing, $maddraxOne, $maddraxTen, $missionMars, $newest], $ascending['paths']);
        $this->assertSame([$newest, $maddraxOne, $maddraxTen, $missionMars, $missing], $descending['paths']);
        $this->assertNull($ascending['metadata'][$missing]['erstveroeffentlichtAm']);
        $this->assertSame(99, $ascending['metadata'][$missing]['romanNrSort']);
    }

    public function test_metadata_lookup_chunks_large_path_lists_and_selects_needed_columns(): void
    {
        $paths = array_map(
            fn (int $number): string => sprintf('romane/maddrax/%03d - Chunk %d.txt', $number, $number),
            range(1, 1001)
        );

        DB::flushQueryLog();
        DB::enableQueryLog();

        $result = (new KompendiumSearchSorter)->orderPathsWithMetadata($paths, 'relevance', 'desc');

        DB::disableQueryLog();

        $metadataQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'kompendium_romane'))
            ->values();

        $this->assertSame($paths, $result['paths']);
        $this->assertCount(3, $metadataQueries);

        foreach ($metadataQueries as $query) {
            $this->assertLessThanOrEqual(500, count($query['bindings']));
        }

        $firstQuery = strtolower($metadataQueries->first()['query']);
        $this->assertStringNotContainsString('select *', $firstQuery);
        $this->assertStringContainsString('dateipfad', $firstQuery);
        $this->assertStringContainsString('serie', $firstQuery);
        $this->assertStringContainsString('roman_nr', $firstQuery);
        $this->assertStringContainsString('erstveroeffentlicht_am', $firstQuery);
    }

    private function createRoman(
        User $user,
        string $path,
        string $serie,
        int $romanNr,
        string $titel,
        ?string $erstveroeffentlichtAm = null,
    ): KompendiumRoman {
        return KompendiumRoman::create([
            'dateiname' => basename($path),
            'dateipfad' => $path,
            'serie' => $serie,
            'roman_nr' => $romanNr,
            'titel' => $titel,
            'erstveroeffentlicht_am' => $erstveroeffentlichtAm,
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
            'indexiert_am' => now(),
        ]);
    }
}
