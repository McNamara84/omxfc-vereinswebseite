<?php

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\ThreeDModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $model = $this->createModelWithReward(999);

        $response = $this->get("/3d-modelle/{$model->id}");

        $response->assertOk();
        $response->assertSee('999 Baxx');
        $response->assertDontSee('data-three-d-viewer');
    }

    public function test_freigeschaltetes_modell_zeigt_viewer(): void
    {
        $user = $this->actingMemberWithPoints(10);

        $model = $this->createModelWithReward(5);
        $this->purchaseModelForUser($model, $user);

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
            'cost_baxx' => 15,
            'model_file' => $file,
        ]);

        $response->assertRedirect('/3d-modelle');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('three_d_models', [
            'name' => 'Testmodell',
            'description' => 'Ein tolles 3D-Modell',
            'file_format' => 'stl',
        ]);

        // Reward wurde automatisch erstellt
        $model = ThreeDModel::where('name', 'Testmodell')->first();
        $this->assertNotNull($model->reward);
        $this->assertEquals(15, $model->reward->cost_baxx);
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
            'cost_baxx' => 10,
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

        $response->assertSessionHasErrors(['name', 'description', 'cost_baxx', 'model_file']);
    }

    public function test_upload_validierung_baxx_min_1(): void
    {
        Storage::fake('private');

        $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.stl', 512);

        $response = $this->post('/3d-modelle', [
            'name' => 'Test',
            'description' => 'Test',
            'cost_baxx' => 0,
            'model_file' => $file,
        ]);

        $response->assertSessionHasErrors(['cost_baxx']);
    }

    public function test_upload_validierung_ungueltige_maddraxikon_url(): void
    {
        Storage::fake('private');

        $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.stl', 512);

        $response = $this->post('/3d-modelle', [
            'name' => 'Test',
            'description' => 'Test',
            'cost_baxx' => 10,
            'model_file' => $file,
            'maddraxikon_url' => 'keine-url',
        ]);

        $response->assertSessionHasErrors(['maddraxikon_url']);
    }

    public function test_upload_validierung_ungueltiges_dateiformat(): void
    {
        Storage::fake('private');

        $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.zip', 512);

        $response = $this->post('/3d-modelle', [
            'name' => 'Test',
            'description' => 'Test',
            'cost_baxx' => 10,
            'model_file' => $file,
        ]);

        $response->assertSessionHasErrors(['model_file']);
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

        $model = ThreeDModel::factory()->create(['name' => 'Alt']);

        $response = $this->put("/3d-modelle/{$model->id}", [
            'name' => 'Neu',
            'description' => 'Neue Beschreibung',
            'cost_baxx' => 20,
            'maddraxikon_url' => 'https://maddraxikon.de/Neu',
        ]);

        $response->assertRedirect('/3d-modelle');

        $model->refresh();
        $this->assertEquals('Neu', $model->name);
        $this->assertNotNull($model->reward);
        $this->assertEquals(20, $model->reward->cost_baxx);
        $this->assertEquals('https://maddraxikon.de/Neu', $model->maddraxikon_url);
    }

    public function test_admin_kann_modell_mit_neuer_datei_aktualisieren(): void
    {
        Storage::fake('private');

        $this->actingAdmin();

        $model = ThreeDModel::factory()->create([
            'name' => 'Original',
            'file_format' => 'stl',
            'file_size' => 1024,
        ]);

        // Alte Datei anlegen
        Storage::disk('private')->put($model->file_path, 'old-content');
        $oldFilePath = $model->file_path;

        $newFile = UploadedFile::fake()->create('neues-modell.obj', 2048);

        $response = $this->put("/3d-modelle/{$model->id}", [
            'name' => 'Aktualisiert',
            'description' => 'Neue Beschreibung',
            'cost_baxx' => 15,
            'model_file' => $newFile,
        ]);

        $response->assertRedirect('/3d-modelle');

        $model->refresh();
        $this->assertEquals('Aktualisiert', $model->name);
        $this->assertEquals('obj', $model->file_format);
        $this->assertNotEquals($oldFilePath, $model->file_path);

        // Alte Datei wurde gelöscht, neue existiert
        Storage::disk('private')->assertMissing($oldFilePath);
        Storage::disk('private')->assertExists($model->file_path);
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

        // Dateien im Storage anlegen
        Storage::disk('private')->put($model->file_path, 'fake-content');

        $response = $this->delete("/3d-modelle/{$model->id}");

        $response->assertRedirect('/3d-modelle');
        $this->assertDatabaseMissing('three_d_models', ['id' => $model->id]);
        Storage::disk('private')->assertMissing($model->file_path);
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

        $user = $this->actingMemberWithPoints(20);

        $model = $this->createModelWithReward(10);
        $this->purchaseModelForUser($model, $user);

        // Datei im fake Storage erstellen
        Storage::disk('private')->put($model->file_path, 'fake-content');

        $response = $this->get("/3d-modelle/{$model->id}/herunterladen");

        $response->assertOk();
    }

    public function test_download_ohne_genuegend_baxx_wird_abgelehnt(): void
    {
        $this->actingSimpleMember();

        $model = $this->createModelWithReward(50);

        $response = $this->get("/3d-modelle/{$model->id}/herunterladen");

        $response->assertForbidden();
    }

    public function test_download_fehlende_datei_gibt_fehler(): void
    {
        Storage::fake('private');

        $user = $this->actingMemberWithPoints(20);

        $model = $this->createModelWithReward(10);
        $this->purchaseModelForUser($model, $user);

        // Datei wird bewusst NICHT erstellt

        $response = $this->get("/3d-modelle/{$model->id}/herunterladen");

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    // ── Preview (Baxx-geschützt) ──────────────────────────

    public function test_vorschau_mit_genuegend_baxx(): void
    {
        Storage::fake('private');

        $user = $this->actingMemberWithPoints(20);

        $model = $this->createModelWithReward(10);
        $this->purchaseModelForUser($model, $user);

        Storage::disk('private')->put($model->file_path, 'fake-content');

        $response = $this->get("/3d-modelle/{$model->id}/vorschau");

        $response->assertOk();
    }

    public function test_vorschau_ohne_genuegend_baxx_wird_abgelehnt(): void
    {
        $this->actingSimpleMember();

        $model = $this->createModelWithReward(50);

        $response = $this->get("/3d-modelle/{$model->id}/vorschau");

        $response->assertForbidden();
    }

    public function test_vorschau_fehlende_datei_gibt_fehler(): void
    {
        Storage::fake('private');

        $user = $this->actingMemberWithPoints(20);

        $model = $this->createModelWithReward(10);
        $this->purchaseModelForUser($model, $user);

        // Datei wird bewusst NICHT erstellt

        $response = $this->get("/3d-modelle/{$model->id}/vorschau");

        $response->assertNotFound();
    }

    // ── Belohnungen-Integration ─────────────────────────────
    // 3D-Modelle nutzen seit der Umstellung auf das aktive Kaufsystem
    // einen eigenen Reward pro Modell. Der Kauf erfolgt über
    // RewardService::purchaseReward() statt über automatische Baxx-Level-Prüfung.

    public function test_3d_modelle_nutzen_eigenes_zugriffssystem(): void
    {
        $user = $this->actingMemberWithPoints(25);

        ThreeDModel::factory()->create([
            'name' => 'Zugriffs-Modell',
        ]);

        // 3D-Modelle sind über ihre eigene Route erreichbar, nicht über /belohnungen
        $response = $this->get('/3d-modelle');
        $response->assertOk();
        $response->assertSee('Zugriffs-Modell');
    }

    // ── Validierung: Dateigröße ──────────────────────────────

    public function test_upload_validierung_datei_zu_gross(): void
    {
        Storage::fake('private');

        $this->actingAdmin();

        // 100 MB + 1 KB überschreitet das Limit
        $file = UploadedFile::fake()->create('modell.stl', 102401);

        $response = $this->post('/3d-modelle', [
            'name' => 'Zu gro\u00df',
            'description' => 'Test',
            'cost_baxx' => 10,
            'model_file' => $file,
        ]);

        $response->assertSessionHasErrors(['model_file']);
    }

    public function test_upload_validierung_thumbnail_zu_gross(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.stl', 512);
        // 2 MB + 1 KB überschreitet das Thumbnail-Limit
        $thumbnail = UploadedFile::fake()->image('vorschau.jpg')->size(2049);

        $response = $this->post('/3d-modelle', [
            'name' => 'Thumbnail zu gro\u00df',
            'description' => 'Test',
            'cost_baxx' => 10,
            'model_file' => $file,
            'thumbnail' => $thumbnail,
        ]);

        $response->assertSessionHasErrors(['thumbnail']);
    }

    // ── Hilfsmethoden ─────────────────────────────────────────

    /**
     * Erstellt ein 3D-Modell mit zugehörigem Reward.
     */
    private function createModelWithReward(int $costBaxx = 10, array $attributes = []): ThreeDModel
    {
        $model = ThreeDModel::factory()->create($attributes);

        $reward = Reward::create([
            'title' => $model->name,
            'slug' => '3d-' . Str::slug($model->name) . '-' . $model->id,
            'description' => $model->description ?? '',
            'category' => '3D-Modelle',
            'cost_baxx' => $costBaxx,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $model->update(['reward_id' => $reward->id]);

        return $model->refresh();
    }

    /**
     * Erstellt einen RewardPurchase für den User und das Modell.
     */
    private function purchaseModelForUser(ThreeDModel $model, User $user): RewardPurchase
    {
        return RewardPurchase::create([
            'user_id' => $user->id,
            'reward_id' => $model->reward_id,
            'cost_baxx' => $model->reward->cost_baxx,
            'purchased_at' => now(),
        ]);
    }
}
