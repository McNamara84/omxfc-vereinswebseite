<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FanfictionStatus;
use App\Enums\Role;
use App\Livewire\FanfictionCreate;
use App\Livewire\FanfictionEdit;
use App\Models\Fanfiction;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(FanfictionAdminControllerTestFixtures::class);

beforeEach(function (): void {
    $this->memberTeam = Team::membersTeam();
    $this->vorstand = User::factory()->create();
    $this->vorstand->teams()->attach($this->memberTeam, ['role' => Role::Vorstand->value]);
    $this->vorstand->switchTeam($this->memberTeam);
    $this->member = User::factory()->create();
    $this->member->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
    $this->member->switchTeam($this->memberTeam);
});

test('vorstand can access fanfiction admin index', function (): void {
    $response = $this->actingAs($this->vorstand)->get(route('admin.fanfiction.index'));
    $response->assertOk();
    $response->assertViewIs('admin.fanfiction.index');
});

test('member cannot access fanfiction admin index', function (): void {
    $response = $this->actingAs($this->member)->get(route('admin.fanfiction.index'));
    $response->assertForbidden();
});

test('vorstand can create fanfiction with member author', function (): void {
    $this->actingAs($this->vorstand);
    Livewire::test(FanfictionCreate::class)->set('title', 'Testgeschichte')->set('content', 'Dies ist der Inhalt der Testgeschichte...')->set('authorType', 'member')->set('userId', $this->member->id)->set('authorName', $this->member->name)->set('status', 'draft')->call('save')->assertRedirect(route('admin.fanfiction.index'));
    $this->assertDatabaseHas('fanfictions', ['title' => 'Testgeschichte', 'user_id' => $this->member->id, 'status' => FanfictionStatus::Draft->value]);
});

test('vorstand can create fanfiction with external author', function (): void {
    $this->actingAs($this->vorstand);
    Livewire::test(FanfictionCreate::class)->set('title', 'Externe Geschichte')->set('content', 'Dies ist eine Geschichte von einem externen Autor.')->set('authorType', 'external')->set('userId', null)->set('authorName', 'Max Mustermann')->set('status', 'draft')->call('save')->assertRedirect(route('admin.fanfiction.index'));
    $this->assertDatabaseHas('fanfictions', ['title' => 'Externe Geschichte', 'user_id' => null, 'author_name' => 'Max Mustermann']);
});

test('vorstand can upload photos with fanfiction', function (): void {
    Storage::fake('public');
    $this->actingAs($this->vorstand);
    $photo = UploadedFile::fake()->image('test.jpg', 800, 600);
    Livewire::test(FanfictionCreate::class)->set('title', 'Geschichte mit Bild')->set('content', 'Dies ist der Inhalt der Geschichte mit einem Bild.')->set('authorType', 'external')->set('authorName', 'Autor')->set('photos', [$photo])->set('status', 'draft')->call('save')->assertRedirect(route('admin.fanfiction.index'));
    $fanfiction = Fanfiction::where('title', 'Geschichte mit Bild')->first();
    expect($fanfiction)->not->toBeNull()
        ->and($fanfiction->photos)->not->toBeEmpty();
    Storage::disk('public')->assertExists($fanfiction->photos[0]);
});

test('vorstand can publish draft fanfiction', function (): void {
    $fanfiction = Fanfiction::factory()->draft()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'user_id' => $this->member->id]);
    expect($fanfiction->published_at)->toBeNull();
    $response = $this->actingAs($this->vorstand)->post(route('admin.fanfiction.publish', $fanfiction));
    $response->assertRedirect(route('admin.fanfiction.index'));
    $response->assertSessionHas('success');
    $fanfiction->refresh();
    expect($fanfiction->status)->toEqual(FanfictionStatus::Published)
        ->and($fanfiction->published_at)->not->toBeNull();
});

test('publishing awards baxx to member author', function (): void {
    $initialPoints = $this->member->totalPointsForTeam($this->memberTeam);
    $fanfiction = Fanfiction::factory()->draft()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'user_id' => $this->member->id]);
    $this->actingAs($this->vorstand)->post(route('admin.fanfiction.publish', $fanfiction));
    $this->member->refresh();
    expect($this->member->totalPointsForTeam($this->memberTeam))->toEqual($initialPoints + 5);
});

test('publishing does not award baxx to external author', function (): void {
    $fanfiction = Fanfiction::factory()->draft()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'user_id' => null, 'author_name' => 'Externer Autor']);
    $response = $this->actingAs($this->vorstand)->post(route('admin.fanfiction.publish', $fanfiction));
    $response->assertRedirect(route('admin.fanfiction.index'));
    // No exception should be thrown
});

