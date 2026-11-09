<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\KompendiumSearchService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KompendiumSearchTestFixtures::class);

afterEach(function (): void {
    Mockery::close();
});

test('search requires purchased reward', function (): void {
    $user = $this->actingMemberWithPoints(50);
    $this->getJson('/kompendium/suche?q=test')->assertStatus(403)->assertJson(['message' => 'Zugang erfordert den Kauf des Kompendium-Rewards oder AG-Maddraxikon-Mitgliedschaft.']);
});

test('search validates query length', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->getJson('/kompendium/suche?q=a')->assertStatus(422);
});

test('search returns formatted results', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    Storage::disk('private')->put('romane/maddrax/001 - ExampleTitle.txt', 'Some example content with query word');
    // Mock den KompendiumSearchService (partialMock: parseSearchQuery/buildTntSearchQuery bleiben real)
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->with('example')->once()->andReturn(['hits' => ['total_hits' => 1], 'ids' => ['romane/maddrax/001 - ExampleTitle.txt']]);
    });
    $response = $this->getJson('/kompendium/suche?q=example');
    $response->assertOk()->assertJson(['currentPage' => 1, 'lastPage' => 1, 'data' => [['cycle' => 'Maddrax - Die dunkle Zukunft der Erde', 'romanNr' => '001', 'title' => 'ExampleTitle', 'serie' => 'maddrax']], 'serienCounts' => ['maddrax' => 1]]);
});

test('search returns empty when no matches found', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    // Mock den KompendiumSearchService (partialMock: parseSearchQuery/buildTntSearchQuery bleiben real)
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->with('nomatch')->once()->andReturn(['hits' => ['total_hits' => 0], 'ids' => []]);
    });
    $response = $this->getJson('/kompendium/suche?q=nomatch');
    $response->assertOk()->assertJson(['currentPage' => 1, 'lastPage' => 1, 'data' => [], 'serienCounts' => []]);
});

test('search filters by serien parameter', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    Storage::disk('private')->put('romane/maddrax/001 - MaddraxRoman.txt', 'Content maddrax');
    Storage::disk('private')->put('romane/missionmars/001 - MarsRoman.txt', 'Content mars');
    // Mock: Suche findet beide Romane (partialMock: parseSearchQuery/buildTntSearchQuery bleiben real)
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->with('content')->once()->andReturn(['hits' => ['total_hits' => 2], 'ids' => ['romane/maddrax/001 - MaddraxRoman.txt', 'romane/missionmars/001 - MarsRoman.txt']]);
    });
    // Nur Maddrax-Serie anfordern
    $response = $this->getJson('/kompendium/suche?q=content&serien[]=maddrax');
    $response->assertOk();
    $data = $response->json('data');
    // Nur 1 Treffer (Maddrax), da missionmars gefiltert wurde
    expect($data)->toHaveCount(1);
    expect($data[0]['serie'])->toEqual('maddrax');
    // serienCounts zeigt aber beide (Gesamtübersicht)
    $response->assertJson(['serienCounts' => ['maddrax' => 1, 'missionmars' => 1]]);
});

test('search returns all serien when no filter', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    Storage::disk('private')->put('romane/maddrax/001 - MaddraxRoman.txt', 'Content maddrax');
    Storage::disk('private')->put('romane/missionmars/001 - MarsRoman.txt', 'Content mars');
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->with('content')->once()->andReturn(['hits' => ['total_hits' => 2], 'ids' => ['romane/maddrax/001 - MaddraxRoman.txt', 'romane/missionmars/001 - MarsRoman.txt']]);
    });
    // Keine serien[] Parameter = alle Serien
    $response = $this->getJson('/kompendium/suche?q=content');
    $response->assertOk();
    $data = $response->json('data');
    // Beide Treffer
    expect($data)->toHaveCount(2);
});

test('search validates invalid serien parameter', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    // Ungültige Serie "invalid-serie" sollte Validierungsfehler auslösen
    $response = $this->getJson('/kompendium/suche?q=test&serien[]=invalid-serie');
    $response->assertStatus(422)->assertJsonValidationErrors(['serien.0']);
});

