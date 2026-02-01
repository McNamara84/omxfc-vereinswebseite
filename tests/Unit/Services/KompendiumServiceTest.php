<?php

namespace Tests\Unit\Services;

use App\Models\KompendiumRoman;
use App\Services\KompendiumService;
use App\Services\MaddraxDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(KompendiumService::class)]
class KompendiumServiceTest extends TestCase
{
    use RefreshDatabase;

    private KompendiumService $service;

    private MaddraxDataService $maddraxDataService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maddraxDataService = Mockery::mock(MaddraxDataService::class);
        $this->service = new KompendiumService($this->maddraxDataService);
    }

    #[Test]
    public function parse_dateiname_parst_gueltiges_format(): void
    {
        $result = $this->service->parseDateiname('001 - Der Gott aus dem Eis.txt');

        $this->assertNotNull($result);
        $this->assertEquals(1, $result['nummer']);
        $this->assertEquals('Der Gott aus dem Eis', $result['titel']);
    }

    #[Test]
    public function parse_dateiname_parst_dreistellige_nummer(): void
    {
        $result = $this->service->parseDateiname('123 - Ein langer Titel mit Sonderzeichen!.txt');

        $this->assertNotNull($result);
        $this->assertEquals(123, $result['nummer']);
        $this->assertEquals('Ein langer Titel mit Sonderzeichen!', $result['titel']);
    }

    #[Test]
    public function parse_dateiname_parst_ohne_fuehrende_nullen(): void
    {
        $result = $this->service->parseDateiname('5 - Kurzer Titel.txt');

        $this->assertNotNull($result);
        $this->assertEquals(5, $result['nummer']);
        $this->assertEquals('Kurzer Titel', $result['titel']);
    }

    #[Test]
    public function parse_dateiname_gibt_null_bei_ungueltigem_format(): void
    {
        $this->assertNull($this->service->parseDateiname('Ohne Nummer.txt'));
        $this->assertNull($this->service->parseDateiname('abc - Mit Buchstaben.txt'));
        $this->assertNull($this->service->parseDateiname('123.txt'));
        $this->assertNull($this->service->parseDateiname(''));
    }

    #[Test]
    public function finde_metadaten_findet_roman_in_maddrax_serie(): void
    {
        $this->maddraxDataService
            ->shouldReceive('getSeries')
            ->with('maddrax')
            ->andReturn(collect([
                ['nummer' => 1, 'titel' => 'Der Gott aus dem Eis', 'zyklus' => 'Euree'],
                ['nummer' => 2, 'titel' => 'Stadt der Verdammten', 'zyklus' => 'Euree'],
            ]));

        // Andere Serien als leer mocken
        $this->maddraxDataService
            ->shouldReceive('getSeries')
            ->withAnyArgs()
            ->andReturn(collect([]));

        $result = $this->service->findeMetadaten(1, 'Der Gott aus dem Eis');

        $this->assertNotNull($result);
        $this->assertEquals('maddrax', $result['serie']);
        $this->assertEquals('Euree', $result['zyklus']);
    }

    #[Test]
    public function finde_metadaten_findet_roman_in_anderer_serie(): void
    {
        $this->maddraxDataService
            ->shouldReceive('getSeries')
            ->with('maddrax')
            ->andReturn(collect([]));

        $this->maddraxDataService
            ->shouldReceive('getSeries')
            ->with('hardcovers')
            ->andReturn(collect([]));

        $this->maddraxDataService
            ->shouldReceive('getSeries')
            ->with('missionmars')
            ->andReturn(collect([
                ['nummer' => 1, 'titel' => 'Mission Mars Titel', 'zyklus' => null],
            ]));

        $this->maddraxDataService
            ->shouldReceive('getSeries')
            ->withAnyArgs()
            ->andReturn(collect([]));

        $result = $this->service->findeMetadaten(1, 'Mission Mars Titel');

        $this->assertNotNull($result);
        $this->assertEquals('missionmars', $result['serie']);
        $this->assertNull($result['zyklus']);
    }

    #[Test]
    public function finde_metadaten_gibt_null_wenn_nicht_gefunden(): void
    {
        $this->maddraxDataService
            ->shouldReceive('getSeries')
            ->withAnyArgs()
            ->andReturn(collect([]));

        $result = $this->service->findeMetadaten(999, 'Nicht existierender Titel');

        $this->assertNull($result);
    }

    #[Test]
    public function get_serien_liste_gibt_alle_serien_zurueck(): void
    {
        $serien = $this->service->getSerienListe();

        $this->assertIsArray($serien);
        $this->assertArrayHasKey('maddrax', $serien);
        $this->assertArrayHasKey('hardcovers', $serien);
        $this->assertArrayHasKey('missionmars', $serien);
        $this->assertArrayHasKey('volkdertiefe', $serien);
        $this->assertArrayHasKey('2012', $serien);
        $this->assertArrayHasKey('abenteurer', $serien);
    }

    #[Test]
    public function get_serien_name_gibt_korrekten_namen_zurueck(): void
    {
        $this->assertEquals('Maddrax - Die dunkle Zukunft der Erde', $this->service->getSerienName('maddrax'));
        $this->assertEquals('Mission Mars', $this->service->getSerienName('missionmars'));
        $this->assertEquals('unknown', $this->service->getSerienName('unknown'));
    }

    #[Test]
    public function get_statistiken_gibt_korrekte_zahlen_zurueck(): void
    {
        $user = \App\Models\User::factory()->withPersonalTeam()->create();

        // Erstelle Test-Romane
        KompendiumRoman::create([
            'dateiname' => '001 - Test1.txt',
            'dateipfad' => 'romane/maddrax/001 - Test1.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test1',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        KompendiumRoman::create([
            'dateiname' => '002 - Test2.txt',
            'dateipfad' => 'romane/maddrax/002 - Test2.txt',
            'serie' => 'maddrax',
            'roman_nr' => 2,
            'titel' => 'Test2',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'hochgeladen',
        ]);

        KompendiumRoman::create([
            'dateiname' => '003 - Test3.txt',
            'dateipfad' => 'romane/maddrax/003 - Test3.txt',
            'serie' => 'maddrax',
            'roman_nr' => 3,
            'titel' => 'Test3',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'fehler',
        ]);

        $stats = $this->service->getStatistiken();

        $this->assertEquals(3, $stats['gesamt']);
        $this->assertEquals(1, $stats['indexiert']);
        $this->assertEquals(1, $stats['hochgeladen']);
        $this->assertEquals(1, $stats['fehler']);
        $this->assertEquals(0, $stats['in_bearbeitung']);
    }

    #[Test]
    public function get_indexierte_romane_gruppiert_gruppiert_nach_zyklus(): void
    {
        $user = \App\Models\User::factory()->withPersonalTeam()->create();

        KompendiumRoman::create([
            'dateiname' => '001 - Test1.txt',
            'dateipfad' => 'romane/maddrax/001 - Test1.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test1',
            'zyklus' => 'Euree',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        KompendiumRoman::create([
            'dateiname' => '025 - Test25.txt',
            'dateipfad' => 'romane/maddrax/025 - Test25.txt',
            'serie' => 'maddrax',
            'roman_nr' => 25,
            'titel' => 'Test25',
            'zyklus' => 'Meeraka',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        $gruppiert = $this->service->getIndexierteRomaneGruppiert();

        $this->assertInstanceOf(Collection::class, $gruppiert);
        $this->assertTrue($gruppiert->has('Euree'));
        $this->assertTrue($gruppiert->has('Meeraka'));
    }

    #[Test]
    public function get_indexierte_romane_summary_formatiert_bandbereich_korrekt(): void
    {
        $user = \App\Models\User::factory()->withPersonalTeam()->create();

        // Erstelle Romane für Band 1-3 und 5
        foreach ([1, 2, 3, 5] as $nr) {
            KompendiumRoman::create([
                'dateiname' => str_pad($nr, 3, '0', STR_PAD_LEFT)." - Test{$nr}.txt",
                'dateipfad' => 'romane/maddrax/'.str_pad($nr, 3, '0', STR_PAD_LEFT)." - Test{$nr}.txt",
                'serie' => 'maddrax',
                'roman_nr' => $nr,
                'titel' => "Test{$nr}",
                'zyklus' => 'Euree',
                'hochgeladen_am' => now(),
                'hochgeladen_von' => $user->id,
                'status' => 'indexiert',
            ]);
        }

        $summary = $this->service->getIndexierteRomaneSummary();

        $this->assertTrue($summary->has('Euree'));
        $euree = $summary->get('Euree');
        $this->assertEquals('1-3, 5', $euree['bandbereich']);
        $this->assertEquals(4, $euree['anzahl']);
    }
}
