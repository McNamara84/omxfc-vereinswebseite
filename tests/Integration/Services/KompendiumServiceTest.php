<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\KompendiumRoman;
use App\Models\User;
use App\Services\KompendiumService;
use App\Services\MaddraxDataService;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KompendiumServiceTestFixtures::class);

covers(KompendiumService::class);

beforeEach(function (): void {
    $this->maddraxDataService = Mockery::mock(MaddraxDataService::class);
    $this->service = new KompendiumService($this->maddraxDataService);
});

test('parse dateiname parst gueltiges format', function (): void {
    $result = $this->service->parseDateiname('001 - Der Gott aus dem Eis.txt');
    expect($result)->not->toBeNull()
        ->and($result['nummer'])->toEqual(1)
        ->and($result['titel'])->toEqual('Der Gott aus dem Eis');
});

test('parse dateiname parst dreistellige nummer', function (): void {
    $result = $this->service->parseDateiname('123 - Ein langer Titel mit Sonderzeichen!.txt');
    expect($result)->not->toBeNull()
        ->and($result['nummer'])->toEqual(123)
        ->and($result['titel'])->toEqual('Ein langer Titel mit Sonderzeichen!');
});

test('parse dateiname parst ohne fuehrende nullen', function (): void {
    $result = $this->service->parseDateiname('5 - Kurzer Titel.txt');
    expect($result)->not->toBeNull()
        ->and($result['nummer'])->toEqual(5)
        ->and($result['titel'])->toEqual('Kurzer Titel');
});

test('parse dateiname gibt null bei ungueltigem format', function (): void {
    expect($this->service->parseDateiname('Ohne Nummer.txt'))->toBeNull()
        ->and($this->service->parseDateiname('abc - Mit Buchstaben.txt'))->toBeNull()
        ->and($this->service->parseDateiname('123.txt'))->toBeNull()
        ->and($this->service->parseDateiname(''))->toBeNull();
});

test('finde metadaten findet roman in maddrax serie', function (): void {
    $this->maddraxDataService->shouldReceive('getSeries')->with('maddrax')->andReturn(collect([['nummer' => 1, 'titel' => 'Der Gott aus dem Eis', 'zyklus' => 'Euree'], ['nummer' => 2, 'titel' => 'Stadt der Verdammten', 'zyklus' => 'Euree']]));
    // Andere Serien als leer mocken
    $this->maddraxDataService->shouldReceive('getSeries')->withAnyArgs()->andReturn(collect([]));
    $result = $this->service->findeMetadaten(1, 'Der Gott aus dem Eis');
    expect($result)->not->toBeNull()
        ->and($result['serie'])->toEqual('maddrax')
        ->and($result['zyklus'])->toEqual('Euree');
});

test('finde metadaten findet roman in anderer serie', function (): void {
    $this->maddraxDataService->shouldReceive('getSeries')->with('maddrax')->andReturn(collect([]));
    $this->maddraxDataService->shouldReceive('getSeries')->with('hardcovers')->andReturn(collect([]));
    $this->maddraxDataService->shouldReceive('getSeries')->with('missionmars')->andReturn(collect([['nummer' => 1, 'titel' => 'Mission Mars Titel', 'zyklus' => null]]));
    $this->maddraxDataService->shouldReceive('getSeries')->withAnyArgs()->andReturn(collect([]));
    $result = $this->service->findeMetadaten(1, 'Mission Mars Titel');
    expect($result)->not->toBeNull()
        ->and($result['serie'])->toEqual('missionmars')
        ->and($result['zyklus'])->toBeNull();
});

test('finde metadaten gibt null wenn nicht gefunden', function (): void {
    $this->maddraxDataService->shouldReceive('getSeries')->withAnyArgs()->andReturn(collect([]));
    $result = $this->service->findeMetadaten(999, 'Nicht existierender Titel');
    expect($result)->toBeNull();
});

