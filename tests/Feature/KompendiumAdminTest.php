<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\DeIndexiereRomanJob;
use App\Jobs\IndexiereRomanJob;
use App\Livewire\KompendiumAdminDashboard;
use App\Models\KompendiumRoman;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KompendiumAdminTestFixtures::class);

covers(KompendiumAdminDashboard::class);

beforeEach(function (): void {
    Storage::fake('private');
    // Admin-User erstellen (mit membersTeam)
    $team = Team::membersTeam();
    $this->admin = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($this->admin, ['role' => Role::Admin->value]);
    // Normaler User
    $this->normalUser = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($this->normalUser, ['role' => Role::Mitglied->value]);
});

test('admin kann kompendium admin seite aufrufen', function (): void {
    $response = $this->actingAs($this->admin)->get(route('kompendium.admin'));
    $response->assertStatus(200);
    $response->assertSeeLivewire(KompendiumAdminDashboard::class);
});

test('nicht admin kann kompendium admin seite nicht aufrufen', function (): void {
    $response = $this->actingAs($this->normalUser)->get(route('kompendium.admin'));
    $response->assertStatus(403);
});

test('gast wird zur login seite weitergeleitet', function (): void {
    $response = $this->get(route('kompendium.admin'));
    $response->assertRedirect(route('login'));
});

test('admin kann txt datei hochladen', function (): void {
    $file = UploadedFile::fake()->create('001 - Der Gott aus dem Eis.txt', 100, 'text/plain');
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->set('uploads', [$file])->set('ausgewaehlteSerie', 'maddrax')->call('hochladen')->assertHasNoErrors();
    $this->assertDatabaseHas('kompendium_romane', ['dateiname' => '001 - Der Gott aus dem Eis.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Der Gott aus dem Eis', 'status' => 'hochgeladen']);
    Storage::disk('private')->assertExists('romane/maddrax/001 - Der Gott aus dem Eis.txt');
});

test('upload uebernimmt erstveroeffentlichungsdatum aus metadaten', function (): void {
    $this->bindKompendiumMetadata(['maddrax' => [['nummer' => 1, 'titel' => 'Der Gott aus dem Eis', 'zyklus' => 'Euree', 'evt' => '1999-02-16']]]);
    $file = UploadedFile::fake()->create('001 - Der Gott aus dem Eis.txt', 100, 'text/plain');
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->set('uploads', [$file])->set('ausgewaehlteSerie', 'maddrax')->call('hochladen')->assertHasNoErrors();
    $roman = KompendiumRoman::query()->firstWhere('dateipfad', 'romane/maddrax/001 - Der Gott aus dem Eis.txt');
    expect($roman)->not->toBeNull()
        ->and($roman->erstveroeffentlicht_am?->toDateString())->toBe('1999-02-16');
});

test('upload mit ungueltigem dateinamen schlaegt fehl', function (): void {
    $file = UploadedFile::fake()->create('Ungültiger Name.txt', 100, 'text/plain');
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->set('uploads', [$file])->set('ausgewaehlteSerie', 'maddrax')->call('hochladen');
    $this->assertDatabaseMissing('kompendium_romane', ['dateiname' => 'Ungültiger Name.txt']);
});

test('upload von duplikat wird abgelehnt', function (): void {
    // Ersten Upload
    KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    $file = UploadedFile::fake()->create('001 - Test.txt', 100, 'text/plain');
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->set('uploads', [$file])->set('ausgewaehlteSerie', 'maddrax')->call('hochladen');
    // Es sollte nur ein Eintrag existieren
    expect(KompendiumRoman::where('roman_nr', 1)->count())->toEqual(1);
});

test('indexieren dispatched job', function (): void {
    Queue::fake();
    $roman = KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('indexieren', $roman->id);
    Queue::assertPushed(IndexiereRomanJob::class, fn ($job) => $job->kompendiumRoman->id === $roman->id);
});

test('de indexieren dispatched job', function (): void {
    Queue::fake();
    $roman = KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexiert', 'indexiert_am' => now()]);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('deIndexieren', $roman->id);
    Queue::assertPushed(DeIndexiereRomanJob::class, fn ($job) => $job->kompendiumRoman->id === $roman->id);
});

