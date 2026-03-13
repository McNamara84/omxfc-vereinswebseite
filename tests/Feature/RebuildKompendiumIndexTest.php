<?php

namespace Tests\Feature;

use App\Models\KompendiumRoman;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class RebuildKompendiumIndexTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath.'/app/private/romane/maddrax');
        File::ensureDirectoryExists($this->testStoragePath.'/framework/views');
        config(['filesystems.disks.private.root' => $this->testStoragePath.'/app/private']);
        config(['scout.driver' => 'null']);
        config(['scout.tntsearch.storage' => $this->testStoragePath.'/app']);

        $team = Team::membersTeam();
        $this->admin = User::factory()->create(['current_team_id' => $team->id]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);
        Mockery::close();
        parent::tearDown();
    }

    public function test_skips_rebuild_when_index_already_exists(): void
    {
        // Index-Datei simulieren
        file_put_contents($this->testStoragePath.'/app/roman_excerpts.index', 'fake-index');

        $this->artisan('kompendium:rebuild-index')
            ->expectsOutput('Index existiert bereits – kein Rebuild nötig.')
            ->assertExitCode(0);
    }

    public function test_skips_rebuild_when_no_indexed_romane_in_database(): void
    {
        $this->artisan('kompendium:rebuild-index')
            ->expectsOutput('Keine indexierten Romane in der Datenbank gefunden – nichts zu tun.')
            ->assertExitCode(0);
    }

    public function test_rebuilds_index_from_indexed_romane(): void
    {
        // Roman-Datei anlegen
        Storage::disk('private')->put(
            'romane/maddrax/001 - Der Gott aus dem Eis.txt',
            'Dies ist ein Testinhalt für den Roman.'
        );

        // DB-Eintrag als "indexiert" markieren
        KompendiumRoman::create([
            'dateiname' => '001 - Der Gott aus dem Eis.txt',
            'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Der Gott aus dem Eis',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'indexiert',
        ]);

        $this->artisan('kompendium:rebuild-index')
            ->expectsOutputToContain('Index fehlt – baue 1 Romane neu auf')
            ->expectsOutput('Index-Rebuild abgeschlossen.')
            ->assertExitCode(0);
    }

    public function test_marks_roman_as_fehler_when_file_missing(): void
    {
        // DB-Eintrag ohne zugehörige Datei
        $roman = KompendiumRoman::create([
            'dateiname' => '999 - Fehlender Roman.txt',
            'dateipfad' => 'romane/maddrax/999 - Fehlender Roman.txt',
            'serie' => 'maddrax',
            'roman_nr' => 999,
            'titel' => 'Fehlender Roman',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'indexiert',
        ]);

        $this->artisan('kompendium:rebuild-index')
            ->expectsOutputToContain('Datei nicht gefunden')
            ->assertExitCode(0);

        $roman->refresh();
        $this->assertEquals('fehler', $roman->status);
        $this->assertStringContainsString('Datei nicht gefunden', $roman->fehler_nachricht);
    }

    public function test_ignores_non_indexed_romane(): void
    {
        // Roman mit Status "hochgeladen" sollte nicht rebuildet werden
        KompendiumRoman::create([
            'dateiname' => '001 - Hochgeladener Roman.txt',
            'dateipfad' => 'romane/maddrax/001 - Hochgeladener Roman.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Hochgeladener Roman',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'hochgeladen',
        ]);

        $this->artisan('kompendium:rebuild-index')
            ->expectsOutput('Keine indexierten Romane in der Datenbank gefunden – nichts zu tun.')
            ->assertExitCode(0);
    }
}
