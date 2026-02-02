<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\DeIndexiereRomanJob;
use App\Jobs\IndexiereRomanJob;
use App\Livewire\KompendiumAdminDashboard;
use App\Models\KompendiumRoman;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(KompendiumAdminDashboard::class)]
class KompendiumAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        // Admin-User erstellen (mit membersTeam)
        $team = Team::membersTeam();
        $this->admin = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($this->admin, ['role' => Role::Admin->value]);

        // Normaler User
        $this->normalUser = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($this->normalUser, ['role' => Role::Mitglied->value]);
    }

    #[Test]
    public function admin_kann_kompendium_admin_seite_aufrufen(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('kompendium.admin'));

        $response->assertStatus(200);
        $response->assertSeeLivewire(KompendiumAdminDashboard::class);
    }

    #[Test]
    public function nicht_admin_kann_kompendium_admin_seite_nicht_aufrufen(): void
    {
        $response = $this->actingAs($this->normalUser)
            ->get(route('kompendium.admin'));

        $response->assertStatus(403);
    }

    #[Test]
    public function gast_wird_zur_login_seite_weitergeleitet(): void
    {
        $response = $this->get(route('kompendium.admin'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_kann_txt_datei_hochladen(): void
    {
        $file = UploadedFile::fake()->create('001 - Der Gott aus dem Eis.txt', 100, 'text/plain');

        Livewire::actingAs($this->admin)
            ->test(KompendiumAdminDashboard::class)
            ->set('uploads', [$file])
            ->set('ausgewaehlteSerie', 'maddrax')
            ->call('hochladen')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('kompendium_romane', [
            'dateiname' => '001 - Der Gott aus dem Eis.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Der Gott aus dem Eis',
            'status' => 'hochgeladen',
        ]);

        Storage::disk('private')->assertExists('romane/maddrax/001 - Der Gott aus dem Eis.txt');
    }

    #[Test]
    public function upload_mit_ungueltigem_dateinamen_schlaegt_fehl(): void
    {
        $file = UploadedFile::fake()->create('Ungültiger Name.txt', 100, 'text/plain');

        Livewire::actingAs($this->admin)
            ->test(KompendiumAdminDashboard::class)
            ->set('uploads', [$file])
            ->set('ausgewaehlteSerie', 'maddrax')
            ->call('hochladen');

        $this->assertDatabaseMissing('kompendium_romane', [
            'dateiname' => 'Ungültiger Name.txt',
        ]);
    }

    #[Test]
    public function upload_von_duplikat_wird_abgelehnt(): void
    {
        // Ersten Upload
        KompendiumRoman::create([
            'dateiname' => '001 - Test.txt',
            'dateipfad' => 'romane/maddrax/001 - Test.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'hochgeladen',
        ]);

        $file = UploadedFile::fake()->create('001 - Test.txt', 100, 'text/plain');

        Livewire::actingAs($this->admin)
            ->test(KompendiumAdminDashboard::class)
            ->set('uploads', [$file])
            ->set('ausgewaehlteSerie', 'maddrax')
            ->call('hochladen');

        // Es sollte nur ein Eintrag existieren
        $this->assertEquals(1, KompendiumRoman::where('roman_nr', 1)->count());
    }

    #[Test]
    public function indexieren_dispatched_job(): void
    {
        Queue::fake();

        $roman = KompendiumRoman::create([
            'dateiname' => '001 - Test.txt',
            'dateipfad' => 'romane/maddrax/001 - Test.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'hochgeladen',
        ]);

        Livewire::actingAs($this->admin)
            ->test(KompendiumAdminDashboard::class)
            ->call('indexieren', $roman->id);

        Queue::assertPushed(IndexiereRomanJob::class, function ($job) use ($roman) {
            return $job->kompendiumRoman->id === $roman->id;
        });
    }

    #[Test]
    public function de_indexieren_dispatched_job(): void
    {
        Queue::fake();

        $roman = KompendiumRoman::create([
            'dateiname' => '001 - Test.txt',
            'dateipfad' => 'romane/maddrax/001 - Test.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'indexiert',
            'indexiert_am' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(KompendiumAdminDashboard::class)
            ->call('deIndexieren', $roman->id);

        Queue::assertPushed(DeIndexiereRomanJob::class, function ($job) use ($roman) {
            return $job->kompendiumRoman->id === $roman->id;
        });
    }

    #[Test]
    public function loeschen_entfernt_datei_und_datenbankeintrag(): void
    {
        Storage::disk('private')->put('romane/maddrax/001 - Test.txt', 'Inhalt');

        $roman = KompendiumRoman::create([
            'dateiname' => '001 - Test.txt',
            'dateipfad' => 'romane/maddrax/001 - Test.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'hochgeladen',
        ]);

        Livewire::actingAs($this->admin)
            ->test(KompendiumAdminDashboard::class)
            ->call('loeschen', $roman->id);

        $this->assertDatabaseMissing('kompendium_romane', ['id' => $roman->id]);
        Storage::disk('private')->assertMissing('romane/maddrax/001 - Test.txt');
    }

    #[Test]
    public function loeschen_indexierter_roman_entfernt_aus_index_und_loescht_datei(): void
    {
        Storage::disk('private')->put('romane/maddrax/001 - Indexiert.txt', 'Inhalt');

        $roman = KompendiumRoman::create([
            'dateiname' => '001 - Indexiert.txt',
            'dateipfad' => 'romane/maddrax/001 - Indexiert.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Indexiert',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'indexiert',
            'indexiert_am' => now(),
        ]);

        // Löschen sollte nicht fehlschlagen, auch wenn der Index nicht existiert
        Livewire::actingAs($this->admin)
            ->test(KompendiumAdminDashboard::class)
            ->call('loeschen', $roman->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('kompendium_romane', ['id' => $roman->id]);
        Storage::disk('private')->assertMissing('romane/maddrax/001 - Indexiert.txt');
    }

    #[Test]
    public function alle_indexieren_dispatched_jobs_fuer_alle_hochgeladenen(): void
    {
        Queue::fake();

        // 3 hochgeladene, 1 bereits indexierter Roman
        foreach ([1, 2, 3] as $nr) {
            KompendiumRoman::create([
                'dateiname' => str_pad($nr, 3, '0', STR_PAD_LEFT)." - Test{$nr}.txt",
                'dateipfad' => 'romane/maddrax/'.str_pad($nr, 3, '0', STR_PAD_LEFT)." - Test{$nr}.txt",
                'serie' => 'maddrax',
                'roman_nr' => $nr,
                'titel' => "Test{$nr}",
                'hochgeladen_am' => now(),
                'hochgeladen_von' => $this->admin->id,
                'status' => 'hochgeladen',
            ]);
        }

        KompendiumRoman::create([
            'dateiname' => '004 - Test4.txt',
            'dateipfad' => 'romane/maddrax/004 - Test4.txt',
            'serie' => 'maddrax',
            'roman_nr' => 4,
            'titel' => 'Test4',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'indexiert',
        ]);

        Livewire::actingAs($this->admin)
            ->test(KompendiumAdminDashboard::class)
            ->call('alleIndexieren');

        Queue::assertPushed(IndexiereRomanJob::class, 3);
    }

    #[Test]
    public function filter_nach_serie_funktioniert(): void
    {
        KompendiumRoman::create([
            'dateiname' => '001 - HauptserieRoman.txt',
            'dateipfad' => 'romane/maddrax/001 - HauptserieRoman.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'HauptserieRoman',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'hochgeladen',
        ]);

        KompendiumRoman::create([
            'dateiname' => '001 - MarsRoman.txt',
            'dateipfad' => 'romane/missionmars/001 - MarsRoman.txt',
            'serie' => 'missionmars',
            'roman_nr' => 1,
            'titel' => 'MarsRoman',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'hochgeladen',
        ]);

        Livewire::actingAs($this->admin)
            ->test(KompendiumAdminDashboard::class)
            ->set('filterSerie', 'missionmars')
            ->assertSee('MarsRoman')
            ->assertDontSee('HauptserieRoman');
    }

    #[Test]
    public function filter_nach_status_funktioniert(): void
    {
        KompendiumRoman::create([
            'dateiname' => '001 - NurHochgeladen.txt',
            'dateipfad' => 'romane/maddrax/001 - NurHochgeladen.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'NurHochgeladen',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'hochgeladen',
        ]);

        KompendiumRoman::create([
            'dateiname' => '002 - NurIndexiert.txt',
            'dateipfad' => 'romane/maddrax/002 - NurIndexiert.txt',
            'serie' => 'maddrax',
            'roman_nr' => 2,
            'titel' => 'NurIndexiert',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'indexiert',
        ]);

        Livewire::actingAs($this->admin)
            ->test(KompendiumAdminDashboard::class)
            ->set('filterStatus', 'indexiert')
            ->assertSee('NurIndexiert')
            ->assertDontSee('NurHochgeladen');
    }

    #[Test]
    public function statistiken_werden_korrekt_angezeigt(): void
    {
        KompendiumRoman::create([
            'dateiname' => '001 - Test.txt',
            'dateipfad' => 'romane/maddrax/001 - Test.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'indexiert',
        ]);

        Livewire::actingAs($this->admin)
            ->test(KompendiumAdminDashboard::class)
            ->assertSee('1') // Gesamt
            ->assertSee('Indexiert');
    }

    #[Test]
    public function retry_fehler_setzt_status_zurueck_und_dispatched_job(): void
    {
        Queue::fake();

        $roman = KompendiumRoman::create([
            'dateiname' => '001 - Test.txt',
            'dateipfad' => 'romane/maddrax/001 - Test.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $this->admin->id,
            'status' => 'fehler',
            'fehler_nachricht' => 'Testfehler',
        ]);

        Livewire::actingAs($this->admin)
            ->test(KompendiumAdminDashboard::class)
            ->call('retryFehler', $roman->id);

        Queue::assertPushed(IndexiereRomanJob::class);

        $roman->refresh();
        $this->assertEquals('hochgeladen', $roman->status);
        $this->assertNull($roman->fehler_nachricht);
    }
}
