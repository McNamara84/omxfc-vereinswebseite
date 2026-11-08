<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\ThreeDModelForm;
use App\Livewire\ThreeDModelShow;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\ThreeDModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Large;
use Tests\Support\PhpunitMetadata;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(ThreeDModelLivewireTestFixtures::class);

test('mitglieder sehen 3d modelle uebersicht', function (): void {
    $this->actingSimpleMember();
    ThreeDModel::factory()->create(['name' => 'Euphoriewurm']);
    $response = $this->get('/3d-modelle');
    $response->assertOk();
    $response->assertSee('Euphoriewurm');
    $response->assertSee('3D-Modelle');
});

test('gaeste koennen 3d modelle nicht sehen', function (): void {
    $response = $this->get('/3d-modelle');
    $response->assertRedirect('/login');
});

test('detailseite zeigt modell informationen', function (): void {
    $this->actingSimpleMember();
    $model = ThreeDModel::factory()->create(['name' => 'Testmodell', 'description' => 'Beschreibung', 'maddraxikon_url' => 'https://maddraxikon.de/Testmodell']);
    $response = $this->get("/3d-modelle/{$model->id}");
    $response->assertOk();
    $response->assertSee('Testmodell');
    $response->assertSee('Beschreibung');
    $response->assertSee('https://maddraxikon.de/Testmodell');
    $response->assertSee('Im Maddraxikon ansehen');
});

test('detailseite ohne maddraxikon link', function (): void {
    $this->actingSimpleMember();
    $model = ThreeDModel::factory()->create(['name' => 'Ohne Link', 'maddraxikon_url' => null]);
    $response = $this->get("/3d-modelle/{$model->id}");
    $response->assertOk();
    $response->assertDontSee('Im Maddraxikon ansehen');
});

test('gesperrtes modell zeigt lock hinweis', function (): void {
    $this->actingSimpleMember();
    $model = $this->createModelWithReward(999);
    $response = $this->get("/3d-modelle/{$model->id}");
    $response->assertOk();
    $response->assertSee('999 Baxx');
    $response->assertDontSee('data-three-d-viewer');
});

test('3d modelle zeigen wallet warning for ambiguous legacy purchase', function (): void {
    $user = $this->actingMemberWithPoints(20);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'three-d-legacy-warning']);
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id, 'wallet_team_id' => null, 'cost_baxx' => 5]);
    $response = $this->get('/3d-modelle');
    $response->assertOk();
    $response->assertSee('Baxx-Guthaben wird geprüft');
});

test('freigeschaltetes modell zeigt viewer', function (): void {
    $user = $this->actingMemberWithPoints(10);
    $model = $this->createModelWithReward(5);
    $this->purchaseModelForUser($model, $user);
    $response = $this->withoutVite()->get("/3d-modelle/{$model->id}");
    $response->assertOk();
    $response->assertSee('data-three-d-viewer');
    $response->assertSee('Herunterladen');
});

test('admin kann upload formular sehen', function (): void {
    $this->actingAdmin();
    $response = $this->get('/3d-modelle/erstellen');
    $response->assertOk();
    $response->assertSee('3D-Modell hochladen');
});

test('vorstand kann upload formular sehen', function (): void {
    $this->actingVorstand();
    $response = $this->get('/3d-modelle/erstellen');
    $response->assertOk();
});

test('normales mitglied kann nicht hochladen', function (): void {
    $user = $this->actingSimpleMember();
    Livewire::actingAs($user)->test(ThreeDModelForm::class)->assertForbidden();
});

test('admin kann 3d modell hochladen', function (): void {
    Storage::fake('private');
    Storage::fake('public');
    $user = $this->actingAdmin();
    $file = UploadedFile::fake()->create('modell.stl', 1024);
    Livewire::actingAs($user)->test(ThreeDModelForm::class)->set('name', 'Testmodell')->set('description', 'Ein tolles 3D-Modell')->set('cost_baxx', 15)->set('model_file', $file)->call('save')->assertRedirect(route('3d-modelle.index'));
    $this->assertDatabaseHas('three_d_models', ['name' => 'Testmodell', 'description' => 'Ein tolles 3D-Modell', 'file_format' => 'stl']);
    $model = ThreeDModel::where('name', 'Testmodell')->first();
    expect($model->reward)->not->toBeNull()
        ->and($model->reward->cost_baxx)->toEqual(15);
});

