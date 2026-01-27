<?php

namespace Tests\Feature;

use App\Enums\FanfictionStatus;
use App\Enums\Role;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $response = $this->actingAs($this->vorstand)
            ->post(route('admin.fanfiction.store'), [
                'title' => 'Testgeschichte',
                'content' => 'Dies ist der Inhalt der Testgeschichte...',
                'author_type' => 'member',
                'user_id' => $this->member->id,
                'author_name' => $this->member->name,
                'status' => 'draft',
            ]);

        $response->assertRedirect(route('admin.fanfiction.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('fanfictions', [
            'title' => 'Testgeschichte',
            'user_id' => $this->member->id,
            'status' => FanfictionStatus::Draft->value,
        ]);
    }

    public function test_vorstand_can_create_fanfiction_with_external_author(): void
    {
        $response = $this->actingAs($this->vorstand)
            ->post(route('admin.fanfiction.store'), [
                'title' => 'Externe Geschichte',
                'content' => 'Dies ist eine Geschichte von einem externen Autor.',
                'author_type' => 'external',
                'user_id' => null,
                'author_name' => 'Max Mustermann',
                'status' => 'draft',
            ]);

        $response->assertRedirect(route('admin.fanfiction.index'));

        $this->assertDatabaseHas('fanfictions', [
            'title' => 'Externe Geschichte',
            'user_id' => null,
            'author_name' => 'Max Mustermann',
        ]);
    }

    public function test_vorstand_can_upload_photos_with_fanfiction(): void
    {
        Storage::fake('public');

        $photo = UploadedFile::fake()->image('test.jpg', 800, 600);

        $response = $this->actingAs($this->vorstand)
            ->post(route('admin.fanfiction.store'), [
                'title' => 'Geschichte mit Bild',
                'content' => 'Dies ist der Inhalt der Geschichte mit einem Bild.',
                'author_type' => 'external',
                'author_name' => 'Autor',
                'photos' => [$photo],
                'status' => 'draft',
            ]);

        $response->assertRedirect(route('admin.fanfiction.index'));

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

        $response = $this->actingAs($this->vorstand)
            ->put(route('admin.fanfiction.update', $fanfiction), [
                'title' => 'Neuer Titel',
                'content' => $fanfiction->content,
                'author_type' => 'external',
                'author_name' => $fanfiction->author_name,
            ]);

        $response->assertRedirect(route('admin.fanfiction.index'));

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

        // Create a file larger than 2MB (2048KB)
        $largePhoto = UploadedFile::fake()->create('large.jpg', 3000);

        $response = $this->actingAs($this->vorstand)
            ->post(route('admin.fanfiction.store'), [
                'title' => 'Geschichte',
                'content' => 'Inhalt...',
                'author_type' => 'external',
                'author_name' => 'Autor',
                'photos' => [$largePhoto],
                'status' => 'draft',
            ]);

        $response->assertSessionHasErrors('photos.0');
    }

    public function test_photo_upload_respects_max_count(): void
    {
        Storage::fake('public');

        $photos = [];
        for ($i = 0; $i < 6; $i++) {
            $photos[] = UploadedFile::fake()->image("test{$i}.jpg", 200, 200);
        }

        $response = $this->actingAs($this->vorstand)
            ->post(route('admin.fanfiction.store'), [
                'title' => 'Geschichte',
                'content' => 'Inhalt...',
                'author_type' => 'external',
                'author_name' => 'Autor',
                'photos' => $photos,
                'status' => 'draft',
            ]);

        $response->assertSessionHasErrors('photos');
    }
}
