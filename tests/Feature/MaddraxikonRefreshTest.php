<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Models\Book;
use App\Services\MaddraxDataService;
use App\Services\Maddraxikon\MaddraxikonBookImporter;
use App\Services\Maddraxikon\MaddraxikonCandidateStore;
use App\Services\Maddraxikon\MaddraxikonRefreshCoordinator;
use App\Services\Maddraxikon\MaddraxikonSnapshotRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonRefreshTestFixtures::class);

beforeEach(function (): void {
    $this->testStoragePath = base_path('storage/testing-maddrax-refresh');
    $this->app->useStoragePath($this->testStoragePath);
    File::ensureDirectoryExists($this->testStoragePath.'/app/private');
    File::ensureDirectoryExists($this->testStoragePath.'/framework/views');
    config(['filesystems.disks.private.root' => $this->testStoragePath.'/app/private', 'maddraxikon.http.attempts' => 3, 'maddraxikon.http.retry_delay_ms' => 0, 'maddraxikon.http.retry_max_delay_ms' => 0]);
});

afterEach(function (): void {
    File::deleteDirectory($this->testStoragePath);
    Mockery::close();
});

test('explicit empty promote option is rejected without starting a crawl', function (): void {
    Http::fake();
    $this->artisan('books:refresh', ['--promote' => ''])->expectsOutputToContain('--promote benötigt eine nicht leere Kandidaten-ID.')->assertExitCode(Command::INVALID);
    Http::assertNothingSent();
    expect(File::glob(Storage::disk('private')->path('maddrax-candidates/*')))->toBe([]);
});