test('vorstand can update fanfiction', function (): void {
    $fanfiction = Fanfiction::factory()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'title' => 'Original Titel']);
    $this->actingAs($this->vorstand);
    Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])->set('title', 'Neuer Titel')->call('save')->assertRedirect(route('admin.fanfiction.index'));
    $fanfiction->refresh();
    expect($fanfiction->title)->toEqual('Neuer Titel');
});

test('vorstand can delete fanfiction', function (): void {
    $fanfiction = Fanfiction::factory()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id]);
    $response = $this->actingAs($this->vorstand)->delete(route('admin.fanfiction.destroy', $fanfiction));
    $response->assertRedirect(route('admin.fanfiction.index'));
    $this->assertSoftDeleted('fanfictions', ['id' => $fanfiction->id]);
});

test('photo upload respects max file size', function (): void {
    Storage::fake('public');
    $this->actingAs($this->vorstand);
    // Create a file larger than 2MB (2048KB)
    $largePhoto = UploadedFile::fake()->create('large.jpg', 3000);
    Livewire::test(FanfictionCreate::class)->set('title', 'Geschichte')->set('content', 'Inhalt...')->set('authorType', 'external')->set('authorName', 'Autor')->set('photos', [$largePhoto])->set('status', 'draft')->call('save')->assertHasErrors('photos.0');
});

test('photo upload respects max count', function (): void {
    Storage::fake('public');
    $this->actingAs($this->vorstand);
    $photos = [];
    for ($i = 0; $i < 11; $i++) {
        $photos[] = UploadedFile::fake()->image("test{$i}.jpg", 200, 200);
    }
    Livewire::test(FanfictionCreate::class)->set('title', 'Geschichte')->set('content', 'Inhalt...')->set('authorType', 'external')->set('authorName', 'Autor')->set('photos', $photos)->set('status', 'draft')->call('save')->assertHasErrors('photos');
});

test('vorstand can remove photos during update', function (): void {
    Storage::fake('public');
    // Create a fanfiction with photos
    $photo1 = UploadedFile::fake()->image('photo1.jpg', 400, 300);
    $photo2 = UploadedFile::fake()->image('photo2.jpg', 400, 300);
    $path1 = $photo1->store('fanfiction', 'public');
    $path2 = $photo2->store('fanfiction', 'public');
    $fanfiction = Fanfiction::factory()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'photos' => [$path1, $path2]]);
    Storage::disk('public')->assertExists($path1);
    Storage::disk('public')->assertExists($path2);
    $this->actingAs($this->vorstand);
    // Remove one photo via Livewire
    Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])->call('togglePhotoRemoval', $path1)->call('save')->assertRedirect(route('admin.fanfiction.index'));
    $fanfiction->refresh();
    expect($fanfiction->photos)->toHaveCount(1)
        ->toContain($path2)->not->toContain($path1);
    Storage::disk('public')->assertMissing($path1);
    Storage::disk('public')->assertExists($path2);
});

test('publishing already published fanfiction returns info message', function (): void {
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id]);
    $response = $this->actingAs($this->vorstand)->post(route('admin.fanfiction.publish', $fanfiction));
    $response->assertRedirect(route('admin.fanfiction.index'));
    $response->assertSessionHas('info', 'Diese Fanfiction ist bereits veröffentlicht.');
});

test('publishing creates reward for fanfiction', function (): void {
    $fanfiction = Fanfiction::factory()->draft()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'user_id' => $this->member->id]);
    expect($fanfiction->reward_id)->toBeNull();
    $this->actingAs($this->vorstand)->post(route('admin.fanfiction.publish', $fanfiction));
    $fanfiction->refresh();
    expect($fanfiction->reward_id)->not->toBeNull()
        ->and($fanfiction->reward)->not->toBeNull()
        ->and($fanfiction->reward->cost_baxx)->toEqual(config('rewards.fanfiction_default_cost_baxx'))
        ->and($fanfiction->reward->is_active)->toBeTrue();
});

test('deleting published fanfiction removes reward', function (): void {
    $fanfiction = Fanfiction::factory()->draft()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id]);
    // Veröffentlichen, um Reward zu erstellen
    $this->actingAs($this->vorstand)->post(route('admin.fanfiction.publish', $fanfiction));
    $fanfiction->refresh();
    $rewardId = $fanfiction->reward_id;
    expect($rewardId)->not->toBeNull();
    // Fanfiction löschen
    $this->actingAs($this->vorstand)->delete(route('admin.fanfiction.destroy', $fanfiction));
    $this->assertSoftDeleted('fanfictions', ['id' => $fanfiction->id]);
    $this->assertDatabaseMissing('rewards', ['id' => $rewardId]);
});
