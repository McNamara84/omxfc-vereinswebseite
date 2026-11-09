<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KompendiumRoman;
use App\Models\Team;
use App\Models\User;
use App\Services\KompendiumSearchService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RebuildKompendiumIndexTestFixtures::class);

beforeEach(function (): void {
    $this->testStoragePath = base_path('storage/testing');
    $this->app->useStoragePath($this->testStoragePath);
    File::ensureDirectoryExists($this->testStoragePath.'/app/private/romane/maddrax');
    File::ensureDirectoryExists($this->testStoragePath.'/framework/views');
    config(['filesystems.disks.private.root' => $this->testStoragePath.'/app/private']);
    config(['scout.driver' => 'null']);
    $team = Team::membersTeam();
    $this->admin = User::factory()->create(['current_team_id' => $team->id]);
});

afterEach(function (): void {
    File::deleteDirectory($this->testStoragePath);
    Mockery::close();
});

test('skips rebuild when index already exists', function (): void {
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('indexExists')->once()->andReturn(true);
    });
    $this->artisan('kompendium:rebuild-index')->expectsOutput('Index existiert bereits – kein Rebuild nötig.')->assertExitCode(0);
});

test('skips rebuild when no indexed romane in database', function (): void {
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('indexExists')->once()->andReturn(false);
    });
    $this->artisan('kompendium:rebuild-index')->expectsOutput('Keine indexierten Romane in der Datenbank gefunden – nichts zu tun.')->assertExitCode(0);
});

test('rebuilds index from indexed romane', function (): void {
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('indexExists')->once()->andReturn(false);
    });
    // Roman-Datei anlegen
    Storage::disk('private')->put('romane/maddrax/001 - Der Gott aus dem Eis.txt', 'Dies ist ein Testinhalt für den Roman.');
    // DB-Eintrag als "indexiert" markieren
    KompendiumRoman::create(['dateiname' => '001 - Der Gott aus dem Eis.txt', 'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Der Gott aus dem Eis', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexiert']);
    $this->artisan('kompendium:rebuild-index')->expectsOutputToContain('Index fehlt – baue 1 Roman neu auf')->expectsOutput('Index-Rebuild abgeschlossen.')->assertExitCode(0);
});

test('resume reimports into an existing index without deleting it', function (): void {
    $this->partialMock(KompendiumSearchService::class, function ($mock): void {
        $mock->shouldReceive('indexExists')->twice()->andReturn(true);
        $mock->shouldNotReceive('deleteIndex');
    });
    Storage::disk('private')->put('romane/maddrax/001 - Eis.txt', 'Matthew Drax findet das Eis.');
    $roman = KompendiumRoman::create(['dateiname' => '001 - Eis.txt', 'dateipfad' => 'romane/maddrax/001 - Eis.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Eis', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexiert']);
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $this->artisan('kompendium:rebuild-index --resume')->expectsOutputToContain('Setze Index-Import fort')->expectsOutput('Index-Rebuild abgeschlossen.')->assertSuccessful();
    }
    expect($roman->fresh()->status)->toBe('indexiert')
        ->and(KompendiumRoman::count())->toBe(1)
        ->and(Storage::disk('private')->get($roman->dateipfad))->toBe('Matthew Drax findet das Eis.');
});

test('marks roman as fehler when file missing', function (): void {
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('indexExists')->once()->andReturn(false);
    });
    // DB-Eintrag ohne zugehörige Datei
    $roman = KompendiumRoman::create(['dateiname' => '999 - Fehlender Roman.txt', 'dateipfad' => 'romane/maddrax/999 - Fehlender Roman.txt', 'serie' => 'maddrax', 'roman_nr' => 999, 'titel' => 'Fehlender Roman', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'indexiert']);
    $this->artisan('kompendium:rebuild-index')->expectsOutputToContain('Datei nicht gefunden')->assertExitCode(1);
    $roman->refresh();
    expect($roman->status)->toEqual('fehler')
        ->and($roman->fehler_nachricht)->toContain('Datei nicht gefunden');
});

test('ignores non indexed romane', function (): void {
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('indexExists')->once()->andReturn(false);
    });
    // Roman mit Status "hochgeladen" sollte nicht rebuildet werden
    KompendiumRoman::create(['dateiname' => '001 - Hochgeladener Roman.txt', 'dateipfad' => 'romane/maddrax/001 - Hochgeladener Roman.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Hochgeladener Roman', 'hochgeladen_am' => now(), 'hochgeladen_von' => $this->admin->id, 'status' => 'hochgeladen']);
    $this->artisan('kompendium:rebuild-index')->expectsOutput('Keine indexierten Romane in der Datenbank gefunden – nichts zu tun.')->assertExitCode(0);
});