test('finde metadaten liefert erstveroeffentlichungsdatum aus evt', function (): void {
    $this->maddraxDataService->shouldReceive('getSeries')->with('maddrax')->andReturn(collect([['nummer' => 1, 'titel' => 'Der Gott aus dem Eis', 'zyklus' => 'Euree', 'evt' => '2024-01']]));
    $this->maddraxDataService->shouldReceive('getSeries')->withAnyArgs()->andReturn(collect([]));
    $result = $this->service->findeMetadaten(1, 'Der Gott aus dem Eis');
    expect($result)->not->toBeNull()
        ->and($result['erstveroeffentlicht_am']?->toDateString())->toBe('2024-01-01');
});

test('parse erstveroeffentlicht am normalisiert gaengige datumsformate', function (): void {
    expect($this->service->parseErstveroeffentlichtAm('2024-01')?->toDateString())->toBe('2024-01-01')
        ->and($this->service->parseErstveroeffentlichtAm('7.5.2024')?->toDateString())->toBe('2024-05-07')
        ->and($this->service->parseErstveroeffentlichtAm('2024')?->toDateString())->toBe('2024-01-01')
        ->and($this->service->parseErstveroeffentlichtAm('kein datum'))->toBeNull();
});

test('parse erstveroeffentlicht am gibt null fuer ungueltiges jahresformat zurueck', function (): void {
    expect($this->service->parseErstveroeffentlichtAm('0000'))->toBeNull();
});

test('get serien liste gibt alle serien zurueck', function (): void {
    $serien = $this->service->getSerienListe();
    expect($serien)->toBeArray()
        ->toHaveKeys(['maddrax', 'hardcovers', 'missionmars', 'volkdertiefe', '2012', 'abenteurer']);
});

test('get serien name gibt korrekten namen zurueck', function (): void {
    expect($this->service->getSerienName('maddrax'))->toEqual('Maddrax - Die dunkle Zukunft der Erde')
        ->and($this->service->getSerienName('missionmars'))->toEqual('Mission Mars')
        ->and($this->service->getSerienName('unknown'))->toEqual('unknown');
});

