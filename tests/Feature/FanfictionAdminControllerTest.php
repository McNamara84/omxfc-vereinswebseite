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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FanfictionAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $memberTeam;

    private User $vorstand;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberTeam = Team::membersTeam();
        $this->vorstand = User::factory()->create();
        $this->vorstand->teams()->attach($this->memberTeam, ['role' => Role::Vorstand->value]);
        $this->vorstand->switchTeam($this->memberTeam);

        $this->member = User::factory()->create();
        $this->member->teams()->attach($this->memberTeam, ['role' => Role::Mitglied->value]);
        $this->member->switchTeam($this->memberTeam);
    }

    public function test_vorstand_can_access_fanfiction_admin_index(): void
    {
        $response = $this->actingAs($this->vorstand)
            ->get(route('admin.fanfiction.index'));

        $response->assertOk();
        $response->assertViewIs('admin.fanfiction.index');
    }

    public function test_member_cannot_access_fanfiction_admin_index(): void
    {
        $response = $this->actingAs($this->member)
            ->get(route('admin.fanfiction.index'));

        $response->assertForbidden();
    }

    public function test_vorstand_can_create_fanfiction_with_member_author(): void
    {
        $this->actingAs($this->vorstand);

        Livewire::test(FanfictionCreate::class)
            ->set('title', 'Testgeschichte')
            ->set('content', 'Dies ist der Inhalt der Testgeschichte...')
            ->set('authorType', 'member')
            ->set('userId', $this->member->id)
            ->set('authorName', $this->member->name)
            ->set('status', 'draft')
            ->call('save')
            ->assertRedirect(route('admin.fanfiction.index'));

        $this->assertDatabaseHas('fanfictions', [
            'title' => 'Testgeschichte',
            'user_id' => $this->member->id,
            'status' => FanfictionStatus::Draft->value,
        ]);
    }

    public function test_vorstand_can_create_fanfiction_with_external_author(): void
    {
        $this->actingAs($this->vorstand);

        Livewire::test(FanfictionCreate::class)
            ->set('title', 'Externe Geschichte')
            ->set('content', 'Dies ist eine Geschichte von einem externen Autor.')
            ->set('authorType', 'external')
            ->set('userId', null)
            ->set('authorName', 'Max Mustermann')
            ->set('status', 'draft')
            ->call('save')
            ->assertRedirect(route('admin.fanfiction.index'));

        $this->assertDatabaseHas('fanfictions', [
            'title' => 'Externe Geschichte',
            'user_id' => null,
            'author_name' => 'Max Mustermann',
        ]);
    }

    public function test_vorstand_can_upload_photos_with_fanfiction(): void
    {
        Storage::fake('public');

        $this->actingAs($this->vorstand);

        $photo = UploadedFile::fake()->image('test.jpg', 800, 600);

        Livewire::test(FanfictionCreate::class)
            ->set('title', 'Geschichte mit Bild')
            ->set('content', 'Dies ist der Inhalt der Geschichte mit einem Bild.')
            ->set('authorType', 'external')
            ->set('authorName', 'Autor')
            ->set('photos', [$photo])
            ->set('status', 'draft')
            ->call('save')
            ->assertRedirect(route('admin.fanfiction.index'));

        $fanfiction = Fanfiction::where('title', 'Geschichte mit Bild')->first();
        $this->assertNotNull($fanfiction);
        $this->assertNotEmpty($fanfiction->photos);
        Storage::disk('public')->assertExists($fanfiction->photos[0]);
    }

    public function test_vorstand_can_publish_draft_fanfiction(): void
    {
        $fanfiction = Fanfiction::factory()->draft()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'user_id' => $this->member->id,
        ]);

        $this->assertNull($fanfiction->published_at);

        $response = $this->actingAs($this->vorstand)
            ->post(route('admin.fanfiction.publish', $fanfiction));

        $response->assertRedirect(route('admin.fanfiction.index'));
        $response->assertSessionHas('success');

        $fanfiction->refresh();
        $this->assertEquals(FanfictionStatus::Published, $fanfiction->status);
        $this->assertNotNull($fanfiction->published_at);
    }

    public function test_publishing_awards_baxx_to_member_author(): void
    {
        $initialPoints = $this->member->totalPointsForTeam($this->memberTeam);

        $fanfiction = Fanfiction::factory()->draft()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'user_id' => $this->member->id,
        ]);

        $this->actingAs($this->vorstand)
            ->post(route('admin.fanfiction.publish', $fanfiction));

        $this->member->refresh();
        $this->assertEquals($initialPoints + 5, $this->member->totalPointsForTeam($this->memberTeam));
    }

    public function test_publishing_does_not_award_baxx_to_external_author(): void
    {
        $fanfiction = Fanfiction::factory()->draft()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'user_id' => null,
            'author_name' => 'Externer Autor',
        ]);

        $response = $this->actingAs($this->vorstand)
            ->post(route('admin.fanfiction.publish', $fanfiction));

        $response->assertRedirect(route('admin.fanfiction.index'));
        // No exception should be thrown
    }

    public function test_vorstand_can_update_fanfiction(): void
    {
        $fanfiction = Fanfiction::factory()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'title' => 'Original Titel',
        ]);

        $this->actingAs($this->vorstand);

        Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])
            ->set('title', 'Neuer Titel')
            ->call('save')
            ->assertRedirect(route('admin.fanfiction.index'));

        $fanfiction->refresh();
        $this->assertEquals('Neuer Titel', $fanfiction->title);
    }

    public function test_vorstand_can_delete_fanfiction(): void
    {
        $fanfiction = Fanfiction::factory()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
        ]);

        $response = $this->actingAs($this->vorstand)
            ->delete(route('admin.fanfiction.destroy', $fanfiction));

        $response->assertRedirect(route('admin.fanfiction.index'));
        $this->assertSoftDeleted('fanfictions', ['id' => $fanfiction->id]);
    }

    public function test_photo_upload_respects_max_file_size(): void
    {
        Storage::fake('public');

        $this->actingAs($this->vorstand);

        // Create a file larger than 2MB (2048KB)
        $largePhoto = UploadedFile::fake()->create('large.jpg', 3000);

        Livewire::test(FanfictionCreate::class)
            ->set('title', 'Geschichte')
            ->set('content', 'Inhalt...')
            ->set('authorType', 'external')
            ->set('authorName', 'Autor')
            ->set('photos', [$largePhoto])
            ->set('status', 'draft')
            ->call('save')
            ->assertHasErrors('photos.0');
    }

    public function test_photo_upload_respects_max_count(): void
    {
        Storage::fake('public');

        $this->actingAs($this->vorstand);

        $photos = [];
        for ($i = 0; $i < 6; $i++) {
            $photos[] = UploadedFile::fake()->image("test{$i}.jpg", 200, 200);
        }

        Livewire::test(FanfictionCreate::class)
            ->set('title', 'Geschichte')
            ->set('content', 'Inhalt...')
            ->set('authorType', 'external')
            ->set('authorName', 'Autor')
            ->set('photos', $photos)
            ->set('status', 'draft')
            ->call('save')
            ->assertHasErrors('photos');
    }

    public function test_vorstand_can_remove_photos_during_update(): void
    {
        Storage::fake('public');

        // Create a fanfiction with photos
        $photo1 = UploadedFile::fake()->image('photo1.jpg', 400, 300);
        $photo2 = UploadedFile::fake()->image('photo2.jpg', 400, 300);

        $path1 = $photo1->store('fanfiction', 'public');
        $path2 = $photo2->store('fanfiction', 'public');

        $fanfiction = Fanfiction::factory()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'photos' => [$path1, $path2],
        ]);

        Storage::disk('public')->assertExists($path1);
        Storage::disk('public')->assertExists($path2);

        $this->actingAs($this->vorstand);

        // Remove one photo via Livewire
        Livewire::test(FanfictionEdit::class, ['fanfiction' => $fanfiction])
            ->call('togglePhotoRemoval', $path1)
            ->call('save')
            ->assertRedirect(route('admin.fanfiction.index'));

        $fanfiction->refresh();
        $this->assertCount(1, $fanfiction->photos);
        $this->assertContains($path2, $fanfiction->photos);
        $this->assertNotContains($path1, $fanfiction->photos);

        Storage::disk('public')->assertMissing($path1);
        Storage::disk('public')->assertExists($path2);
    }

    public function test_publishing_already_published_fanfiction_returns_info_message(): void
    {
        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
        ]);

        $response = $this->actingAs($this->vorstand)
            ->post(route('admin.fanfiction.publish', $fanfiction));

        $response->assertRedirect(route('admin.fanfiction.index'));
        $response->assertSessionHas('info', 'Diese Fanfiction ist bereits veröffentlicht.');
    }

    public function test_publishing_creates_reward_for_fanfiction(): void
    {
        $fanfiction = Fanfiction::factory()->draft()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
            'user_id' => $this->member->id,
        ]);

        $this->assertNull($fanfiction->reward_id);

        $this->actingAs($this->vorstand)
            ->post(route('admin.fanfiction.publish', $fanfiction));

        $fanfiction->refresh();
        $this->assertNotNull($fanfiction->reward_id);
        $this->assertNotNull($fanfiction->reward);
        $this->assertEquals(config('rewards.fanfiction_default_cost_baxx'), $fanfiction->reward->cost_baxx);
        $this->assertTrue($fanfiction->reward->is_active);
    }

    public function test_deleting_published_fanfiction_removes_reward(): void
    {
        $fanfiction = Fanfiction::factory()->draft()->create([
            'team_id' => $this->memberTeam->id,
            'created_by' => $this->vorstand->id,
        ]);

        // Veröffentlichen, um Reward zu erstellen
        $this->actingAs($this->vorstand)
            ->post(route('admin.fanfiction.publish', $fanfiction));

        $fanfiction->refresh();
        $rewardId = $fanfiction->reward_id;
        $this->assertNotNull($rewardId);

        // Fanfiction löschen
        $this->actingAs($this->vorstand)
            ->delete(route('admin.fanfiction.destroy', $fanfiction));

        $this->assertSoftDeleted('fanfictions', ['id' => $fanfiction->id]);
        $this->assertDatabaseMissing('rewards', ['id' => $rewardId]);
    }
}