test('dry run creates immutable candidate without changing active state', function (): void {
    $category = 'https://de.maddraxikon.com/index.php?title=Kategorie:Maddrax-Heftromane';
    Http::fake([$category => Http::response('<div id="mw-pages"><a href="/wiki/MX_1">MX 1</a></div>'), 'https://de.maddraxikon.com/wiki/MX_1' => Http::response($this->articleHtml(1, 'Euree'))]);
    Cache::put('maddrax_series_maddrax', ['old']);
    $this->artisan('books:refresh', ['--series' => 'maddrax', '--dry-run' => true])->expectsOutputToContain('Dry-Run erfolgreich')->assertSuccessful();
    expect(Storage::disk('private')->exists('maddrax.json'))->toBeFalse()
        ->and(Cache::get('maddrax_series_maddrax'))->toBe(['old'])
        ->and(Book::query()->where('type', BookType::MaddraxDieDunkleZukunftDerErde)->count())->toBe(0)
        ->and(app(MaddraxikonSnapshotRepository::class)->activeId(BookType::MaddraxDieDunkleZukunftDerErde))->toBeNull();
    $manifests = File::glob(Storage::disk('private')->path('maddrax-candidates/*/manifest.json'));
    expect($manifests)->toHaveCount(1);
    $manifest = json_decode(File::get($manifests[0]), true);
    expect($manifest['series']['maddrax']['count'])->toBe(1)
        ->and($manifest['series']['maddrax']['sha256'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($manifest['series']['maddrax']['baseline'])->toBe(['source' => 'none', 'fingerprint' => null]);
});

test('numeric 2012 series key survives manifest load and promotion', function (): void {
    $category = 'https://de.maddraxikon.com/index.php?title=Kategorie:2012-Heftromane';
    $article = 'https://de.maddraxikon.com/wiki/2012_1';
    Http::fake([$category => Http::response('<div id="mw-pages"><a href="/wiki/2012_1">2012 1</a></div>'), $article => Http::response('<html><body>
                <table><tr><td><b>2012</b></td></tr></table>
                <div class="heftartikel-navigationsleiste-anfang">
                    <table><tr><td align="center"><i><b>1</b></i></td></tr></table>
                </div>
                <table>
                    <tr><td>Erstmals&nbsp;erschienen:</td><td>2026-09-01</td></tr>
                    <tr><td>Titel:</td><th>Roman 1</th></tr>
                    <tr><td>Text:</td><td>Autor</td></tr>
                </table>
            </body></html>')]);
    $this->artisan('books:refresh', ['--series' => '2012', '--dry-run' => true])->assertSuccessful();
    $manifests = File::glob(Storage::disk('private')->path('maddrax-candidates/*/manifest.json'));
    expect($manifests)->toHaveCount(1);
    $manifest = json_decode(File::get($manifests[0]), true, flags: JSON_THROW_ON_ERROR);
    expect(array_key_first($manifest['series']))->toBe(2012);
    $this->artisan('books:refresh', ['--promote' => $manifest['id']])->expectsOutputToContain('für 2012 sicher aktualisiert')->assertSuccessful();
    $type = BookType::ZweiTausendZwölfDasJahrDerApokalypse;
    expect(app(MaddraxikonSnapshotRepository::class)->activeId($type))->toBe($manifest['id']);
    $this->assertDatabaseHas('books', ['roman_number' => 1, 'type' => $type->value, 'title' => 'Roman 1']);
});

test('refresh uses the configured origin for category and page title', function (): void {
    $origin = 'https://wiki.example.test:8443';
    $category = $origin.'/index.php?title=Kategorie:Maddrax-Heftromane';
    $article = $origin.'/wiki/MX_1';
    config(['maddraxikon.base_url' => $origin]);
    Http::fake([$category => Http::response('<div id="mw-pages"><a href="/wiki/MX_1">MX 1</a></div>'), $article => Http::response($this->articleHtml(1, 'Euree'))]);
    $this->artisan('books:refresh', ['--series' => 'maddrax', '--dry-run' => true])->assertSuccessful();
    $candidateFiles = File::glob(Storage::disk('private')->path('maddrax-candidates/*/maddrax.json'));
    expect($candidateFiles)->toHaveCount(1);
    $rows = json_decode(File::get($candidateFiles[0]), true, flags: JSON_THROW_ON_ERROR);
    expect($rows[0]['maddraxikon_seitentitel'])->toBe('MX 1');
    Http::assertSentCount(2);
});

test('promotion updates database snapshot books and cache together', function (): void {
    $old = [$this->row(1, 'Alter Zyklus', 'Alter Titel')];
    $new = [$this->row(1, 'Euree', 'Neuer Titel')];
    Storage::disk('private')->put('maddrax.json', json_encode($old));
    Book::create(['roman_number' => 1, 'title' => 'Alter Titel', 'author' => 'Alt', 'cycle' => 'Alter Zyklus']);
    Cache::put('maddrax_series_maddrax', $old);
    $dataService = app(MaddraxDataService::class);
    expect($dataService->getMaddraxRomane()->first()['titel'])->toBe('Alter Titel');
    $candidate = app(MaddraxikonCandidateStore::class)->stage(['maddrax' => $new]);
    $coordinator = new MaddraxikonRefreshCoordinator(app(MaddraxikonCandidateStore::class), app(MaddraxikonBookImporter::class), $dataService, app(MaddraxikonSnapshotRepository::class));
    $counts = $coordinator->promote($candidate['id']);
    expect($counts)->toBe(['maddrax' => 1]);
    $this->assertDatabaseHas('books', ['roman_number' => 1, 'title' => 'Neuer Titel', 'cycle' => 'Euree', 'type' => BookType::MaddraxDieDunkleZukunftDerErde->value]);
    $snapshot = app(MaddraxikonSnapshotRepository::class)->activeDataset(BookType::MaddraxDieDunkleZukunftDerErde);
    expect($snapshot)->not->toBeNull()
        ->and($snapshot['id'])->toBe($candidate['id'])
        ->and($snapshot['rows'])->toBe($new)
        ->and(json_decode(Storage::disk('private')->get('maddrax.json'), true))->toBe($old)
        ->and(Storage::disk('private')->exists('maddrax-candidates/'.$candidate['id']))->toBeFalse()
        ->and(Cache::get('maddrax_series_maddrax'))->toBeNull()
        ->and($dataService->getMaddraxRomane()->first()['titel'])->toBe('Neuer Titel')
        ->and(Cache::get('maddrax_series_maddrax')['snapshot_id'])->toBe($candidate['id']);
});

test('whole number float rating survives the candidate round trip', function (): void {
    $row = $this->row(1, 'Euree', 'Titel');
    $row['bewertung'] = 4.0;
    $store = app(MaddraxikonCandidateStore::class);
    $candidate = $store->stage(['maddrax' => [$row]]);
    $loaded = $store->load($candidate['id']);
    expect($loaded['datasets']['maddrax'][0]['bewertung'])->toBe(4.0)
        ->and(Storage::disk('private')->get('maddrax-candidates/'.$candidate['id'].'/maddrax.json'))->toContain('"bewertung": 4.0');
});

test('cache invalidation failure does not turn a committed promotion into a failure', function (): void {
    $store = app(MaddraxikonCandidateStore::class);
    $candidate = $store->stage(['maddrax' => [$this->row(1, 'Euree', 'Neuer Titel')]]);
    $dataService = Mockery::mock(MaddraxDataService::class);
    $dataService->shouldReceive('clearCache')->once()->andThrow(new \RuntimeException('Cache nicht erreichbar'));
    $coordinator = new MaddraxikonRefreshCoordinator($store, app(MaddraxikonBookImporter::class), $dataService, app(MaddraxikonSnapshotRepository::class));
    expect($coordinator->promote($candidate['id']))->toBe(['maddrax' => 1])
        ->and(app(MaddraxikonSnapshotRepository::class)->activeId(BookType::MaddraxDieDunkleZukunftDerErde))->toBe($candidate['id']);
    $this->assertDatabaseHas('books', ['roman_number' => 1, 'title' => 'Neuer Titel', 'cycle' => 'Euree']);
    expect(Storage::disk('private')->exists('maddrax-candidates/'.$candidate['id']))->toBeFalse();
});

test('promotion retry cleans up an already active candidate without reapplying it', function (): void {
    $old = [$this->row(1, 'Alter Zyklus', 'Alter Titel')];
    $new = [$this->row(1, 'Euree', 'Neuer Titel')];
    $oldSnapshotId = '33333333-3333-4333-8333-333333333333';
    $this->activateSnapshot($oldSnapshotId, ['maddrax' => $old]);
    Book::create(['roman_number' => 1, 'title' => 'Alter Titel', 'author' => 'Alt', 'cycle' => 'Alter Zyklus']);
    $store = app(MaddraxikonCandidateStore::class);
    $candidate = $store->stage(['maddrax' => $new]);
    $loaded = $store->load($candidate['id']);
    expect($loaded['already_active'])->toBeFalse();
    DB::transaction(function () use ($candidate, $loaded): void {
        app(MaddraxikonSnapshotRepository::class)->storeAndActivate($candidate['id'], $loaded['json']);
        app(MaddraxikonBookImporter::class)->import($loaded['datasets'], false);
    });
    expect(Storage::disk('private')->exists('maddrax-candidates/'.$candidate['id']))->toBeTrue()
        ->and($store->load($candidate['id'])['already_active'])->toBeTrue();
    $importer = Mockery::mock(MaddraxikonBookImporter::class);
    $importer->shouldNotReceive('import');
    $coordinator = new MaddraxikonRefreshCoordinator($store, $importer, app(MaddraxDataService::class), app(MaddraxikonSnapshotRepository::class));
    expect($coordinator->promote($candidate['id']))->toBe(['maddrax' => 1])
        ->and(app(MaddraxikonSnapshotRepository::class)->activeId(BookType::MaddraxDieDunkleZukunftDerErde))->toBe($candidate['id']);
    $this->assertDatabaseHas('books', ['roman_number' => 1, 'title' => 'Neuer Titel', 'cycle' => 'Euree']);
    expect(Storage::disk('private')->exists('maddrax-candidates/'.$candidate['id']))->toBeFalse();
});

test('promotion retry rejects an active id with different snapshot data', function (): void {
    $store = app(MaddraxikonCandidateStore::class);
    $candidate = $store->stage(['maddrax' => [$this->row(1, 'Euree', 'Kandidat')]]);
    $this->activateSnapshot($candidate['id'], ['maddrax' => [$this->row(1, 'Euree', 'Abweichender Snapshot')]]);
    try {
        app(MaddraxikonRefreshCoordinator::class)->promote($candidate['id']);
        $this->fail('Expected an active snapshot payload mismatch.');
    } catch (MaddraxikonCrawlException $exception) {
        expect($exception->getMessage())->toContain('stimmt nicht mit Kandidat');
    }
    expect(Storage::disk('private')->exists('maddrax-candidates/'.$candidate['id']))->toBeTrue();
});

test('import failure rolls back snapshot pointer books and cache', function (): void {
    $old = [$this->row(1, 'Alter Zyklus', 'Alter Titel')];
    $new = [$this->row(1, 'Euree', 'Neuer Titel')];
    Storage::disk('private')->put('maddrax.json', json_encode($old));
    $this->activateSnapshot('11111111-1111-4111-8111-111111111111', ['maddrax' => $old]);
    $book = Book::create(['roman_number' => 1, 'title' => 'Alter Titel', 'author' => 'Alt', 'cycle' => 'Alter Zyklus']);
    Cache::put('maddrax_series_maddrax', $old);
    $candidate = app(MaddraxikonCandidateStore::class)->stage(['maddrax' => $new]);
    $importer = Mockery::mock(MaddraxikonBookImporter::class);
    $importer->shouldReceive('import')->once()->andReturnUsing(function (): void {
        Book::query()->update(['title' => 'Nicht committen']);
        throw new \RuntimeException('Import kaputt');
    });
    $coordinator = new MaddraxikonRefreshCoordinator(app(MaddraxikonCandidateStore::class), $importer, app(MaddraxDataService::class), app(MaddraxikonSnapshotRepository::class));
    try {
        $coordinator->promote($candidate['id']);
        $this->fail('Expected import failure.');
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Import kaputt');
    }
    expect(json_decode(Storage::disk('private')->get('maddrax.json'), true))->toBe($old)
        ->and($book->fresh()->title)->toBe('Alter Titel')
        ->and(app(MaddraxikonSnapshotRepository::class)->activeId(BookType::MaddraxDieDunkleZukunftDerErde))->toBe('11111111-1111-4111-8111-111111111111')
        ->and(Storage::disk('private')->exists('maddrax-candidates/'.$candidate['id']))->toBeTrue()
        ->and(Cache::get('maddrax_series_maddrax'))->toBe($old);
});

test('failure after pointer update rolls back to previous snapshot', function (): void {
    $old = [$this->row(1, 'Alter Zyklus', 'Alter Titel')];
    $new = [$this->row(1, 'Euree', 'Neuer Titel')];
    $oldJson = json_encode($old);
    Storage::disk('private')->put('maddrax.json', $oldJson);
    $oldSnapshotId = '22222222-2222-4222-8222-222222222222';
    $this->activateSnapshot($oldSnapshotId, ['maddrax' => $old]);
    $book = Book::create(['roman_number' => 1, 'title' => 'Alter Titel', 'author' => 'Alt', 'cycle' => 'Alter Zyklus']);
    Cache::put('maddrax_series_maddrax', $old);
    $candidate = app(MaddraxikonCandidateStore::class)->stage(['maddrax' => $new]);
    $realSnapshots = app(MaddraxikonSnapshotRepository::class);
    $snapshots = Mockery::mock(MaddraxikonSnapshotRepository::class);
    $snapshots->shouldReceive('storeAndActivate')->once()->andReturnUsing(function (string $id, array $json) use ($realSnapshots): void {
        $realSnapshots->storeAndActivate($id, $json);
        throw new MaddraxikonCrawlException('Aktivierung kaputt');
    });
    $coordinator = new MaddraxikonRefreshCoordinator(app(MaddraxikonCandidateStore::class), app(MaddraxikonBookImporter::class), app(MaddraxDataService::class), $snapshots);
    try {
        $coordinator->promote($candidate['id']);
        $this->fail('Expected snapshot activation failure.');
    } catch (MaddraxikonCrawlException $exception) {
        expect($exception->getMessage())->toBe('Aktivierung kaputt');
    }
    expect(Storage::disk('private')->get('maddrax.json'))->toBe($oldJson)
        ->and($book->fresh()->title)->toBe('Alter Titel')
        ->and($book->fresh()->cycle)->toBe('Alter Zyklus')
        ->and($realSnapshots->activeId(BookType::MaddraxDieDunkleZukunftDerErde))->toBe($oldSnapshotId);
    $this->assertDatabaseMissing('maddraxikon_book_snapshots', ['snapshot_id' => $candidate['id']]);
    expect(Cache::get('maddrax_series_maddrax'))->toBe($old);
});

test('staging prunes expired candidates but keeps current dry runs', function (): void {
    $store = app(MaddraxikonCandidateStore::class);
    $expired = $store->stage(['maddrax' => [$this->row(1, 'Euree', 'Alt')]]);
    $manifestPath = 'maddrax-candidates/'.$expired['id'].'/manifest.json';
    $manifest = json_decode(Storage::disk('private')->get($manifestPath), true);
    $manifest['expires_at'] = now()->subMinute()->toIso8601String();
    Storage::disk('private')->put($manifestPath, json_encode($manifest));
    $current = $store->stage(['maddrax' => [$this->row(1, 'Euree', 'Neu')]]);
    expect(Storage::disk('private')->exists('maddrax-candidates/'.$expired['id']))->toBeFalse()
        ->and(Storage::disk('private')->exists('maddrax-candidates/'.$current['id']))->toBeTrue()
        ->and($store->pruneExpired())->toBe(0);
});

test('stale candidate is rejected after a newer snapshot was promoted', function (): void {
    $initial = [$this->row(1, 'Euree', 'Ausgangsstand')];
    $this->activateSnapshot('99999999-9999-4999-8999-999999999999', ['maddrax' => $initial]);
    Book::create(['roman_number' => 1, 'title' => 'Ausgangsstand', 'author' => 'Autor', 'cycle' => 'Euree']);
    $store = app(MaddraxikonCandidateStore::class);
    $stale = $store->stage(['maddrax' => [$this->row(1, 'Weltrat', 'Veraltet')]]);
    $fresh = $store->stage(['maddrax' => [$this->row(1, 'Weltrat', 'Aktuell')]]);
    expect($stale['manifest']['series']['maddrax']['baseline'])->toBe(['source' => 'database_snapshot', 'fingerprint' => '99999999-9999-4999-8999-999999999999']);
    app(MaddraxikonRefreshCoordinator::class)->promote($fresh['id']);
    try {
        app(MaddraxikonRefreshCoordinator::class)->promote($stale['id']);
        $this->fail('Expected stale baseline rejection.');
    } catch (MaddraxikonCrawlException $exception) {
        expect($exception->getMessage())->toContain('seit der Kandidatenerstellung geändert');
    }
    expect(app(MaddraxikonSnapshotRepository::class)->activeId(BookType::MaddraxDieDunkleZukunftDerErde))->toBe($fresh['id']);
    $this->assertDatabaseHas('books', ['roman_number' => 1, 'title' => 'Aktuell', 'cycle' => 'Weltrat']);
    expect(Storage::disk('private')->exists('maddrax-candidates/'.$stale['id']))->toBeTrue();
});

test('candidate is rejected when its legacy file baseline changed', function (): void {
    $store = app(MaddraxikonCandidateStore::class);
    Storage::disk('private')->put('maddrax.json', json_encode([$this->row(1, 'Euree', 'Dateistand A')]));
    $candidate = $store->stage(['maddrax' => [$this->row(1, 'Weltrat', 'Kandidat')]]);
    Storage::disk('private')->put('maddrax.json', json_encode([$this->row(1, 'Euree', 'Dateistand B')]));
    expect(fn () => $store->load($candidate['id']))->toThrow(MaddraxikonCrawlException::class, 'seit der Kandidatenerstellung geändert');
});

test('legacy file coverage error names the actual baseline source', function (): void {
    Storage::disk('private')->put('maddrax.json', json_encode([$this->row(1, 'Euree', 'Roman 1'), $this->row(2, 'Euree', 'Roman 2')]));
    expect(fn () => app(MaddraxikonCandidateStore::class)->stage(['maddrax' => [$this->row(1, 'Euree', 'Roman 1')]]))->toThrow(MaddraxikonCrawlException::class, 'gegenüber der bisherigen JSON-Datei');
});

test('promotion reports snapshot and book updates without claiming a json update', function (): void {
    $candidate = app(MaddraxikonCandidateStore::class)->stage(['maddrax' => [$this->row(1, 'Euree', 'Titel')]]);
    $this->artisan('books:refresh', ['--promote' => $candidate['id']])->expectsOutput('Snapshot und Buchdaten für maddrax sicher aktualisiert (1 Datensatz).')->assertSuccessful();
});

test('tampered candidate is rejected', function (): void {
    $candidate = app(MaddraxikonCandidateStore::class)->stage(['maddrax' => [$this->row(1, 'Euree', 'Titel')]]);
    Storage::disk('private')->put('maddrax-candidates/'.$candidate['id'].'/maddrax.json', json_encode([$this->row(1, 'Manipuliert', 'Titel')]));
    expect(fn () => app(MaddraxikonRefreshCoordinator::class)->promote($candidate['id']))->toThrow(MaddraxikonCrawlException::class, 'Hashprüfung');
});

test('failed followup page keeps last valid snapshot byte identical', function (): void {
    $category = 'https://de.maddraxikon.com/index.php?title=Kategorie:Maddrax-Heftromane';
    $page2 = $category.'&pagefrom=201';
    $oldJson = json_encode([$this->row(1, 'Euree', 'Alt')]);
    Storage::disk('private')->put('maddrax.json', $oldJson);
    Book::create(['roman_number' => 1, 'title' => 'Alt', 'author' => 'Autor', 'cycle' => 'Euree']);
    Http::fakeSequence()->push('<meta charset="UTF-8"><div id="mw-pages"><a href="/wiki/MX_1">MX 1</a><a href="'.$page2.'">nächste Seite</a></div>')->push('maintenance', 503)->push('maintenance', 503)->push('maintenance', 503);
    $this->artisan('books:refresh', ['--series' => 'maddrax'])->assertFailed();
    expect(Storage::disk('private')->get('maddrax.json'))->toBe($oldJson);
    $this->assertDatabaseHas('books', ['roman_number' => 1, 'title' => 'Alt', 'cycle' => 'Euree']);
});