test('search accepts valid serien parameter', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->with('test')->once()->andReturn(['hits' => ['total_hits' => 0], 'ids' => []]);
    });
    // Gültige Serie "maddrax" sollte akzeptiert werden
    $response = $this->getJson('/kompendium/suche?q=test&serien[]=maddrax');
    $response->assertOk();
});

test('search validates invalid sort parameter', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->getJson('/kompendium/suche?q=test&sort=ungueltig')->assertStatus(422)->assertJsonValidationErrors(['sort']);
    $this->getJson('/kompendium/suche?q=test&direction=seitwaerts')->assertStatus(422)->assertJsonValidationErrors(['direction']);
});

test('search ignores path traversal attempts', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    // Legitime Datei erstellen
    Storage::disk('private')->put('romane/maddrax/001 - ValidRoman.txt', 'Valid content');
    // Mock: Suche gibt sowohl legitime als auch bösartige Pfade zurück (partialMock)
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->with('content')->once()->andReturn(['hits' => ['total_hits' => 3], 'ids' => [
            'romane/maddrax/001 - ValidRoman.txt',
            // Gültig
            '../.env',
            // Path-Traversal
            'romane/../../../.env',
        ]]);
    });
    $response = $this->getJson('/kompendium/suche?q=content');
    $response->assertOk();
    $data = $response->json('data');
    // Nur der gültige Roman sollte zurückgegeben werden
    expect($data)->toHaveCount(1);
    expect($data[0]['romanNr'])->toEqual('001');
});

test('search paginates results correctly', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    // 7 Romane erstellen (5 pro Seite = 2 Seiten)
    $ids = [];
    for ($i = 1; $i <= 7; $i++) {
        $nr = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $path = "romane/maddrax/{$nr} - Roman{$i}.txt";
        Storage::disk('private')->put($path, "Content roman{$i} with searchterm");
        $ids[] = $path;
    }
    $this->partialMock(KompendiumSearchService::class, function ($mock) use ($ids) {
        $mock->shouldReceive('search')->with('searchterm')->twice()->andReturn(['hits' => ['total_hits' => 7], 'ids' => $ids]);
    });
    // Seite 1: 5 Ergebnisse
    $response = $this->getJson('/kompendium/suche?q=searchterm&page=1');
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(5)
        ->and($response->json('currentPage'))->toEqual(1)
        ->and($response->json('lastPage'))->toEqual(2);
    // Seite 2: 2 Ergebnisse
    $response = $this->getJson('/kompendium/suche?q=searchterm&page=2');
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('currentPage'))->toEqual(2);
});

test('search can reverse relevance order', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    $ids = ['romane/maddrax/001 - RelevantA.txt', 'romane/maddrax/002 - RelevantB.txt', 'romane/maddrax/003 - RelevantC.txt'];
    $this->createIndexedSearchRoman($user, $ids[0], 'Content reverseorder', 'maddrax', 1, 'RelevantA');
    $this->createIndexedSearchRoman($user, $ids[1], 'Content reverseorder', 'maddrax', 2, 'RelevantB');
    $this->createIndexedSearchRoman($user, $ids[2], 'Content reverseorder', 'maddrax', 3, 'RelevantC');
    $this->partialMock(KompendiumSearchService::class, function ($mock) use ($ids) {
        $mock->shouldReceive('search')->with('reverseorder')->once()->andReturn(['hits' => ['total_hits' => 3], 'ids' => $ids]);
    });
    $response = $this->getJson('/kompendium/suche?q=reverseorder&sort=relevance&direction=asc');
    $response->assertOk();
    expect(array_column($response->json('data'), 'title'))->toBe(['RelevantC', 'RelevantB', 'RelevantA'])
        ->and($response->json('sort'))->toBe('relevance')
        ->and($response->json('direction'))->toBe('asc');
});

