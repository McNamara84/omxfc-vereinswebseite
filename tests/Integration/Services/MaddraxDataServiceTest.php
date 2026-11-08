<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\BookType;
use App\Services\MaddraxDataService;
use App\Services\Maddraxikon\MaddraxikonSnapshotRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxDataServiceTestFixtures::class);

covers(MaddraxDataService::class);

beforeEach(function (): void {
    $this->testStoragePath = base_path('storage/testing');
    $this->app->useStoragePath($this->testStoragePath);
    File::ensureDirectoryExists($this->testStoragePath.'/app/private');
    $this->service = app(MaddraxDataService::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->testStoragePath);
});

test('get maddrax romane returns collection', function (): void {
    $this->createMaddraxFile();
    $result = $this->service->getMaddraxRomane();
    expect($result)->toHaveCount(2)
        ->and($result[0]['titel'])->toEqual('Erster Roman')
        ->and($result[1]['titel'])->toEqual('Zweiter Roman');
});

test('get maddrax romane returns empty collection when file missing', function (): void {
    $result = $this->service->getMaddraxRomane();
    expect($result)->toHaveCount(0);
});

test('get cycle map returns nummer to zyklus mapping', function (): void {
    $this->createMaddraxFile();
    $cycleMap = $this->service->getCycleMap();
    expect($cycleMap[1])->toEqual('Euree')
        ->and($cycleMap[2])->toEqual('Euree');
});

test('get hardcovers returns collection', function (): void {
    $data = [['nummer' => 1, 'titel' => 'HC1', 'text' => ['Autor1'], 'bewertung' => 4.5]];
    $this->createSeriesFile('hardcovers.json', $data);
    $result = $this->service->getHardcovers();
    expect($result)->toHaveCount(1)
        ->and($result[0]['titel'])->toEqual('HC1');
});

test('get mission mars returns collection', function (): void {
    $data = [['nummer' => 1, 'titel' => 'Mission Mars 1', 'text' => ['Autor1']]];
    $this->createSeriesFile('missionmars.json', $data);
    $result = $this->service->getMissionMars();
    expect($result)->toHaveCount(1)
        ->and($result[0]['titel'])->toEqual('Mission Mars 1');
});

test('get volk der tiefe returns collection', function (): void {
    $data = [['nummer' => 1, 'titel' => 'Volk der Tiefe 1', 'text' => ['Autor1']]];
    $this->createSeriesFile('volkdertiefe.json', $data);
    $result = $this->service->getVolkDerTiefe();
    expect($result)->toHaveCount(1)
        ->and($result[0]['titel'])->toEqual('Volk der Tiefe 1');
});

test('get 2012 returns collection', function (): void {
    $data = [['nummer' => 1, 'titel' => '2012 Roman 1', 'text' => ['Autor1']]];
    $this->createSeriesFile('2012.json', $data);
    $result = $this->service->get2012();
    expect($result)->toHaveCount(1)
        ->and($result[0]['titel'])->toEqual('2012 Roman 1');
});

test('get abenteurer returns collection', function (): void {
    $data = [['nummer' => 1, 'titel' => 'Abenteurer 1', 'text' => ['Autor1']]];
    $this->createSeriesFile('abenteurer.json', $data);
    $result = $this->service->getAbenteurer();
    expect($result)->toHaveCount(1)
        ->and($result[0]['titel'])->toEqual('Abenteurer 1');
});

test('get all series returns all series', function (): void {
    $this->createMaddraxFile();
    $this->createSeriesFile('hardcovers.json', [['nummer' => 1, 'titel' => 'HC1', 'text' => []]]);
    $this->createSeriesFile('missionmars.json', []);
    $this->createSeriesFile('volkdertiefe.json', []);
    $this->createSeriesFile('2012.json', []);
    $this->createSeriesFile('abenteurer.json', []);
    $result = $this->service->getAllSeries();
    expect($result)->toHaveKeys(['maddrax', 'hardcovers', 'missionmars', 'volkdertiefe', '2012', 'abenteurer'])
        ->and($result['maddrax'])->toHaveCount(2)
        ->and($result['hardcovers'])->toHaveCount(1);
});

test('caching returns same data after file change', function (): void {
    $this->createMaddraxFile([['nummer' => 1, 'titel' => 'Original', 'text' => [], 'zyklus' => 'Test']]);
    // Erster Aufruf - Daten werden gecacht
    $firstResult = $this->service->getMaddraxRomane();
    expect($firstResult[0]['titel'])->toEqual('Original');
    // Datei ändern
    $this->createMaddraxFile([['nummer' => 1, 'titel' => 'Geändert', 'text' => [], 'zyklus' => 'Test']]);
    // Zweiter Aufruf - sollte noch gecachte Daten zurückgeben
    $secondResult = $this->service->getMaddraxRomane();
    expect($secondResult[0]['titel'])->toEqual('Original');
});