test('get statistiken gibt korrekte zahlen zurueck', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    // Erstelle Test-Romane
    KompendiumRoman::create(['dateiname' => '001 - Test1.txt', 'dateipfad' => 'romane/maddrax/001 - Test1.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test1', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    KompendiumRoman::create(['dateiname' => '002 - Test2.txt', 'dateipfad' => 'romane/maddrax/002 - Test2.txt', 'serie' => 'maddrax', 'roman_nr' => 2, 'titel' => 'Test2', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'hochgeladen']);
    KompendiumRoman::create(['dateiname' => '003 - Test3.txt', 'dateipfad' => 'romane/maddrax/003 - Test3.txt', 'serie' => 'maddrax', 'roman_nr' => 3, 'titel' => 'Test3', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'fehler']);
    $stats = $this->service->getStatistiken();
    expect($stats['gesamt'])->toEqual(3)
        ->and($stats['indexiert'])->toEqual(1)
        ->and($stats['hochgeladen'])->toEqual(1)
        ->and($stats['fehler'])->toEqual(1)
        ->and($stats['in_bearbeitung'])->toEqual(0);
});

test('get indexierte romane gruppiert nach zyklus', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    KompendiumRoman::create(['dateiname' => '001 - Test1.txt', 'dateipfad' => 'romane/maddrax/001 - Test1.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test1', 'zyklus' => 'Euree', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    KompendiumRoman::create(['dateiname' => '025 - Test25.txt', 'dateipfad' => 'romane/maddrax/025 - Test25.txt', 'serie' => 'maddrax', 'roman_nr' => 25, 'titel' => 'Test25', 'zyklus' => 'Meeraka', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    $gruppiert = $this->service->getIndexierteRomaneGruppiert();
    expect($gruppiert)->toBeInstanceOf(Collection::class)
        ->and($gruppiert->has('Euree'))->toBeTrue()
        ->and($gruppiert->has('Meeraka'))->toBeTrue();
});

test('get indexierte romane summary formatiert bandbereich korrekt', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    // Erstelle Romane für Band 1-3 und 5
    foreach ([1, 2, 3, 5] as $nr) {
        KompendiumRoman::create(['dateiname' => str_pad((string) $nr, 3, '0', STR_PAD_LEFT)." - Test{$nr}.txt", 'dateipfad' => 'romane/maddrax/'.str_pad((string) $nr, 3, '0', STR_PAD_LEFT)." - Test{$nr}.txt", 'serie' => 'maddrax', 'roman_nr' => $nr, 'titel' => "Test{$nr}", 'zyklus' => 'Euree', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    }
    $summary = $this->service->getIndexierteRomaneSummary();
    expect($summary->has('Euree'))->toBeTrue();
    $euree = $summary->get('Euree');
    expect($euree['bandbereich'])->toEqual('1-3, 5')
        ->and($euree['anzahl'])->toEqual(4);
});

test('fuzzy match findet exakten treffer', function (): void {
    $this->maddraxDataService->shouldReceive('getSeries')->with('maddrax')->andReturn(collect([['nummer' => 1, 'titel' => 'Der Gott aus dem Eis', 'zyklus' => 'Euree']]));
    $this->maddraxDataService->shouldReceive('getSeries')->withAnyArgs()->andReturn(collect([]));
    $result = $this->service->findeMetadatenMitFuzzy(1, 'Der Gott aus dem Eis');
    expect($result)->not->toBeNull()
        ->and($result['serie'])->toEqual('maddrax')
        ->and($result['zyklus'])->toEqual('Euree')
        ->and($result['match_typ'])->toEqual('exakt');
});

test('fuzzy match findet normalisierten treffer', function (): void {
    $this->maddraxDataService->shouldReceive('getSeries')->with('maddrax')->andReturn(collect([['nummer' => 1, 'titel' => 'Der Gott aus dem Eis!', 'zyklus' => 'Euree']]));
    $this->maddraxDataService->shouldReceive('getSeries')->withAnyArgs()->andReturn(collect([]));
    // Leicht abweichender Titel (Klein, ohne Sonderzeichen)
    $result = $this->service->findeMetadatenMitFuzzy(1, 'der gott aus dem eis');
    expect($result)->not->toBeNull()
        ->and($result['serie'])->toEqual('maddrax')
        ->and($result['match_typ'])->toEqual('normalisiert');
});

test('fuzzy match findet nur nummer treffer', function (): void {
    $this->maddraxDataService->shouldReceive('getSeries')->with('maddrax')->andReturn(collect([['nummer' => 42, 'titel' => 'Ganz anderer Titel', 'zyklus' => 'Euree']]));
    $this->maddraxDataService->shouldReceive('getSeries')->withAnyArgs()->andReturn(collect([]));
    // Titel stimmt überhaupt nicht, aber nur ein Roman mit Nr. 42
    $result = $this->service->findeMetadatenMitFuzzy(42, 'Falscher Titel komplett');
    expect($result)->not->toBeNull()
        ->and($result['serie'])->toEqual('maddrax')
        ->and($result['match_typ'])->toEqual('nummer');
});

test('fuzzy match gibt null bei mehrfach nummer ohne titel', function (): void {
    // Nummer 1 kommt in maddrax UND missionmars vor → keine eindeutige Zuordnung
    $this->maddraxDataService->shouldReceive('getSeries')->with('maddrax')->andReturn(collect([['nummer' => 1, 'titel' => 'Maddrax Titel', 'zyklus' => 'Euree']]));
    $this->maddraxDataService->shouldReceive('getSeries')->with('missionmars')->andReturn(collect([['nummer' => 1, 'titel' => 'Mission Mars Titel', 'zyklus' => null]]));
    $this->maddraxDataService->shouldReceive('getSeries')->withAnyArgs()->andReturn(collect([]));
    $result = $this->service->findeMetadatenMitFuzzy(1, 'Ganz was anderes');
    expect($result)->toBeNull();
});

test('fuzzy match gibt null wenn nichts gefunden', function (): void {
    $this->maddraxDataService->shouldReceive('getSeries')->withAnyArgs()->andReturn(collect([]));
    $result = $this->service->findeMetadatenMitFuzzy(999, 'Existiert nicht');
    expect($result)->toBeNull();
});

test('normalisiere titel entfernt sonderzeichen und whitespace', function (): void {
    expect($this->service->normalisiereTitel('Der Gott aus dem Eis'))->toEqual('der gott aus dem eis')
        ->and($this->service->normalisiereTitel('Der Gott  aus  dem  Eis!'))->toEqual('der gott aus dem eis')
        ->and($this->service->normalisiereTitel('  TEST  '))->toEqual('test')
        ->and($this->service->normalisiereTitel('Rückkehr'))->toEqual('rückkehr');
});

test('zusammengefasste uebersicht gibt leer bei keinen indexierten', function (): void {
    $result = $this->service->getZusammengefassteUebersicht();
    expect($result->isEmpty())->toBeTrue();
});

test('zusammengefasste uebersicht konsolidiert maddrax zyklen', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    // Euree-Zyklus: Band 1
    KompendiumRoman::create(['dateiname' => '001 - Test1.txt', 'dateipfad' => 'romane/maddrax/001 - Test1.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test1', 'zyklus' => 'Euree', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    // Meeraka-Zyklus: Band 25
    KompendiumRoman::create(['dateiname' => '025 - Test25.txt', 'dateipfad' => 'romane/maddrax/025 - Test25.txt', 'serie' => 'maddrax', 'roman_nr' => 25, 'titel' => 'Test25', 'zyklus' => 'Meeraka', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    $this->maddraxDataService->shouldReceive('getSeries')->withAnyArgs()->andReturn(collect([]));
    $result = $this->service->getZusammengefassteUebersicht();
    expect($result)->toHaveCount(1);
    $maddrax = $result->first();
    expect($maddrax['serie'])->toEqual('maddrax')
        ->and($maddrax['serie_name'])->toEqual('Maddrax')
        ->and($maddrax['anzahl'])->toEqual(2);
    // Beschreibung enthält Euree und Meeraka
    expect($maddrax['beschreibung'])->toContain('Euree');
    expect($maddrax['beschreibung'])->toContain('Meeraka');
});

test('zusammengefasste uebersicht miniserie als ein eintrag', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    foreach ([1, 2, 3] as $nr) {
        KompendiumRoman::create(['dateiname' => str_pad((string) $nr, 3, '0', STR_PAD_LEFT)." - Mars{$nr}.txt", 'dateipfad' => 'romane/missionmars/'.str_pad((string) $nr, 3, '0', STR_PAD_LEFT)." - Mars{$nr}.txt", 'serie' => 'missionmars', 'roman_nr' => $nr, 'titel' => "Mars{$nr}", 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    }
    $result = $this->service->getZusammengefassteUebersicht();
    expect($result)->toHaveCount(1);
    $mars = $result->first();
    expect($mars['serie'])->toEqual('missionmars')
        ->and($mars['serie_name'])->toEqual('Mission Mars')
        ->and($mars['beschreibung'])->toContain('Band 1-3');
});

test('zyklen fortschritt zeigt status korrekt', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    // Ein Roman im Euree-Zyklus (Soll: 24 Romane laut JSON)
    KompendiumRoman::create(['dateiname' => '001 - Test1.txt', 'dateipfad' => 'romane/maddrax/001 - Test1.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test1', 'zyklus' => 'Euree', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    // Mock: Maddrax hat 24 Romane im Euree-Zyklus
    $this->maddraxDataService->shouldReceive('getSeries')->with('maddrax')->andReturn(collect([...array_map(fn ($n) => ['nummer' => $n, 'titel' => "Roman {$n}", 'zyklus' => 'Euree'], range(1, 24))]));
    $this->maddraxDataService->shouldReceive('getSeries')->withAnyArgs()->andReturn(collect([]));
    $fortschritt = $this->service->getZyklenFortschritt();
    expect($fortschritt)->not->toBeEmpty();
    // Finde den Euree-Eintrag
    $euree = collect($fortschritt)->firstWhere('zyklus', 'Euree');
    expect($euree)->not->toBeNull()
        ->and($euree['soll'])->toEqual(24)
        ->and($euree['ist'])->toEqual(1)
        ->and($euree['status'])->toEqual('teilweise');
});