test('loeschen entfernt datei und datenbankeintrag', function (): void {
    Storage::disk('private')->put('romane/maddrax/001 - Test.txt', 'Inhalt');
    $roman = KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('loeschen', $roman->id);
    $this->assertDatabaseMissing('kompendium_romane', ['id' => $roman->id]);
    Storage::disk('private')->assertMissing('romane/maddrax/001 - Test.txt');
});

test('loeschen indexierter roman entfernt aus index und loescht datei', function (): void {
    Storage::disk('private')->put('romane/maddrax/001 - Indexiert.txt', 'Inhalt');
    $roman = KompendiumRoman::create(['dateiname' => '001 - Indexiert.txt', 'dateipfad' => 'romane/maddrax/001 - Indexiert.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Indexiert', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexiert', 'indexiert_am' => now()]);
    // Löschen sollte nicht fehlschlagen, auch wenn der Index nicht existiert
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('loeschen', $roman->id)->assertHasNoErrors();
    $this->assertDatabaseMissing('kompendium_romane', ['id' => $roman->id]);
    Storage::disk('private')->assertMissing('romane/maddrax/001 - Indexiert.txt');
});

test('alle indexieren dispatched jobs fuer alle hochgeladenen', function (): void {
    Queue::fake();
    // 3 hochgeladene, 1 bereits indexierter Roman
    foreach ([1, 2, 3] as $nr) {
        KompendiumRoman::create(['dateiname' => str_pad((string) $nr, 3, '0', STR_PAD_LEFT)." - Test{$nr}.txt", 'dateipfad' => 'romane/maddrax/'.str_pad((string) $nr, 3, '0', STR_PAD_LEFT)." - Test{$nr}.txt", 'serie' => 'maddrax', 'roman_nr' => $nr, 'titel' => "Test{$nr}", 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    }
    KompendiumRoman::create(['dateiname' => '004 - Test4.txt', 'dateipfad' => 'romane/maddrax/004 - Test4.txt', 'serie' => 'maddrax', 'roman_nr' => 4, 'titel' => 'Test4', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexiert']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('alleIndexieren');
    Queue::assertPushed(IndexiereRomanJob::class, 3);
});

test('filter nach serie funktioniert', function (): void {
    KompendiumRoman::create(['dateiname' => '001 - HauptserieRoman.txt', 'dateipfad' => 'romane/maddrax/001 - HauptserieRoman.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'HauptserieRoman', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    KompendiumRoman::create(['dateiname' => '001 - MarsRoman.txt', 'dateipfad' => 'romane/missionmars/001 - MarsRoman.txt', 'serie' => 'missionmars', 'roman_nr' => 1, 'titel' => 'MarsRoman', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->set('filterSerie', 'missionmars')->assertSee('MarsRoman')->assertDontSee('HauptserieRoman');
});

test('filter nach status funktioniert', function (): void {
    KompendiumRoman::create(['dateiname' => '001 - NurHochgeladen.txt', 'dateipfad' => 'romane/maddrax/001 - NurHochgeladen.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'NurHochgeladen', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    KompendiumRoman::create(['dateiname' => '002 - NurIndexiert.txt', 'dateipfad' => 'romane/maddrax/002 - NurIndexiert.txt', 'serie' => 'maddrax', 'roman_nr' => 2, 'titel' => 'NurIndexiert', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexiert']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->set('filterStatus', 'indexiert')->assertSee('NurIndexiert')->assertDontSee('NurHochgeladen');
});

test('statistiken werden korrekt angezeigt', function (): void {
    KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexiert']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->assertSee('1')->assertSee('Indexiert');
});

test('retry fehler setzt status zurueck und dispatched job', function (): void {
    Queue::fake();
    $roman = KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'fehler', 'fehler_nachricht' => 'Testfehler']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('retryFehler', $roman->id);
    Queue::assertPushed(IndexiereRomanJob::class);
    $roman->refresh();
    expect($roman->status)->toEqual('hochgeladen')
        ->and($roman->fehler_nachricht)->toBeNull();
});

test('bearbeiten oeffnet modal mit daten', function (): void {
    $roman = KompendiumRoman::create(['dateiname' => '001 - TestRoman.txt', 'dateipfad' => 'romane/maddrax/001 - TestRoman.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'TestRoman', 'zyklus' => 'Euree', 'erstveroeffentlicht_am' => '2001-02-03', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('bearbeiten', $roman->id)->assertSet('showEditModal', true)->assertSet('editId', $roman->id)->assertSet('editSerie', 'maddrax')->assertSet('editZyklus', 'Euree')->assertSet('editNummer', 1)->assertSet('editTitel', 'TestRoman')->assertSet('editErstveroeffentlichtAm', '2001-02-03');
});

test('bearbeiten blockiert bei laufender indexierung', function (): void {
    $roman = KompendiumRoman::create(['dateiname' => '001 - TestRoman.txt', 'dateipfad' => 'romane/maddrax/001 - TestRoman.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'TestRoman', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexierung_laeuft']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('bearbeiten', $roman->id)->assertSet('showEditModal', false);
});

test('speichern aktualisiert roman daten', function (): void {
    Storage::disk('private')->put('romane/maddrax/001 - AlterTitel.txt', 'Inhalt');
    $roman = KompendiumRoman::create(['dateiname' => '001 - AlterTitel.txt', 'dateipfad' => 'romane/maddrax/001 - AlterTitel.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'AlterTitel', 'zyklus' => 'Euree', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('bearbeiten', $roman->id)->set('editSerie', 'missionmars')->set('editZyklus', '')->set('editNummer', 5)->set('editTitel', 'NeuerTitel')->set('editErstveroeffentlichtAm', '2005-06-07')->call('speichern')->assertSet('showEditModal', false);
    $roman->refresh();
    expect($roman->serie)->toEqual('missionmars')
        ->and($roman->zyklus)->toBeNull()
        ->and($roman->roman_nr)->toEqual(5)
        ->and($roman->titel)->toEqual('NeuerTitel')
        ->and($roman->erstveroeffentlicht_am?->toDateString())->toBe('2005-06-07')
        ->and($roman->dateipfad)->toEqual('romane/missionmars/005 - NeuerTitel.txt');
    // Datei wurde verschoben
    Storage::disk('private')->assertExists('romane/missionmars/005 - NeuerTitel.txt');
    Storage::disk('private')->assertMissing('romane/maddrax/001 - AlterTitel.txt');
});

test('speichern nutzt metadaten fallback wenn erstveroeffentlichungsdatum als leerer string geleert wird', function (): void {
    $this->assertSpeichernNutztMetadatenFallbackBeiGeleertemDatum('');
});

test('speichern nutzt metadaten fallback wenn erstveroeffentlichungsdatum null ist', function (): void {
    $this->assertSpeichernNutztMetadatenFallbackBeiGeleertemDatum(null);
});

test('speichern blockiert duplikat pfad', function (): void {
    KompendiumRoman::create(['dateiname' => '005 - Existiert.txt', 'dateipfad' => 'romane/maddrax/005 - Existiert.txt', 'serie' => 'maddrax', 'roman_nr' => 5, 'titel' => 'Existiert', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    $roman = KompendiumRoman::create(['dateiname' => '001 - Anderer.txt', 'dateipfad' => 'romane/maddrax/001 - Anderer.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Anderer', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('bearbeiten', $roman->id)->set('editNummer', 5)->set('editTitel', 'Existiert')->call('speichern')->assertHasErrors('editTitel');
});

test('speichern reindexiert bei pfadaenderung indexierter romane', function (): void {
    Queue::fake();
    Storage::disk('private')->put('romane/maddrax/001 - Alt.txt', 'Inhalt');
    $roman = KompendiumRoman::create(['dateiname' => '001 - Alt.txt', 'dateipfad' => 'romane/maddrax/001 - Alt.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Alt', 'zyklus' => 'Euree', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexiert', 'indexiert_am' => now()]);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('bearbeiten', $roman->id)->set('editTitel', 'Neu')->call('speichern');
    $roman->refresh();
    // Status wird auf "hochgeladen" zurückgesetzt und Re-Indexierung gestartet
    expect($roman->status)->toEqual('hochgeladen');
    expect($roman->indexiert_am)->toBeNull();
    Queue::assertPushed(IndexiereRomanJob::class);
});

test('standard filter serie ist maddrax', function (): void {
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->assertSet('filterSerie', 'maddrax');
});

test('roman zahlen pro serie computed funktioniert', function (): void {
    KompendiumRoman::create(['dateiname' => '001 - M1.txt', 'dateipfad' => 'romane/maddrax/001 - M1.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'M1', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    KompendiumRoman::create(['dateiname' => '002 - M2.txt', 'dateipfad' => 'romane/maddrax/002 - M2.txt', 'serie' => 'maddrax', 'roman_nr' => 2, 'titel' => 'M2', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexiert']);
    KompendiumRoman::create(['dateiname' => '001 - Mars.txt', 'dateipfad' => 'romane/missionmars/001 - Mars.txt', 'serie' => 'missionmars', 'roman_nr' => 1, 'titel' => 'Mars', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    $component = Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class);
    $zahlen = $component->get('romanZahlenProSerie');
    expect($zahlen['maddrax'])->toEqual(2)
        ->and($zahlen['missionmars'])->toEqual(1);
});

test('speichern sanitized einfache traversal sequenz', function (): void {
    Storage::disk('private')->put('romane/maddrax/001 - Test.txt', 'Inhalt');
    $roman = KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('bearbeiten', $roman->id)->set('editTitel', '../../../etc/passwd')->call('speichern')->assertSet('showEditModal', false);
    $roman->refresh();
    expect($roman->titel)->not->toContain('..')->not->toContain('/')
        ->toEqual('etcpasswd');
});

test('speichern sanitized backslash traversal', function (): void {
    Storage::disk('private')->put('romane/maddrax/001 - Test.txt', 'Inhalt');
    $roman = KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('bearbeiten', $roman->id)->set('editTitel', '..\..\windows\system32')->call('speichern')->assertSet('showEditModal', false);
    $roman->refresh();
    expect($roman->titel)->not->toContain('\\')->not->toContain('..')
        ->toEqual('windowssystem32');
});

test('speichern sanitized verschachtelte traversal muster', function (): void {
    Storage::disk('private')->put('romane/maddrax/001 - Test.txt', 'Inhalt');
    $roman = KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    // "....//"-Muster: nach Entfernung von / bleiben "....", nach iterativer Entfernung von ".." bleibt ""
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('bearbeiten', $roman->id)->set('editTitel', '....//Titel')->call('speichern')->assertSet('showEditModal', false);
    $roman->refresh();
    expect($roman->titel)->not->toContain('..')->not->toContain('/')
        ->toEqual('Titel');
});

test('speichern sanitized gemischte traversal sequenzen', function (): void {
    Storage::disk('private')->put('romane/maddrax/001 - Test.txt', 'Inhalt');
    $roman = KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    // "./../"-Muster
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('bearbeiten', $roman->id)->set('editTitel', './../geheim')->call('speichern')->assertSet('showEditModal', false);
    $roman->refresh();
    expect($roman->titel)->not->toContain('..')->not->toContain('/');
    // "./../geheim" → remove "/" → "...geheim" → remove ".." → ".geheim"
    expect($roman->titel)->toEqual('.geheim');
});

test('speichern lehnt nur sonderzeichen titel ab', function (): void {
    $roman = KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('bearbeiten', $roman->id)->set('editTitel', '../../..')->call('speichern')->assertHasErrors('editTitel');
    // Titel in DB wurde NICHT geändert
    $roman->refresh();
    expect($roman->titel)->toEqual('Test');
});

test('speichern loggt warnung bei fehlender datei', function (): void {
    // Datei bewusst NICHT erstellen → fehlt im Storage
    $roman = KompendiumRoman::create(['dateiname' => '001 - Verschwunden.txt', 'dateipfad' => 'romane/maddrax/001 - Verschwunden.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Verschwunden', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    Log::spy();
    Livewire::actingAs($this->admin)->test(KompendiumAdminDashboard::class)->call('bearbeiten', $roman->id)->set('editTitel', 'NeuerName')->call('speichern')->assertSet('showEditModal', false);
    // DB wurde trotzdem aktualisiert (Titel-Korrektur ist trotzdem sinnvoll)
    $roman->refresh();
    expect($roman->titel)->toEqual('NeuerName');
    Log::shouldHaveReceived('warning')->once()->withArgs(fn ($msg, $ctx = []) => str_contains($msg, 'Datei fehlt') && ($ctx['roman_id'] ?? null) === $roman->id);
});