test('search sorts by publication date in both directions with missing dates as oldest', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    $ids = ['romane/maddrax/003 - Neuer Treffer.txt', 'romane/maddrax/001 - Ohne Datum.txt', 'romane/maddrax/002 - Alter Treffer.txt'];
    $this->createIndexedSearchRoman($user, $ids[0], 'Content datumsortierung', 'maddrax', 3, 'Neuer Treffer', '2024-01-01');
    $this->createIndexedSearchRoman($user, $ids[1], 'Content datumsortierung', 'maddrax', 1, 'Ohne Datum');
    $this->createIndexedSearchRoman($user, $ids[2], 'Content datumsortierung', 'maddrax', 2, 'Alter Treffer', '2020-01-01');
    $this->partialMock(KompendiumSearchService::class, function ($mock) use ($ids) {
        $mock->shouldReceive('search')->with('datumsortierung')->twice()->andReturn(['hits' => ['total_hits' => 3], 'ids' => $ids]);
    });
    $ascending = $this->getJson('/kompendium/suche?q=datumsortierung&sort=first_published');
    $ascending->assertOk();
    expect(array_column($ascending->json('data'), 'title'))->toBe(['Ohne Datum', 'Alter Treffer', 'Neuer Treffer'])
        ->and($ascending->json('sort'))->toBe('first_published')
        ->and($ascending->json('direction'))->toBe('asc');
    $descending = $this->getJson('/kompendium/suche?q=datumsortierung&sort=first_published&direction=desc');
    $descending->assertOk();
    expect(array_column($descending->json('data'), 'title'))->toBe(['Neuer Treffer', 'Alter Treffer', 'Ohne Datum'])
        ->and($descending->json('direction'))->toBe('desc');
});

test('search keeps series filter counts when sorting by publication date', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    $ids = ['romane/missionmars/002 - Mars Neuer.txt', 'romane/maddrax/002 - Maddrax Neuer.txt', 'romane/missionmars/001 - Mars Alter.txt', 'romane/maddrax/001 - Maddrax Alter.txt'];
    $this->createIndexedSearchRoman($user, $ids[0], 'Content serienfilter', 'missionmars', 2, 'Mars Neuer', '2024-01-01');
    $this->createIndexedSearchRoman($user, $ids[1], 'Content serienfilter', 'maddrax', 2, 'Maddrax Neuer', '2023-01-01');
    $this->createIndexedSearchRoman($user, $ids[2], 'Content serienfilter', 'missionmars', 1, 'Mars Alter', '2020-01-01');
    $this->createIndexedSearchRoman($user, $ids[3], 'Content serienfilter', 'maddrax', 1, 'Maddrax Alter', '2019-01-01');
    $this->partialMock(KompendiumSearchService::class, function ($mock) use ($ids) {
        $mock->shouldReceive('search')->with('serienfilter')->once()->andReturn(['hits' => ['total_hits' => 4], 'ids' => $ids]);
    });
    $response = $this->getJson('/kompendium/suche?q=serienfilter&sort=first_published&direction=asc&serien[]=maddrax');
    $response->assertOk();
    expect(array_column($response->json('data'), 'title'))->toBe(['Maddrax Alter', 'Maddrax Neuer'])
        ->and($response->json('serienCounts.maddrax'))->toBe(2)
        ->and($response->json('serienCounts.missionmars'))->toBe(2);
});

test('search generates snippets with mark highlighting', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    Storage::disk('private')->put('romane/maddrax/001 - HighlightTest.txt', 'Vor dem Suchbegriff steht Text und hier kommt der Maddrax und danach mehr Text.');
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->with('maddrax')->once()->andReturn(['hits' => ['total_hits' => 1], 'ids' => ['romane/maddrax/001 - HighlightTest.txt']]);
    });
    $response = $this->getJson('/kompendium/suche?q=maddrax');
    $response->assertOk();
    $data = $response->json('data');
    expect($data[0]['snippets'])->not->toBeEmpty();
    // Snippet enthält <mark>-Tags um den Suchbegriff
    $snippet = $data[0]['snippets'][0];
    expect($snippet)->toContain('<mark>')
        ->toContain('</mark>');
});

