<?php

declare(strict_types=1);

namespace Tests\Feature;

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

pest()->extend(TestCase::class)->use(FanfictionPreviewTestFixtures::class);

beforeEach(function (): void {
    $this->memberTeam = Team::membersTeam();
    $this->vorstand = User::factory()->create();
    $this->vorstand->teams()->attach($this->memberTeam, ['role' => Role::Vorstand->value]);
    $this->vorstand->switchTeam($this->memberTeam);
});

test('create preview toggle changes state', function (): void {
    $this->actingAs($this->vorstand);
    Livewire::test(FanfictionCreate::class)->assertSet('showPreview', false)->call('togglePreview')->assertSet('showPreview', true)->call('togglePreview')->assertSet('showPreview', false);
});

test('create preview renders markdown', function (): void {
    $this->actingAs($this->vorstand);
    Livewire::test(FanfictionCreate::class)->set('content', '**Fettgedruckt** und *kursiv*')->call('togglePreview')->assertSet('showPreview', true)->assertSee('Fettgedruckt');
});

test('create preview html contains strong tag', function (): void {
    $this->actingAs($this->vorstand);
    $component = Livewire::test(FanfictionCreate::class)->set('content', '**Fett**')->call('togglePreview');
    expect($component->get('previewHtml'))->toContain('<strong>Fett</strong>');
});

test('create preview with empty content shows empty html', function (): void {
    $this->actingAs($this->vorstand);
    Livewire::test(FanfictionCreate::class)->set('content', '')->call('togglePreview')->assertSet('showPreview', true)->assertSet('previewHtml', '');
});

test('edit preview toggle changes state', function (): void {
    $fanfiction = Fanfiction::factory()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id]);
    $this->actingAs($this->vorstand);
    Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])->assertSet('showPreview', false)->call('togglePreview')->assertSet('showPreview', true)->call('togglePreview')->assertSet('showPreview', false);
});

test('edit preview renders existing content', function (): void {
    $fanfiction = Fanfiction::factory()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'content' => 'Eine **tolle** Geschichte']);
    $this->actingAs($this->vorstand);
    $component = Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])->call('togglePreview');
    expect($component->get('previewHtml'))->toContain('<strong>tolle</strong>');
});

test('edit preview renders modified content', function (): void {
    $fanfiction = Fanfiction::factory()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'content' => 'Alter Inhalt']);
    $this->actingAs($this->vorstand);
    $component = Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])->set('content', '**Neuer** Inhalt')->call('togglePreview');
    expect($component->get('previewHtml'))->toContain('<strong>Neuer</strong>');
});

test('edit preview resolves bild tags', function (): void {
    $fanfiction = Fanfiction::factory()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'content' => 'Text [bild:1:links:Illustration] weiter', 'photos' => ['fanfiction/test-foto.jpg']]);
    $this->actingAs($this->vorstand);
    $component = Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])->call('togglePreview');
    $previewHtml = $component->get('previewHtml');
    expect($previewHtml)->toContain('fanfiction-bild--links')
        ->toContain('<figcaption>Illustration</figcaption>')
        ->toContain('test-foto.jpg');
});

test('edit preview excludes photos marked for removal', function (): void {
    $fanfiction = Fanfiction::factory()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'content' => '[bild:1] und [bild:2]', 'photos' => ['fanfiction/photo1.jpg', 'fanfiction/photo2.jpg']]);
    $this->actingAs($this->vorstand);
    $component = Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])->call('togglePhotoRemoval', 'fanfiction/photo1.jpg')->call('togglePreview');
    $previewHtml = $component->get('previewHtml');
    // After removing photo1, only photo2 remains at index 0
    expect($previewHtml)->toContain('photo2.jpg');
});

test('show page renders inline bild tags', function (): void {
    $member = User::factory()->create();
    $member->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
    $member->switchTeam($this->memberTeam);
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'content' => 'Geschichte mit [bild:1:zentriert:Cooles Bild] eingebettet.', 'photos' => ['fanfiction/inline-test.jpg']]);
    $response = $this->actingAs($member)->get(route('fanfiction.show', $fanfiction));
    $response->assertOk();
    $response->assertSee('fanfiction-bild--zentriert', false);
    $response->assertSee('Cooles Bild', false);
});

test('show page shows unreferenced photos in gallery', function (): void {
    $member = User::factory()->create();
    $member->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
    $member->switchTeam($this->memberTeam);
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'content' => 'Geschichte mit [bild:1] im Text.', 'photos' => ['fanfiction/referenced.jpg', 'fanfiction/gallery-only.jpg']]);
    $response = $this->actingAs($member)->get(route('fanfiction.show', $fanfiction));
    $response->assertOk();
    // gallery-only.jpg should still appear in gallery section
    $response->assertSee('gallery-only.jpg', false);
});

test('show page hides gallery when all photos referenced', function (): void {
    $member = User::factory()->create();
    $member->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
    $member->switchTeam($this->memberTeam);
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $this->memberTeam->id, 'created_by' => $this->vorstand->id, 'content' => '[bild:1] und [bild:2]', 'photos' => ['fanfiction/a.jpg', 'fanfiction/b.jpg']]);
    $response = $this->actingAs($member)->get(route('fanfiction.show', $fanfiction));
    $response->assertOk();
    // No separate gallery grid should be rendered
    $response->assertDontSee('data-testid="fanfiction-gallery"', false);
});

test('create allows up to 10 photos', function (): void {
    Storage::fake('public');
    $this->actingAs($this->vorstand);
    $photos = [];
    for ($i = 0; $i < 10; $i++) {
        $photos[] = UploadedFile::fake()->image("test{$i}.jpg", 200, 200);
    }
    Livewire::test(FanfictionCreate::class)->set('title', 'Viele Bilder')->set('content', 'Geschichte mit vielen Bildern darin')->set('authorType', 'external')->set('authorName', 'Autor')->set('photos', $photos)->set('status', 'draft')->call('save')->assertRedirect(route('admin.fanfiction.index'));
    $fanfiction = Fanfiction::where('title', 'Viele Bilder')->first();
    expect($fanfiction)->not->toBeNull()
        ->and($fanfiction->photos)->toHaveCount(10);
});

test('create rejects more than 10 photos', function (): void {
    Storage::fake('public');
    $this->actingAs($this->vorstand);
    $photos = [];
    for ($i = 0; $i < 11; $i++) {
        $photos[] = UploadedFile::fake()->image("test{$i}.jpg", 200, 200);
    }
    Livewire::test(FanfictionCreate::class)->set('title', 'Zu viele Bilder')->set('content', 'Geschichte mit zu vielen Bildern')->set('authorType', 'external')->set('authorName', 'Autor')->set('photos', $photos)->set('status', 'draft')->call('save')->assertHasErrors('photos');
});