test('admin kann 3d modell mit thumbnail hochladen', function (): void {
    Storage::fake('private');
    Storage::fake('public');
    $user = $this->actingAdmin();
    $file = UploadedFile::fake()->create('modell.obj', 2048);
    $thumbnail = UploadedFile::fake()->image('vorschau.jpg', 400, 300);
    Livewire::actingAs($user)->test(ThreeDModelForm::class)->set('name', 'OBJ-Modell')->set('description', 'Ein OBJ-Modell mit Vorschaubild')->set('cost_baxx', 10)->set('maddraxikon_url', 'https://maddraxikon.de/Euphoriewurm')->set('model_file', $file)->set('thumbnail', $thumbnail)->call('save')->assertRedirect(route('3d-modelle.index'));
    $model = ThreeDModel::where('name', 'OBJ-Modell')->first();
    expect($model)->not->toBeNull()
        ->and($model->thumbnail_path)->not->toBeNull()
        ->and($model->file_format)->toEqual('obj')
        ->and($model->maddraxikon_url)->toEqual('https://maddraxikon.de/Euphoriewurm');
});

test('upload validierung fehlende pflichtfelder', function (): void {
    $user = $this->actingAdmin();
    Livewire::actingAs($user)->test(ThreeDModelForm::class)->set('name', '')->set('description', '')->set('cost_baxx', 0)->call('save')->assertHasErrors(['name', 'description', 'cost_baxx', 'model_file']);
});

test('upload validierung baxx min 1', function (): void {
    Storage::fake('private');
    $user = $this->actingAdmin();
    $file = UploadedFile::fake()->create('modell.stl', 512);
    Livewire::actingAs($user)->test(ThreeDModelForm::class)->set('name', 'Test')->set('description', 'Test')->set('cost_baxx', 0)->set('model_file', $file)->call('save')->assertHasErrors(['cost_baxx']);
});

test('upload validierung ungueltige maddraxikon url', function (): void {
    Storage::fake('private');
    $user = $this->actingAdmin();
    $file = UploadedFile::fake()->create('modell.stl', 512);
    Livewire::actingAs($user)->test(ThreeDModelForm::class)->set('name', 'Test')->set('description', 'Test')->set('cost_baxx', 10)->set('model_file', $file)->set('maddraxikon_url', 'keine-url')->call('save')->assertHasErrors(['maddraxikon_url']);
});

test('upload validierung ungueltiges dateiformat', function (): void {
    Storage::fake('private');
    $user = $this->actingAdmin();
    $file = UploadedFile::fake()->create('modell.zip', 512);
    Livewire::actingAs($user)->test(ThreeDModelForm::class)->set('name', 'Test')->set('description', 'Test')->set('cost_baxx', 10)->set('model_file', $file)->call('save')->assertHasErrors(['model_file']);
});

test('admin kann modell bearbeiten', function (): void {
    $this->actingAdmin();
    $model = ThreeDModel::factory()->create(['name' => 'Alt']);
    $response = $this->get("/3d-modelle/{$model->id}/bearbeiten");
    $response->assertOk();
    $response->assertSee('Alt');
});

test('admin kann modell aktualisieren', function (): void {
    Storage::fake('private');
    $user = $this->actingAdmin();
    $model = ThreeDModel::factory()->create(['name' => 'Alt']);
    Livewire::actingAs($user)->test(ThreeDModelForm::class, ['threeDModel' => $model])->set('name', 'Neu')->set('description', 'Neue Beschreibung')->set('cost_baxx', 20)->set('maddraxikon_url', 'https://maddraxikon.de/Neu')->call('save')->assertRedirect(route('3d-modelle.index'));
    $model->refresh();
    expect($model->name)->toEqual('Neu')
        ->and($model->reward)->not->toBeNull()
        ->and($model->reward->cost_baxx)->toEqual(20)
        ->and($model->maddraxikon_url)->toEqual('https://maddraxikon.de/Neu');
});