test('search skips files that no longer exist', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    // Nur eine von zwei Dateien existiert
    Storage::disk('private')->put('romane/maddrax/001 - Exists.txt', 'Existing content test');
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->with('content')->once()->andReturn(['hits' => ['total_hits' => 2], 'ids' => ['romane/maddrax/001 - Exists.txt', 'romane/maddrax/002 - Deleted.txt']]);
    });
    $response = $this->getJson('/kompendium/suche?q=content');
    $response->assertOk();
    $data = $response->json('data');
    // Nur die existierende Datei wird zurückgegeben
    expect($data)->toHaveCount(1);
    expect($data[0]['romanNr'])->toEqual('001')
        ->and($data[0]['title'])->toEqual('Exists');
});

test('search handles multiple serien filters', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    Storage::disk('private')->put('romane/maddrax/001 - MaddraxRoman.txt', 'Content testterm maddrax');
    Storage::disk('private')->put('romane/missionmars/001 - MarsRoman.txt', 'Content testterm mars');
    Storage::disk('private')->put('romane/hardcovers/001 - HCRoman.txt', 'Content testterm hardcover');
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->with('testterm')->once()->andReturn(['hits' => ['total_hits' => 3], 'ids' => ['romane/maddrax/001 - MaddraxRoman.txt', 'romane/missionmars/001 - MarsRoman.txt', 'romane/hardcovers/001 - HCRoman.txt']]);
    });
    // Zwei von drei Serien filtern
    $response = $this->getJson('/kompendium/suche?q=testterm&serien[]=maddrax&serien[]=missionmars');
    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    $serien = array_column($data, 'serie');
    expect($serien)->toContain('maddrax')
        ->toContain('missionmars')->not->toContain('hardcovers');
    // serienCounts zeigt alle 3 (unabhängig vom Filter)
    expect(count($response->json('serienCounts')))->toEqual(3);
});

test('search handles filename without separator', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    Storage::disk('private')->put('romane/maddrax/BadFormat.txt', 'Some badformat content');
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->with('badformat')->once()->andReturn(['hits' => ['total_hits' => 1], 'ids' => ['romane/maddrax/BadFormat.txt']]);
    });
    $response = $this->getJson('/kompendium/suche?q=badformat');
    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    // Fallback bei ungültigem Format
    expect($data[0]['romanNr'])->toEqual('???');
    expect($data[0]['title'])->toEqual('BadFormat');
});

test('search response contains all required fields', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    Storage::disk('private')->put('romane/maddrax/001 - TestRoman.txt', 'Content structtest');
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->with('structtest')->once()->andReturn(['hits' => ['total_hits' => 1], 'ids' => ['romane/maddrax/001 - TestRoman.txt']]);
    });
    $response = $this->getJson('/kompendium/suche?q=structtest');
    $response->assertOk()->assertJsonStructure(['data' => ['*' => ['cycle', 'romanNr', 'title', 'serie', 'snippets']], 'currentPage', 'lastPage', 'serienCounts', 'isPhraseSearch', 'searchInfo']);
});

test('search returns empty data beyond last page', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Storage::fake('private');
    Storage::disk('private')->put('romane/maddrax/001 - OnlyOne.txt', 'Content pagetest');
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->with('pagetest')->once()->andReturn(['hits' => ['total_hits' => 1], 'ids' => ['romane/maddrax/001 - OnlyOne.txt']]);
    });
    // Seite 999 bei nur 1 Ergebnis
    $response = $this->getJson('/kompendium/suche?q=pagetest&page=999');
    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

test('search requires authentication', function (): void {
    $this->getJson('/kompendium/suche?q=test')->assertUnauthorized();
});

test('serien requires authentication', function (): void {
    $this->getJson('/kompendium/serien')->assertUnauthorized();
});

test('index requires authentication', function (): void {
    $this->get('/kompendium')->assertRedirect('/login');
});