test('snapshot cache uses a stable key and refreshes the injected pointer cache', function (): void {
    $type = BookType::MaddraxDieDunkleZukunftDerErde;
    $oldId = '11111111-1111-4111-8111-111111111111';
    $newId = '22222222-2222-4222-8222-222222222222';
    $oldRows = [['nummer' => 1, 'titel' => 'Alter Snapshot']];
    $newRows = [['nummer' => 1, 'titel' => 'Neuer Snapshot']];
    $snapshots = Mockery::mock(MaddraxikonSnapshotRepository::class);
    $snapshots->shouldReceive('activeId')->times(3)->with($type)->andReturn($oldId, $newId, $newId);
    $snapshots->shouldReceive('dataset')->once()->with($oldId, $type)->andReturn($oldRows);
    $snapshots->shouldReceive('clearActiveIdsCache')->once();
    $snapshots->shouldReceive('dataset')->twice()->with($newId, $type)->andReturn($newRows);
    $service = new MaddraxDataService($snapshots);
    expect($service->getMaddraxRomane()->all())->toBe($oldRows)
        ->and(Cache::get('maddrax_series_maddrax'))->toBe(['snapshot_id' => $oldId, 'rows' => $oldRows])
        ->and(Cache::has("maddrax_series_maddrax_snapshot_{$oldId}"))->toBeFalse()
        ->and($service->getMaddraxRomane()->all())->toBe($newRows)
        ->and(Cache::get('maddrax_series_maddrax'))->toBe(['snapshot_id' => $newId, 'rows' => $newRows]);
    $service->clearCache('maddrax');
    expect(Cache::get('maddrax_series_maddrax'))->toBeNull()
        ->and($service->getMaddraxRomane()->all())->toBe($newRows)
        ->and(Cache::get('maddrax_series_maddrax'))->toBe(['snapshot_id' => $newId, 'rows' => $newRows])
        ->and(Cache::has("maddrax_series_maddrax_snapshot_{$newId}"))->toBeFalse();
});

test('clear cache resets pointer cache before a cache backend failure', function (): void {
    $pointerCacheWasReset = false;
    $snapshots = Mockery::mock(MaddraxikonSnapshotRepository::class);
    $snapshots->shouldReceive('clearActiveIdsCache')->once()->andReturnUsing(function () use (&$pointerCacheWasReset): void {
        $pointerCacheWasReset = true;
    });
    Cache::shouldReceive('forget')->once()->with('maddrax_series_hardcovers')->andReturnUsing(function () use (&$pointerCacheWasReset): void {
        expect($pointerCacheWasReset)->toBeTrue();
        throw new \RuntimeException('Cache nicht erreichbar');
    });
    try {
        (new MaddraxDataService($snapshots))->clearCache('hardcovers');
        $this->fail('Expected cache backend failure.');
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Cache nicht erreichbar');
    }
});

test('clear cache invalidates specific series', function (): void {
    $this->createMaddraxFile([['nummer' => 1, 'titel' => 'Original', 'text' => [], 'zyklus' => 'Test']]);
    // Erster Aufruf - Daten werden gecacht
    $this->service->getMaddraxRomane();
    // Datei ändern
    $this->createMaddraxFile([['nummer' => 1, 'titel' => 'Geändert', 'text' => [], 'zyklus' => 'Test']]);
    // Cache für maddrax leeren
    $this->service->clearCache('maddrax');
    // Sollte jetzt die neuen Daten zurückgeben
    $result = $this->service->getMaddraxRomane();
    expect($result[0]['titel'])->toEqual('Geändert');
});

test('clear cache without key invalidates all', function (): void {
    $this->createMaddraxFile([['nummer' => 1, 'titel' => 'Original', 'text' => [], 'zyklus' => 'Test']]);
    // Erster Aufruf
    $this->service->getMaddraxRomane();
    // Datei ändern
    $this->createMaddraxFile([['nummer' => 1, 'titel' => 'Geändert', 'text' => [], 'zyklus' => 'Test']]);
    // Gesamten Cache leeren
    $this->service->clearCache();
    // Sollte jetzt die neuen Daten zurückgeben
    $result = $this->service->getMaddraxRomane();
    expect($result[0]['titel'])->toEqual('Geändert');
});

test('returns empty collection for invalid json', function (): void {
    $path = storage_path('app/private/maddrax.json');
    file_put_contents($path, 'invalid json content');
    $result = $this->service->getMaddraxRomane();
    expect($result)->toHaveCount(0);
});

test('optional series return empty without warning when missing', function (): void {
    // Nur maddrax.json erstellen, optionale Serien fehlen
    $this->createMaddraxFile();
    // Sollte keine Exceptions werfen und leere Collections zurückgeben
    $hardcovers = $this->service->getHardcovers();
    $missionMars = $this->service->getMissionMars();
    $volkDerTiefe = $this->service->getVolkDerTiefe();
    $zweitausendzwoelf = $this->service->get2012();
    $abenteurer = $this->service->getAbenteurer();
    expect($hardcovers)->toHaveCount(0)
        ->and($missionMars)->toHaveCount(0)
        ->and($volkDerTiefe)->toHaveCount(0)
        ->and($zweitausendzwoelf)->toHaveCount(0)
        ->and($abenteurer)->toHaveCount(0);
});
