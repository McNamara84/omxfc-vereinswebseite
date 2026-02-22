<?php

namespace Tests\Feature;

use App\Models\ThreeDModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class ThreeDModelTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    // ── Index ──────────────────────────────────────────────────

    public function test_mitglieder_sehen_3d_modelle_uebersicht(): void
    {
        $user = $this->actingSimpleMember();

        ThreeDModel::factory()->create(['name' => 'Euphoriewurm']);

        $response = $this->get('/3d-modelle');

        $response->assertOk();
        $response->assertSee('Euphoriewurm');
        $response->assertSee('3D-Modelle');
    }

    public function test_gaeste_koennen_3d_modelle_nicht_sehen(): void
    {
        $response = $this->get('/3d-modelle');

        $response->assertRedirect('/login');
    }

    // ── Show ──────────────────────────────────────────────────

    public function test_detailseite_zeigt_modell_informationen(): void
    {
        $user = $this->actingSimpleMember();

        $model = ThreeDModel::factory()->create([
            'name' => 'Testmodell',
            'description' => 'Beschreibung',
            'required_baxx' => 5,
            'maddraxikon_url' => 'https://maddraxikon.de/Testmodell',
        ]);

        $response = $this->get("/3d-modelle/{$model->id}");

        $response->assertOk();
        $response->assertSee('Testmodell');
        $response->assertSee('Beschreibung');
        $response->assertSee('https://maddraxikon.de/Testmodell');
        $response->assertSee('Im Maddraxikon ansehen');
    }

    public function test_detailseite_ohne_maddraxikon_link(): void
    {
        $user = $this->actingSimpleMember();

        $model = ThreeDModel::factory()->create([
            'name' => 'Ohne Link',
            'maddraxikon_url' => null,
        ]);

        $response = $this->get("/3d-modelle/{$model->id}");

        $response->assertOk();
        $response->assertDontSee('Im Maddraxikon ansehen');
    }

    public function test_gesperrtes_modell_zeigt_lock_hinweis(): void
    {
        $user = $this->actingSimpleMember();

        $model = ThreeDModel::factory()->create(['required_baxx' => 999]);

        $response = $this->get("/3d-modelle/{$model->id}");

        $response->assertOk();
        $response->assertSee('999 Baxx');
        $response->assertDontSee('data-three-d-viewer');
    }

    public function test_freigeschaltetes_modell_zeigt_viewer(): void
    {
        $user = $this->actingMemberWithPoints(10);

        $model = ThreeDModel::factory()->create(['required_baxx' => 5]);

        $response = $this->withoutVite()->get("/3d-modelle/{$model->id}");

        $response->assertOk();
        $response->assertSee('data-three-d-viewer');
        $response->assertSee('Herunterladen');
    }

    // ── Create / Store (Admin/Vorstand) ──────────────────────

    public function test_admin_kann_upload_formular_sehen(): void
    {
        $this->actingAdmin();

        $response = $this->get('/3d-modelle/erstellen');

        $response->assertOk();
        $response->assertSee('3D-Modell hochladen');
    }

    public function test_vorstand_kann_upload_formular_sehen(): void
    {
        $this->actingVorstand();

        $response = $this->get('/3d-modelle/erstellen');

        $response->assertOk();
    }

    public function test_normales_mitglied_kann_nicht_hochladen(): void
    {
        $this->actingSimpleMember();

        $response = $this->get('/3d-modelle/erstellen');

        $response->assertForbidden();
    }

    public function test_admin_kann_3d_modell_hochladen(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.stl', 1024);

        $response = $this->post('/3d-modelle', [
            'name' => 'Testmodell',
            'description' => 'Ein tolles 3D-Modell',
            'required_baxx' => 15,
            'model_file' => $file,
        ]);

        $response->assertRedirect('/3d-modelle');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('three_d_models', [
            'name' => 'Testmodell',
            'description' => 'Ein tolles 3D-Modell',
            'required_baxx' => 15,
            'file_format' => 'stl',
        ]);
    }

    public function test_admin_kann_3d_modell_mit_thumbnail_hochladen(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.obj', 2048);
        $thumbnail = UploadedFile::fake()->image('vorschau.jpg', 400, 300);

        $response = $this->post('/3d-modelle', [
            'name' => 'OBJ-Modell',
            'description' => 'Ein OBJ-Modell mit Vorschaubild',
            'required_baxx' => 10,
            'maddraxikon_url' => 'https://maddraxikon.de/Euphoriewurm',
            'model_file' => $file,
            'thumbnail' => $thumbnail,
        ]);

        $response->assertRedirect('/3d-modelle');

        $model = ThreeDModel::where('name', 'OBJ-Modell')->first();
        $this->assertNotNull($model);
        $this->assertNotNull($model->thumbnail_path);
        $this->assertEquals('obj', $model->file_format);
        $this->assertEquals('https://maddraxikon.de/Euphoriewurm', $model->maddraxikon_url);
    }

    public function test_upload_validierung_fehlende_pflichtfelder(): void
    {
        $this->actingAdmin();

        $response = $this->post('/3d-modelle', []);

        $response->assertSessionHasErrors(['name', 'description', 'required_baxx', 'model_file']);
    }

    public function test_upload_validierung_baxx_min_1(): void
    {
        Storage::fake('private');

        $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.stl', 512);

        $response = $this->post('/3d-modelle', [
            'name' => 'Test',
            'description' => 'Test',
            'required_baxx' => 0,
            'model_file' => $file,
        ]);

        $response->assertSessionHasErrors(['required_baxx']);
    }

    public function test_upload_validierung_ungueltige_maddraxikon_url(): void
    {
        Storage::fake('private');

        $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.stl', 512);

        $response = $this->post('/3d-modelle', [
            'name' => 'Test',
            'description' => 'Test',
            'required_baxx' => 10,
            'model_file' => $file,
            'maddraxikon_url' => 'keine-url',
        ]);

        $response->assertSessionHasErrors(['maddraxikon_url']);
    }

    // ── Edit / Update (Admin/Vorstand) ──────────────────────

    public function test_admin_kann_modell_bearbeiten(): void
    {
        $this->actingAdmin();

        $model = ThreeDModel::factory()->create(['name' => 'Alt']);

        $response = $this->get("/3d-modelle/{$model->id}/bearbeiten");

        $response->assertOk();
        $response->assertSee('Alt');
    }

    public function test_admin_kann_modell_aktualisieren(): void
    {
        Storage::fake('private');

        $this->actingAdmin();

        $model = ThreeDModel::factory()->create(['name' => 'Alt', 'required_baxx' => 5]);

        $response = $this->put("/3d-modelle/{$model->id}", [
            'name' => 'Neu',
            'description' => 'Neue Beschreibung',
            'required_baxx' => 20,
            'maddraxikon_url' => 'https://maddraxikon.de/Neu',
        ]);

        $response->assertRedirect('/3d-modelle');

        $model->refresh();
        $this->assertEquals('Neu', $model->name);
        $this->assertEquals(20, $model->required_baxx);
        $this->assertEquals('https://maddraxikon.de/Neu', $model->maddraxikon_url);
    }

    public function test_normales_mitglied_kann_nicht_bearbeiten(): void
    {
        $this->actingSimpleMember();

        $model = ThreeDModel::factory()->create();

        $response = $this->get("/3d-modelle/{$model->id}/bearbeiten");

        $response->assertForbidden();
    }

    // ── Delete (Admin/Vorstand) ─────────────────────────────

    public function test_admin_kann_modell_loeschen(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $this->actingAdmin();

        $model = ThreeDModel::factory()->create();

        $response = $this->delete("/3d-modelle/{$model->id}");

        $response->assertRedirect('/3d-modelle');
        $this->assertDatabaseMissing('three_d_models', ['id' => $model->id]);
    }

    public function test_normales_mitglied_kann_nicht_loeschen(): void
    {
        $this->actingSimpleMember();

        $model = ThreeDModel::factory()->create();

        $response = $this->delete("/3d-modelle/{$model->id}");

        $response->assertForbidden();
    }

    // ── Download (Baxx-geschützt) ──────────────────────────

    public function test_download_mit_genuegend_baxx(): void
    {
        Storage::fake('private');

        $this->actingMemberWithPoints(20);

        $model = ThreeDModel::factory()->create(['required_baxx' => 10]);

        // Datei im fake Storage erstellen
        Storage::disk('private')->put($model->file_path, 'fake-content');

        $response = $this->get("/3d-modelle/{$model->id}/herunterladen");

        $response->assertOk();
    }

    public function test_download_ohne_genuegend_baxx_wird_abgelehnt(): void
    {
        $this->actingMemberWithPoints(2);

        $model = ThreeDModel::factory()->create(['required_baxx' => 50]);

        $response = $this->get("/3d-modelle/{$model->id}/herunterladen");

        $response->assertForbidden();
    }

    // ── Preview (Baxx-geschützt) ──────────────────────────

    public function test_vorschau_mit_genuegend_baxx(): void
    {
        Storage::fake('private');

        $this->actingMemberWithPoints(20);

        $model = ThreeDModel::factory()->create(['required_baxx' => 10]);

        Storage::disk('private')->put($model->file_path, 'fake-content');

        $response = $this->get("/3d-modelle/{$model->id}/vorschau");

        $response->assertOk();
    }

    public function test_vorschau_ohne_genuegend_baxx_wird_abgelehnt(): void
    {
        $this->actingMemberWithPoints(2);

        $model = ThreeDModel::factory()->create(['required_baxx' => 50]);

        $response = $this->get("/3d-modelle/{$model->id}/vorschau");

        $response->assertForbidden();
    }

    // ── Belohnungen-Integration ─────────────────────────────

    public function test_belohnungen_seite_zeigt_3d_modelle(): void
    {
        $this->actingSimpleMember();

        ThreeDModel::factory()->create([
            'name' => 'Belohnungs-Modell',
            'required_baxx' => 25,
        ]);

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $response->assertSee('3D-Modell - Belohnungs-Modell');
        $response->assertSee('25 Baxx');
    }
}