test('admin kann modell mit neuer datei aktualisieren', function (): void {
    Storage::fake('private');
    $user = $this->actingAdmin();
    $model = ThreeDModel::factory()->create(['name' => 'Original', 'file_format' => 'stl', 'file_size' => 1024]);
    Storage::disk('private')->put($model->file_path, 'old-content');
    $oldFilePath = $model->file_path;
    $newFile = UploadedFile::fake()->create('neues-modell.obj', 2048);
    Livewire::actingAs($user)->test(ThreeDModelForm::class, ['threeDModel' => $model])->set('name', 'Aktualisiert')->set('description', 'Neue Beschreibung')->set('cost_baxx', 15)->set('model_file', $newFile)->call('save')->assertRedirect(route('3d-modelle.index'));
    $model->refresh();
    expect($model->name)->toEqual('Aktualisiert')
        ->and($model->file_format)->toEqual('obj')
        ->and($model->file_path)->not->toEqual($oldFilePath);
    Storage::disk('private')->assertMissing($oldFilePath);
    Storage::disk('private')->assertExists($model->file_path);
});

test('normales mitglied kann nicht bearbeiten', function (): void {
    $user = $this->actingSimpleMember();
    $model = ThreeDModel::factory()->create();
    Livewire::actingAs($user)->test(ThreeDModelForm::class, ['threeDModel' => $model])->assertForbidden();
});

test('admin kann modell loeschen', function (): void {
    Storage::fake('private');
    Storage::fake('public');
    $user = $this->actingAdmin();
    $model = ThreeDModel::factory()->create();
    Storage::disk('private')->put($model->file_path, 'fake-content');
    Livewire::actingAs($user)->test(ThreeDModelShow::class, ['threeDModel' => $model])->call('deleteModel')->assertRedirect(route('3d-modelle.index'));
    $this->assertDatabaseMissing('three_d_models', ['id' => $model->id]);
    Storage::disk('private')->assertMissing($model->file_path);
});

test('normales mitglied kann nicht loeschen', function (): void {
    $user = $this->actingSimpleMember();
    $model = ThreeDModel::factory()->create();
    Livewire::actingAs($user)->test(ThreeDModelShow::class, ['threeDModel' => $model])->call('deleteModel')->assertForbidden();
    $this->assertDatabaseHas('three_d_models', ['id' => $model->id]);
});

test('download mit genuegend baxx', function (): void {
    Storage::fake('private');
    $user = $this->actingMemberWithPoints(20);
    $model = $this->createModelWithReward(10, ['file_format' => 'stl']);
    $this->purchaseModelForUser($model, $user);
    Storage::disk('private')->put($model->file_path, 'fake-content');
    // MIME coverage must not depend on the factory's random format choice.
    foreach (['stl' => 'model/stl', 'obj' => 'text/plain', 'fbx' => 'application/octet-stream'] as $format => $mime) {
        $model->update(['file_format' => $format]);
        $response = $this->get("/3d-modelle/{$model->id}/herunterladen");
        $response->assertOk()->assertHeader('Content-Type')->assertStreamedContent('fake-content');
        expect(explode(';', $response->headers->get('Content-Type'), 2)[0])->toBe($mime);
    }
});

test('download ohne genuegend baxx wird abgelehnt', function (): void {
    $this->actingSimpleMember();
    $model = $this->createModelWithReward(50);
    $response = $this->get("/3d-modelle/{$model->id}/herunterladen");
    $response->assertRedirect(route('3d-modelle.show', $model));
    $response->assertSessionHasErrors('reward');
});

