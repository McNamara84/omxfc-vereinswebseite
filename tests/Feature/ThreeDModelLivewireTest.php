<?php

namespace Tests\Feature;

use App\Livewire\ThreeDModelForm;
use App\Livewire\ThreeDModelShow;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\ThreeDModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Large;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

#[Large]
class ThreeDModelLivewireTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    // ── Index ──────────────────────────────────────────────────

    public function test_mitglieder_sehen_3d_modelle_uebersicht(): void
    {
        $this->actingSimpleMember();

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
        $this->actingSimpleMember();

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
        $this->actingSimpleMember();

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
        $this->actingSimpleMember();

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

    // ── Create Form ──────────────────────────────────────────

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
        $user = $this->actingSimpleMember();

        Livewire::actingAs($user)
            ->test(ThreeDModelForm::class)
            ->assertForbidden();
    }

    // ── Store (Livewire) ─────────────────────────────────────

    public function test_admin_kann_3d_modell_hochladen(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $user = $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.stl', 1024);

        Livewire::actingAs($user)
            ->test(ThreeDModelForm::class)
            ->set('name', 'Testmodell')
            ->set('description', 'Ein tolles 3D-Modell')
            ->set('cost_baxx', 15)
            ->set('model_file', $file)
            ->call('save')
            ->assertRedirect(route('3d-modelle.index'));

        $this->assertDatabaseHas('three_d_models', [
            'name' => 'Testmodell',
            'description' => 'Ein tolles 3D-Modell',
            'file_format' => 'stl',
        ]);

        $model = ThreeDModel::where('name', 'Testmodell')->first();
        $this->assertNotNull($model->reward);
        $this->assertEquals(15, $model->reward->cost_baxx);
    }

    public function test_admin_kann_3d_modell_mit_thumbnail_hochladen(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $user = $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.obj', 2048);
        $thumbnail = UploadedFile::fake()->image('vorschau.jpg', 400, 300);

        Livewire::actingAs($user)
            ->test(ThreeDModelForm::class)
            ->set('name', 'OBJ-Modell')
            ->set('description', 'Ein OBJ-Modell mit Vorschaubild')
            ->set('cost_baxx', 10)
            ->set('maddraxikon_url', 'https://maddraxikon.de/Euphoriewurm')
            ->set('model_file', $file)
            ->set('thumbnail', $thumbnail)
            ->call('save')
            ->assertRedirect(route('3d-modelle.index'));

        $model = ThreeDModel::where('name', 'OBJ-Modell')->first();
        $this->assertNotNull($model);
        $this->assertNotNull($model->thumbnail_path);
        $this->assertEquals('obj', $model->file_format);
        $this->assertEquals('https://maddraxikon.de/Euphoriewurm', $model->maddraxikon_url);
    }

    public function test_upload_validierung_fehlende_pflichtfelder(): void
    {
        $user = $this->actingAdmin();

        Livewire::actingAs($user)
            ->test(ThreeDModelForm::class)
            ->set('name', '')
            ->set('description', '')
            ->set('cost_baxx', 0)
            ->call('save')
            ->assertHasErrors(['name', 'description', 'cost_baxx', 'model_file']);
    }

    public function test_upload_validierung_baxx_min_1(): void
    {
        Storage::fake('private');

        $user = $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.stl', 512);

        Livewire::actingAs($user)
            ->test(ThreeDModelForm::class)
            ->set('name', 'Test')
            ->set('description', 'Test')
            ->set('cost_baxx', 0)
            ->set('model_file', $file)
            ->call('save')
            ->assertHasErrors(['cost_baxx']);
    }

    public function test_upload_validierung_ungueltige_maddraxikon_url(): void
    {
        Storage::fake('private');

        $user = $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.stl', 512);

        Livewire::actingAs($user)
            ->test(ThreeDModelForm::class)
            ->set('name', 'Test')
            ->set('description', 'Test')
            ->set('cost_baxx', 10)
            ->set('model_file', $file)
            ->set('maddraxikon_url', 'keine-url')
            ->call('save')
            ->assertHasErrors(['maddraxikon_url']);
    }

    public function test_upload_validierung_ungueltiges_dateiformat(): void
    {
        Storage::fake('private');

        $user = $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.zip', 512);

        Livewire::actingAs($user)
            ->test(ThreeDModelForm::class)
            ->set('name', 'Test')
            ->set('description', 'Test')
            ->set('cost_baxx', 10)
            ->set('model_file', $file)
            ->call('save')
            ->assertHasErrors(['model_file']);
    }

    // ── Edit / Update (Livewire) ─────────────────────────────

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

        $user = $this->actingAdmin();

        $model = ThreeDModel::factory()->create(['name' => 'Alt']);

        Livewire::actingAs($user)
            ->test(ThreeDModelForm::class, ['threeDModel' => $model])
            ->set('name', 'Neu')
            ->set('description', 'Neue Beschreibung')
            ->set('cost_baxx', 20)
            ->set('maddraxikon_url', 'https://maddraxikon.de/Neu')
            ->call('save')
            ->assertRedirect(route('3d-modelle.index'));

        $model->refresh();
        $this->assertEquals('Neu', $model->name);
        $this->assertNotNull($model->reward);
        $this->assertEquals(20, $model->reward->cost_baxx);
        $this->assertEquals('https://maddraxikon.de/Neu', $model->maddraxikon_url);
    }

    public function test_admin_kann_modell_mit_neuer_datei_aktualisieren(): void
    {
        Storage::fake('private');

        $user = $this->actingAdmin();

        $model = ThreeDModel::factory()->create([
            'name' => 'Original',
            'file_format' => 'stl',
            'file_size' => 1024,
        ]);

        Storage::disk('private')->put($model->file_path, 'old-content');
        $oldFilePath = $model->file_path;

        $newFile = UploadedFile::fake()->create('neues-modell.obj', 2048);

        Livewire::actingAs($user)
            ->test(ThreeDModelForm::class, ['threeDModel' => $model])
            ->set('name', 'Aktualisiert')
            ->set('description', 'Neue Beschreibung')
            ->set('cost_baxx', 15)
            ->set('model_file', $newFile)
            ->call('save')
            ->assertRedirect(route('3d-modelle.index'));

        $model->refresh();
        $this->assertEquals('Aktualisiert', $model->name);
        $this->assertEquals('obj', $model->file_format);
        $this->assertNotEquals($oldFilePath, $model->file_path);

        Storage::disk('private')->assertMissing($oldFilePath);
        Storage::disk('private')->assertExists($model->file_path);
    }

    public function test_normales_mitglied_kann_nicht_bearbeiten(): void
    {
        $user = $this->actingSimpleMember();

        $model = ThreeDModel::factory()->create();

        Livewire::actingAs($user)
            ->test(ThreeDModelForm::class, ['threeDModel' => $model])
            ->assertForbidden();
    }

    // ── Delete (Livewire) ───────────────────────────────────

    public function test_admin_kann_modell_loeschen(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $user = $this->actingAdmin();

        $model = ThreeDModel::factory()->create();

        Storage::disk('private')->put($model->file_path, 'fake-content');

        Livewire::actingAs($user)
            ->test(ThreeDModelShow::class, ['threeDModel' => $model])
            ->call('deleteModel')
            ->assertRedirect(route('3d-modelle.index'));

        $this->assertDatabaseMissing('three_d_models', ['id' => $model->id]);
        Storage::disk('private')->assertMissing($model->file_path);
    }

    public function test_normales_mitglied_kann_nicht_loeschen(): void
    {
        $user = $this->actingSimpleMember();

        $model = ThreeDModel::factory()->create();

        Livewire::actingAs($user)
            ->test(ThreeDModelShow::class, ['threeDModel' => $model])
            ->call('deleteModel')
            ->assertForbidden();

        $this->assertDatabaseHas('three_d_models', ['id' => $model->id]);
    }

    // ── Download (Controller routes, unchanged) ──────────────

    public function test_download_mit_genuegend_baxx(): void
    {
        Storage::fake('private');

        $user = $this->actingMemberWithPoints(20);

        $model = $this->createModelWithReward(10);
        $this->purchaseModelForUser($model, $user);

        Storage::disk('private')->put($model->file_path, 'fake-content');

        $response = $this->get("/3d-modelle/{$model->id}/herunterladen");

        $response->assertOk();
    }

    public function test_download_ohne_genuegend_baxx_wird_abgelehnt(): void
    {
        $this->actingSimpleMember();

        $model = $this->createModelWithReward(50);

        $response = $this->get("/3d-modelle/{$model->id}/herunterladen");

        $response->assertRedirect(route('3d-modelle.show', $model));
        $response->assertSessionHasErrors('reward');
    }

    public function test_download_fehlende_datei_gibt_fehler(): void
    {
        Storage::fake('private');

        $user = $this->actingMemberWithPoints(20);

        $model = $this->createModelWithReward(10);
        $this->purchaseModelForUser($model, $user);

        $response = $this->get("/3d-modelle/{$model->id}/herunterladen");

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    // ── Preview (Controller routes, unchanged) ───────────────

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

        $response->assertRedirect(route('3d-modelle.show', $model));
        $response->assertSessionHasErrors('reward');
    }

    public function test_vorschau_fehlende_datei_gibt_fehler(): void
    {
        Storage::fake('private');

        $user = $this->actingMemberWithPoints(20);

        $model = $this->createModelWithReward(10);
        $this->purchaseModelForUser($model, $user);

        $response = $this->get("/3d-modelle/{$model->id}/vorschau");

        $response->assertNotFound();
    }

    // ── Purchase (Livewire) ──────────────────────────────────

    public function test_kauf_erfolgreich_mit_genuegend_baxx(): void
    {
        $user = $this->actingMemberWithPoints(20);

        $model = $this->createModelWithReward(10);

        Livewire::actingAs($user)
            ->test(ThreeDModelShow::class, ['threeDModel' => $model])
            ->call('purchase')
            ->assertRedirect(route('3d-modelle.show', $model));

        $this->assertDatabaseHas('reward_purchases', [
            'user_id' => $user->id,
            'reward_id' => $model->reward_id,
            'cost_baxx' => 10,
        ]);
    }

    public function test_kauf_mit_zu_wenig_baxx_schlaegt_fehl(): void
    {
        $user = $this->actingMemberWithPoints(5);

        $model = $this->createModelWithReward(50);

        Livewire::actingAs($user)
            ->test(ThreeDModelShow::class, ['threeDModel' => $model])
            ->call('purchase')
            ->assertHasErrors(['reward']);

        $this->assertDatabaseMissing('reward_purchases', [
            'reward_id' => $model->reward_id,
        ]);
    }

    public function test_doppelkauf_wird_abgelehnt(): void
    {
        $user = $this->actingMemberWithPoints(100);

        $model = $this->createModelWithReward(10);
        $this->purchaseModelForUser($model, $user);

        Livewire::actingAs($user)
            ->test(ThreeDModelShow::class, ['threeDModel' => $model])
            ->call('purchase')
            ->assertHasErrors(['reward']);
    }

    public function test_kauf_reduziert_verfuegbare_baxx(): void
    {
        $user = $this->actingMemberWithPoints(30);

        $model = $this->createModelWithReward(10);

        Livewire::actingAs($user)
            ->test(ThreeDModelShow::class, ['threeDModel' => $model])
            ->call('purchase')
            ->assertRedirect(route('3d-modelle.show', $model));

        $response = $this->withoutVite()->get('/3d-modelle');
        $response->assertOk();
        $response->assertSee('20');

        $this->assertDatabaseHas('reward_purchases', [
            'user_id' => $user->id,
            'reward_id' => $model->reward_id,
            'cost_baxx' => 10,
        ]);
    }

    public function test_modell_ohne_reward_gilt_als_freigeschaltet(): void
    {
        $this->actingSimpleMember();

        $model = ThreeDModel::factory()->create(['name' => 'Kostenloses Modell']);

        $response = $this->withoutVite()->get("/3d-modelle/{$model->id}");

        $response->assertOk();
        $response->assertSee('data-three-d-viewer');
    }

    // ── Integration ──────────────────────────────────────────

    public function test_3d_modelle_nutzen_eigenes_zugriffssystem(): void
    {
        $this->actingMemberWithPoints(25);

        ThreeDModel::factory()->create(['name' => 'Zugriffs-Modell']);

        $response = $this->get('/3d-modelle');
        $response->assertOk();
        $response->assertSee('Zugriffs-Modell');
    }

    // ── Dateigrößen-Validierung ──────────────────────────────

    public function test_upload_validierung_datei_zu_gross(): void
    {
        Storage::fake('private');

        $user = $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.stl', 102401);

        Livewire::actingAs($user)
            ->test(ThreeDModelForm::class)
            ->set('name', 'Zu groß')
            ->set('description', 'Test')
            ->set('cost_baxx', 10)
            ->set('model_file', $file)
            ->call('save')
            ->assertHasErrors(['model_file']);
    }

    public function test_upload_validierung_thumbnail_zu_gross(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $user = $this->actingAdmin();

        $file = UploadedFile::fake()->create('modell.stl', 512);
        $thumbnail = UploadedFile::fake()->image('vorschau.jpg')->size(2049);

        Livewire::actingAs($user)
            ->test(ThreeDModelForm::class)
            ->set('name', 'Thumbnail zu groß')
            ->set('description', 'Test')
            ->set('cost_baxx', 10)
            ->set('model_file', $file)
            ->set('thumbnail', $thumbnail)
            ->call('save')
            ->assertHasErrors(['thumbnail']);
    }

    // ── Hilfsmethoden ─────────────────────────────────────────

    private function createModelWithReward(int $costBaxx = 10, array $attributes = []): ThreeDModel
    {
        $model = ThreeDModel::factory()->create($attributes);

        $reward = Reward::create([
            'title' => $model->name,
            'slug' => '3d-'.Str::slug($model->name).'-'.$model->id,
            'description' => $model->description ?? '',
            'category' => '3D-Modelle',
            'cost_baxx' => $costBaxx,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $model->update(['reward_id' => $reward->id]);

        return $model->refresh();
    }

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
