<?php

namespace Tests\Unit\Services;

use App\Services\MaddraxDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(MaddraxDataService::class)]
class MaddraxDataServiceTest extends TestCase
{
    use RefreshDatabase;

    private MaddraxDataService $service;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath.'/app/private');

        $this->service = new MaddraxDataService;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);

        parent::tearDown();
    }

    private function createMaddraxFile(array $data = []): void
    {
        $defaultData = [
            [
                'nummer' => 1,
                'titel' => 'Erster Roman',
                'text' => ['Autor1'],
                'zyklus' => 'Euree',
                'bewertung' => 4.0,
                'stimmen' => 10,
            ],
            [
                'nummer' => 2,
                'titel' => 'Zweiter Roman',
                'text' => ['Autor1', 'Autor2'],
                'zyklus' => 'Euree',
                'bewertung' => 5.0,
                'stimmen' => 20,
            ],
        ];

        $path = storage_path('app/private/maddrax.json');
        file_put_contents($path, json_encode($data ?: $defaultData));
    }

    private function createSeriesFile(string $filename, array $data = []): void
    {
        $path = storage_path('app/private/'.$filename);
        file_put_contents($path, json_encode($data));
    }

    public function test_get_maddrax_romane_returns_collection(): void
    {
        $this->createMaddraxFile();

        $result = $this->service->getMaddraxRomane();

        $this->assertCount(2, $result);
        $this->assertEquals('Erster Roman', $result[0]['titel']);
        $this->assertEquals('Zweiter Roman', $result[1]['titel']);
    }

    public function test_get_maddrax_romane_returns_empty_collection_when_file_missing(): void
    {
        $result = $this->service->getMaddraxRomane();

        $this->assertCount(0, $result);
    }

    public function test_get_cycle_map_returns_nummer_to_zyklus_mapping(): void
    {
        $this->createMaddraxFile();

        $cycleMap = $this->service->getCycleMap();

        $this->assertEquals('Euree', $cycleMap[1]);
        $this->assertEquals('Euree', $cycleMap[2]);
    }

    public function test_get_hardcovers_returns_collection(): void
    {
        $data = [
            ['nummer' => 1, 'titel' => 'HC1', 'text' => ['Autor1'], 'bewertung' => 4.5],
        ];
        $this->createSeriesFile('hardcovers.json', $data);

        $result = $this->service->getHardcovers();

        $this->assertCount(1, $result);
        $this->assertEquals('HC1', $result[0]['titel']);
    }

    public function test_get_mission_mars_returns_collection(): void
    {
        $data = [
            ['nummer' => 1, 'titel' => 'Mission Mars 1', 'text' => ['Autor1']],
        ];
        $this->createSeriesFile('missionmars.json', $data);

        $result = $this->service->getMissionMars();

        $this->assertCount(1, $result);
        $this->assertEquals('Mission Mars 1', $result[0]['titel']);
    }

    public function test_get_volk_der_tiefe_returns_collection(): void
    {
        $data = [
            ['nummer' => 1, 'titel' => 'Volk der Tiefe 1', 'text' => ['Autor1']],
        ];
        $this->createSeriesFile('volkdertiefe.json', $data);

        $result = $this->service->getVolkDerTiefe();

        $this->assertCount(1, $result);
        $this->assertEquals('Volk der Tiefe 1', $result[0]['titel']);
    }

    public function test_get_2012_returns_collection(): void
    {
        $data = [
            ['nummer' => 1, 'titel' => '2012 Roman 1', 'text' => ['Autor1']],
        ];
        $this->createSeriesFile('2012.json', $data);

        $result = $this->service->get2012();

        $this->assertCount(1, $result);
        $this->assertEquals('2012 Roman 1', $result[0]['titel']);
    }

    public function test_get_abenteurer_returns_collection(): void
    {
        $data = [
            ['nummer' => 1, 'titel' => 'Abenteurer 1', 'text' => ['Autor1']],
        ];
        $this->createSeriesFile('abenteurer.json', $data);

        $result = $this->service->getAbenteurer();

        $this->assertCount(1, $result);
        $this->assertEquals('Abenteurer 1', $result[0]['titel']);
    }

    public function test_get_all_series_returns_all_series(): void
    {
        $this->createMaddraxFile();
        $this->createSeriesFile('hardcovers.json', [['nummer' => 1, 'titel' => 'HC1', 'text' => []]]);
        $this->createSeriesFile('missionmars.json', []);
        $this->createSeriesFile('volkdertiefe.json', []);
        $this->createSeriesFile('2012.json', []);
        $this->createSeriesFile('abenteurer.json', []);

        $result = $this->service->getAllSeries();

        $this->assertArrayHasKey('maddrax', $result);
        $this->assertArrayHasKey('hardcovers', $result);
        $this->assertArrayHasKey('missionmars', $result);
        $this->assertArrayHasKey('volkdertiefe', $result);
        $this->assertArrayHasKey('2012', $result);
        $this->assertArrayHasKey('abenteurer', $result);

        $this->assertCount(2, $result['maddrax']);
        $this->assertCount(1, $result['hardcovers']);
    }

    public function test_caching_returns_same_data_after_file_change(): void
    {
        $this->createMaddraxFile([
            ['nummer' => 1, 'titel' => 'Original', 'text' => [], 'zyklus' => 'Test'],
        ]);

        // Erster Aufruf - Daten werden gecacht
        $firstResult = $this->service->getMaddraxRomane();
        $this->assertEquals('Original', $firstResult[0]['titel']);

        // Datei ändern
        $this->createMaddraxFile([
            ['nummer' => 1, 'titel' => 'Geändert', 'text' => [], 'zyklus' => 'Test'],
        ]);

        // Zweiter Aufruf - sollte noch gecachte Daten zurückgeben
        $secondResult = $this->service->getMaddraxRomane();
        $this->assertEquals('Original', $secondResult[0]['titel']);
    }

    public function test_clear_cache_invalidates_specific_series(): void
    {
        $this->createMaddraxFile([
            ['nummer' => 1, 'titel' => 'Original', 'text' => [], 'zyklus' => 'Test'],
        ]);

        // Erster Aufruf - Daten werden gecacht
        $this->service->getMaddraxRomane();

        // Datei ändern
        $this->createMaddraxFile([
            ['nummer' => 1, 'titel' => 'Geändert', 'text' => [], 'zyklus' => 'Test'],
        ]);

        // Cache für maddrax leeren
        $this->service->clearCache('maddrax');

        // Sollte jetzt die neuen Daten zurückgeben
        $result = $this->service->getMaddraxRomane();
        $this->assertEquals('Geändert', $result[0]['titel']);
    }

    public function test_clear_cache_without_key_invalidates_all(): void
    {
        $this->createMaddraxFile([
            ['nummer' => 1, 'titel' => 'Original', 'text' => [], 'zyklus' => 'Test'],
        ]);

        // Erster Aufruf
        $this->service->getMaddraxRomane();

        // Datei ändern
        $this->createMaddraxFile([
            ['nummer' => 1, 'titel' => 'Geändert', 'text' => [], 'zyklus' => 'Test'],
        ]);

        // Gesamten Cache leeren
        $this->service->clearCache();

        // Sollte jetzt die neuen Daten zurückgeben
        $result = $this->service->getMaddraxRomane();
        $this->assertEquals('Geändert', $result[0]['titel']);
    }

    public function test_returns_empty_collection_for_invalid_json(): void
    {
        $path = storage_path('app/private/maddrax.json');
        file_put_contents($path, 'invalid json content');

        $result = $this->service->getMaddraxRomane();

        $this->assertCount(0, $result);
    }

    public function test_optional_series_return_empty_without_warning_when_missing(): void
    {
        // Nur maddrax.json erstellen, optionale Serien fehlen
        $this->createMaddraxFile();

        // Sollte keine Exceptions werfen und leere Collections zurückgeben
        $hardcovers = $this->service->getHardcovers();
        $missionMars = $this->service->getMissionMars();
        $volkDerTiefe = $this->service->getVolkDerTiefe();
        $zweitausendzwoelf = $this->service->get2012();
        $abenteurer = $this->service->getAbenteurer();

        $this->assertCount(0, $hardcovers);
        $this->assertCount(0, $missionMars);
        $this->assertCount(0, $volkDerTiefe);
        $this->assertCount(0, $zweitausendzwoelf);
        $this->assertCount(0, $abenteurer);
    }
}