test('download fehlende datei gibt fehler', function (): void {
    Storage::fake('private');
    $user = $this->actingMemberWithPoints(20);
    $model = $this->createModelWithReward(10);
    $this->purchaseModelForUser($model, $user);
    $response = $this->get("/3d-modelle/{$model->id}/herunterladen");
    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

test('vorschau mit genuegend baxx', function (): void {
    Storage::fake('private');
    $user = $this->actingMemberWithPoints(20);
    $model = $this->createModelWithReward(10);
    $this->purchaseModelForUser($model, $user);
    Storage::disk('private')->put($model->file_path, 'fake-content');
    $response = $this->get("/3d-modelle/{$model->id}/vorschau");
    $response->assertOk();
});

test('vorschau ohne genuegend baxx wird abgelehnt', function (): void {
    $this->actingSimpleMember();
    $model = $this->createModelWithReward(50);
    $response = $this->get("/3d-modelle/{$model->id}/vorschau");
    $response->assertRedirect(route('3d-modelle.show', $model));
    $response->assertSessionHasErrors('reward');
});

test('vorschau fehlende datei gibt fehler', function (): void {
    Storage::fake('private');
    $user = $this->actingMemberWithPoints(20);
    $model = $this->createModelWithReward(10);
    $this->purchaseModelForUser($model, $user);
    $response = $this->get("/3d-modelle/{$model->id}/vorschau");
    $response->assertNotFound();
});

test('kauf erfolgreich mit genuegend baxx', function (): void {
    $user = $this->actingMemberWithPoints(20);
    $model = $this->createModelWithReward(10);
    Livewire::actingAs($user)->test(ThreeDModelShow::class, ['threeDModel' => $model])->call('purchase')->assertRedirect(route('3d-modelle.show', $model));
    $this->assertDatabaseHas('reward_purchases', ['user_id' => $user->id, 'reward_id' => $model->reward_id, 'cost_baxx' => 10]);
});

test('kauf mit zu wenig baxx schlaegt fehl', function (): void {
    $user = $this->actingMemberWithPoints(5);
    $model = $this->createModelWithReward(50);
    Livewire::actingAs($user)->test(ThreeDModelShow::class, ['threeDModel' => $model])->call('purchase')->assertHasErrors(['reward']);
    $this->assertDatabaseMissing('reward_purchases', ['reward_id' => $model->reward_id]);
});

test('doppelkauf wird abgelehnt', function (): void {
    $user = $this->actingMemberWithPoints(100);
    $model = $this->createModelWithReward(10);
    $this->purchaseModelForUser($model, $user);
    Livewire::actingAs($user)->test(ThreeDModelShow::class, ['threeDModel' => $model])->call('purchase')->assertHasErrors(['reward']);
});

test('kauf reduziert verfuegbare baxx', function (): void {
    $user = $this->actingMemberWithPoints(30);
    $model = $this->createModelWithReward(10);
    Livewire::actingAs($user)->test(ThreeDModelShow::class, ['threeDModel' => $model])->call('purchase')->assertRedirect(route('3d-modelle.show', $model));
    $response = $this->withoutVite()->get('/3d-modelle');
    $response->assertOk();
    $response->assertSee('20');
    $this->assertDatabaseHas('reward_purchases', ['user_id' => $user->id, 'reward_id' => $model->reward_id, 'cost_baxx' => 10]);
});

test('modell ohne reward gilt als freigeschaltet', function (): void {
    $this->actingSimpleMember();
    $model = ThreeDModel::factory()->create(['name' => 'Kostenloses Modell']);
    $response = $this->withoutVite()->get("/3d-modelle/{$model->id}");
    $response->assertOk();
    $response->assertSee('data-three-d-viewer');
});

test('3d modelle nutzen eigenes zugriffssystem', function (): void {
    $this->actingMemberWithPoints(25);
    ThreeDModel::factory()->create(['name' => 'Zugriffs-Modell']);
    $response = $this->get('/3d-modelle');
    $response->assertOk();
    $response->assertSee('Zugriffs-Modell');
});

test('upload validierung datei zu gross', function (): void {
    Storage::fake('private');
    $user = $this->actingAdmin();
    $file = UploadedFile::fake()->create('modell.stl', 102401);
    Livewire::actingAs($user)->test(ThreeDModelForm::class)->set('name', 'Zu groß')->set('description', 'Test')->set('cost_baxx', 10)->set('model_file', $file)->call('save')->assertHasErrors(['model_file']);
});

test('upload validierung thumbnail zu gross', function (): void {
    Storage::fake('private');
    Storage::fake('public');
    $user = $this->actingAdmin();
    $file = UploadedFile::fake()->create('modell.stl', 512);
    $baseThumbnail = UploadedFile::fake()->image('vorschau.jpg', 400, 300);
    $thumbnail = UploadedFile::fake()->createWithContent('vorschau.jpg', file_get_contents($baseThumbnail->getRealPath()).str_repeat('A', 2048 * 1024 + 1));
    Livewire::actingAs($user)->test(ThreeDModelForm::class)->set('name', 'Thumbnail zu groß')->set('description', 'Test')->set('cost_baxx', 10)->set('model_file', $file)->set('thumbnail', $thumbnail)->call('save')->assertHasErrors(['thumbnail']);
});

PhpunitMetadata::onFile(__FILE__, Large::class);
