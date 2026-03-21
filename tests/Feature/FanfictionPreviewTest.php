<?php

namespace Tests\Feature;

use App\Enums\FanfictionStatus;
use App\Enums\Role;
use App\Livewire\FanfictionCreate;
use App\Livewire\FanfictionEdit;
use App\Models\Fanfiction;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FanfictionPreviewTest extends TestCase
{
    use RefreshDatabase;

    private Team $memberTeam;

    private User $vorstand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberTeam = Team::membersTeam();
        $this->vorstand = User::factory()->create();
        $this->vorstand->teams()->attach($this->memberTeam, ['role' => Role::Vorstand->value]);
        $this->vorstand->switchTeam($this->memberTeam);
    }

    // ── Create-Vorschau ────────────────────────────────────────

    public function test_create_preview_toggle_changes_state(): void
    {
        $this->actingAs($this->vorstand);

        Livewire::test(FanfictionCreate::class)
            ->assertSet('showPreview', false)
            ->call('togglePreview')
            ->assertSet('showPreview', true)
            ->call('togglePreview')
            ->assertSet('showPreview', false);
    }

    public function test_create_preview_renders_markdown(): void
    {
        $this->actingAs($this->vorstand);

        Livewire::test(FanfictionCreate::class)
            ->set('content', '**Fettgedruckt** und *kursiv*')
            ->call('togglePreview')
            ->assertSet('showPreview', true)
            ->assertSee('Fettgedruckt');
    }

    public function test_create_preview_html_contains_strong_tag(): void
    {
        $this->actingAs($this->vorstand);

        $component = Livewire::test(FanfictionCreate::class)
            ->set('content', '**Fett**')
            ->call('togglePreview');

        $this->assertStringContainsString('<strong>Fett</strong>', $component->get('previewHtml'));
    }

    public function test_create_preview_with_empty_content_shows_empty_html(): void
    {
        $this->actingAs($this->vorstand);

        Livewire::test(FanfictionCreate::class)
            ->set('content', '')
            ->call('togglePreview')
            ->assertSet('showPreview', true)
            ->assertSet('previewHtml', '');
    }

    // ── Edit-Vorschau ──────────────────────────────────────────

    public function test_edit_preview_toggle_changes_state(): void
    {
        $fanfiction = Fanfiction::factory()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
        ]);

        $this->actingAs($this->vorstand);

        Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])
            ->assertSet('showPreview', false)
            ->call('togglePreview')
            ->assertSet('showPreview', true)
            ->call('togglePreview')
            ->assertSet('showPreview', false);
    }

    public function test_edit_preview_renders_existing_content(): void
    {
        $fanfiction = Fanfiction::factory()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'content' => 'Eine **tolle** Geschichte',
        ]);

        $this->actingAs($this->vorstand);

        $component = Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])
            ->call('togglePreview');

        $this->assertStringContainsString('<strong>tolle</strong>', $component->get('previewHtml'));
    }

    public function test_edit_preview_renders_modified_content(): void
    {
        $fanfiction = Fanfiction::factory()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'content' => 'Alter Inhalt',
        ]);

        $this->actingAs($this->vorstand);

        $component = Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])
            ->set('content', '**Neuer** Inhalt')
            ->call('togglePreview');

        $this->assertStringContainsString('<strong>Neuer</strong>', $component->get('previewHtml'));
    }

    // ── Vorschau mit Bild-Tags ─────────────────────────────────

    public function test_edit_preview_resolves_bild_tags(): void
    {
        $fanfiction = Fanfiction::factory()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'content' => 'Text [bild:1:links:Illustration] weiter',
            'photos' => ['fanfiction/test-foto.jpg'],
        ]);

        $this->actingAs($this->vorstand);

        $component = Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])
            ->call('togglePreview');

        $previewHtml = $component->get('previewHtml');
        $this->assertStringContainsString('fanfiction-bild--links', $previewHtml);
        $this->assertStringContainsString('<figcaption>Illustration</figcaption>', $previewHtml);
        $this->assertStringContainsString('test-foto.jpg', $previewHtml);
    }

    public function test_edit_preview_excludes_photos_marked_for_removal(): void
    {
        $fanfiction = Fanfiction::factory()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'content' => '[bild:1] und [bild:2]',
            'photos' => ['fanfiction/photo1.jpg', 'fanfiction/photo2.jpg'],
        ]);

        $this->actingAs($this->vorstand);

        $component = Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])
            ->call('togglePhotoRemoval', 'fanfiction/photo1.jpg')
            ->call('togglePreview');

        $previewHtml = $component->get('previewHtml');
        // After removing photo1, only photo2 remains at index 0
        $this->assertStringContainsString('photo2.jpg', $previewHtml);
    }

    // ── Bild-Tags in Show-Ansicht ──────────────────────────────

    public function test_show_page_renders_inline_bild_tags(): void
    {
        $member = User::factory()->create();
        $member->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
        $member->switchTeam($this->memberTeam);

        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'content' => 'Geschichte mit [bild:1:zentriert:Cooles Bild] eingebettet.',
            'photos' => ['fanfiction/inline-test.jpg'],
        ]);

        $response = $this->actingAs($member)
            ->get(route('fanfiction.show', $fanfiction));

        $response->assertOk();
        $response->assertSee('fanfiction-bild--zentriert', false);
        $response->assertSee('Cooles Bild', false);
    }

    public function test_show_page_shows_unreferenced_photos_in_gallery(): void
    {
        $member = User::factory()->create();
        $member->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
        $member->switchTeam($this->memberTeam);

        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'content' => 'Geschichte mit [bild:1] im Text.',
            'photos' => ['fanfiction/referenced.jpg', 'fanfiction/gallery-only.jpg'],
        ]);

        $response = $this->actingAs($member)
            ->get(route('fanfiction.show', $fanfiction));

        $response->assertOk();
        // gallery-only.jpg should still appear in gallery section
        $response->assertSee('gallery-only.jpg', false);
    }

    public function test_show_page_hides_gallery_when_all_photos_referenced(): void
    {
        $member = User::factory()->create();
        $member->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
        $member->switchTeam($this->memberTeam);

        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'content' => '[bild:1] und [bild:2]',
            'photos' => ['fanfiction/a.jpg', 'fanfiction/b.jpg'],
        ]);

        $response = $this->actingAs($member)
            ->get(route('fanfiction.show', $fanfiction));

        $response->assertOk();
        // No separate gallery grid should be rendered
        $response->assertDontSee('grid grid-cols-2 md:grid-cols-3 gap-4', false);
    }

    // ── Foto-Limit ─────────────────────────────────────────────

    public function test_create_allows_up_to_10_photos(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->actingAs($this->vorstand);

        $photos = [];
        for ($i = 0; $i < 10; $i++) {
            $photos[] = \Illuminate\Http\UploadedFile::fake()->image("test{$i}.jpg", 200, 200);
        }

        Livewire::test(FanfictionCreate::class)
            ->set('title', 'Viele Bilder')
            ->set('content', 'Geschichte mit vielen Bildern darin')
            ->set('authorType', 'external')
            ->set('authorName', 'Autor')
            ->set('photos', $photos)
            ->set('status', 'draft')
            ->call('save')
            ->assertRedirect(route('admin.fanfiction.index'));

        $fanfiction = Fanfiction::where('title', 'Viele Bilder')->first();
        $this->assertNotNull($fanfiction);
        $this->assertCount(10, $fanfiction->photos);
    }

    public function test_create_rejects_more_than_10_photos(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->actingAs($this->vorstand);

        $photos = [];
        for ($i = 0; $i < 11; $i++) {
            $photos[] = \Illuminate\Http\UploadedFile::fake()->image("test{$i}.jpg", 200, 200);
        }

        Livewire::test(FanfictionCreate::class)
            ->set('title', 'Zu viele Bilder')
            ->set('content', 'Geschichte mit zu vielen Bildern')
            ->set('authorType', 'external')
            ->set('authorName', 'Autor')
            ->set('photos', $photos)
            ->set('status', 'draft')
            ->call('save')
            ->assertHasErrors('photos');
    }
}
