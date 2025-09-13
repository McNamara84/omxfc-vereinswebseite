<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\Book;
use App\Enums\BookType;
use App\Mail\NewReviewNotification;
use Illuminate\Support\Facades\Mail;
use App\Models\Review;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class RezensionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    public function test_store_creates_review_and_notifies_author(): void
    {
        Mail::fake();

        $author = User::factory()->create(['notify_new_review' => true]);
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => $author->name,
        ]);

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post(route('reviews.store', $book), [
            'title' => 'Tolle Rezension',
            'content' => str_repeat('A', 140),
        ]);

        $response->assertRedirect(route('reviews.show', $book, false));
        $this->assertDatabaseHas('reviews', [
            'book_id' => $book->id,
            'title' => 'Tolle Rezension',
        ]);
        Mail::assertQueued(NewReviewNotification::class);
    }

    public function test_store_strips_heading_markers_before_validation(): void
    {
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post(route('reviews.store', $book), [
            'title' => 'Tolle Rezension',
            'content' => '# ' . str_repeat('A', 140),
        ]);

        $response->assertRedirect(route('reviews.show', $book, false));
        $this->assertDatabaseHas('reviews', [
            'book_id' => $book->id,
            'content' => str_repeat('A', 140),
        ]);
    }

    public function test_store_rejects_content_too_short_after_stripping_heading_markers(): void
    {
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->from(route('reviews.create', $book))
            ->post(route('reviews.store', $book), [
                'title' => 'Tolle Rezension',
                'content' => '# ' . str_repeat('A', 139),
            ]);

        $response->assertRedirect(route('reviews.create', $book, false));
        $response->assertSessionHasErrors('content');
    }

    public function test_index_requires_valid_role(): void
    {
        $user = $this->actingMember('Gast');
        $this->actingAs($user);

        $this->get('/rezensionen')->assertStatus(403);
    }

    public function test_index_displays_total_review_count_per_cycle(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('maddrax.json', json_encode([
            ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'],
            ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1'],
        ]));

        $book1 = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        $book2 = Book::create(['roman_number' => 2, 'title' => 'Beta', 'author' => 'B']);

        $teamId = Team::membersTeam()->id;
        Review::create([
            'team_id' => $teamId,
            'user_id' => User::factory()->create()->id,
            'book_id' => $book1->id,
            'title' => 'R1',
            'content' => str_repeat('A', 140),
        ]);
        Review::create([
            'team_id' => $teamId,
            'user_id' => User::factory()->create()->id,
            'book_id' => $book2->id,
            'title' => 'R2',
            'content' => str_repeat('B', 140),
        ]);

        $user = $this->actingMember();
        $this->actingAs($user);

        $this->get('/rezensionen')
            ->assertOk()
            ->assertSee('(2 Rezensionen)');
    }

    public function test_index_shows_hardcover_books(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('maddrax.json', json_encode([
            ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Z1'],
            ['nummer' => 2, 'titel' => 'Roman2', 'zyklus' => 'Z1'],
        ]));

        $book = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
        Book::create([
            'roman_number' => 2,
            'title' => 'Hardcover Beta',
            'author' => 'B',
            'type' => BookType::MaddraxHardcover,
        ]);

        $user = $this->actingMember();
        $this->actingAs($user);

        $this->get('/rezensionen')
            ->assertOk()
            ->assertSee($book->title)
            ->assertSee('Hardcover Beta');
    }

    public function test_index_shows_mission_mars_books(): void
    {
        $path = storage_path('app/private/maddrax.json');
        $original = file_get_contents($path);

        try {
            file_put_contents($path, json_encode([
                ['nummer' => 1, 'titel' => 'Roman1', 'zyklus' => 'Wandler'],
            ]));

            $book = Book::create(['roman_number' => 1, 'title' => 'Alpha', 'author' => 'A']);
            Book::create([
                'roman_number' => 2,
                'title' => 'Mission Mars Beta',
                'author' => 'B',
                'type' => BookType::MissionMars,
            ]);

            $user = $this->actingMember();
            $this->actingAs($user);

            $this->get('/rezensionen')
                ->assertOk()
                ->assertSee($book->title)
                ->assertSee('Mission Mars Beta');
        } finally {
            file_put_contents($path, $original);
        }
    }

    public function test_show_redirects_when_user_has_no_permission(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $other = $this->actingMember('Mitglied');
        Review::create([
            'team_id' => $other->currentTeam->id,
            'user_id' => $other->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('B', 140),
        ]);

        $response = $this->get(route('reviews.show', $book));
        $response->assertRedirect(route('reviews.create', $book, false));
    }

    public function test_edit_shows_form_for_author(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('C', 140),
        ]);

        $response = $this->get(route('reviews.edit', $review));
        $response->assertOk();
        $response->assertViewIs('reviews.edit');
    }

    public function test_admin_can_update_and_delete_review(): void
    {
        $admin = $this->actingMember('Admin');
        $this->actingAs($admin);

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $member = $this->actingMember();
        $review = Review::create([
            'team_id' => $admin->currentTeam->id,
            'user_id' => $member->id,
            'book_id' => $book->id,
            'title' => 'Old',
            'content' => str_repeat('D', 140),
        ]);

        $updateResponse = $this->put(route('reviews.update', $review), [
            'title' => 'New',
            'content' => str_repeat('E', 140),
        ]);
        $updateResponse->assertRedirect(route('reviews.show', $book, false));
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'title' => 'New',
        ]);

        $deleteResponse = $this->from('/rezensionen')->delete(route('reviews.destroy', $review));
        $deleteResponse->assertRedirect('/rezensionen');
        $this->assertSoftDeleted('reviews', ['id' => $review->id]);
    }

    public function test_update_validation_errors(): void
    {
        $admin = $this->actingMember('Admin');
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Test',
            'author' => 'Autor',
        ]);
        $review = Review::create([
            'team_id' => Team::membersTeam()->id,
            'user_id' => $admin->id,
            'book_id' => $book->id,
            'title' => 'Old',
            'content' => str_repeat('A', 140),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('reviews.edit', $review))
            ->put(route('reviews.update', $review), [
                'title' => '',
                'content' => 'short',
            ]);

        $response->assertRedirect(route('reviews.edit', $review, false));
        $response->assertSessionHasErrors(['title', 'content']);
    }

    public function test_show_displays_review_for_author(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('A', 140),
        ]);

        $response = $this->get(route('reviews.show', $book));
        $response->assertOk();
        $response->assertViewIs('reviews.show');
        $response->assertViewHas('reviews', function ($c) use ($review) {
            return $c->first()->is($review);
        });
    }

    public function test_create_redirects_when_review_exists(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('B', 140),
        ]);

        $this->get(route('reviews.create', $book))
            ->assertRedirect(route('reviews.show', $book, false));
    }

    public function test_store_fails_when_review_exists(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('C', 140),
        ]);

        $this->post(route('reviews.store', $book), [
            'title' => 'T',
            'content' => str_repeat('D', 140),
        ])->assertStatus(403);
    }

    public function test_update_forbidden_for_non_author(): void
    {
        $owner = $this->actingMember();
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $review = Review::create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'book_id' => $book->id,
            'title' => 'Old',
            'content' => str_repeat('E', 140),
        ]);

        $this->actingAs($this->actingMember());

        $this->put(route('reviews.update', $review), [
            'title' => 'New',
            'content' => str_repeat('F', 140),
        ])->assertStatus(403);
    }

    public function test_update_strips_heading_markers_before_validation(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('E', 140),
        ]);

        $response = $this->put(route('reviews.update', $review), [
            'title' => 'R',
            'content' => '# ' . str_repeat('A', 140),
        ]);

        $response->assertRedirect(route('reviews.show', $book, false));
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'content' => str_repeat('A', 140),
        ]);
    }

    public function test_update_rejects_content_too_short_after_stripping_heading_markers(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('E', 140),
        ]);

        $response = $this->from(route('reviews.edit', $review))
            ->put(route('reviews.update', $review), [
                'title' => 'R',
                'content' => '# ' . str_repeat('A', 139),
            ]);

        $response->assertRedirect(route('reviews.edit', $review, false));
        $response->assertSessionHasErrors('content');
    }

    public function test_destroy_forbidden_for_non_author(): void
    {
        $owner = $this->actingMember();
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        $review = Review::create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'book_id' => $book->id,
            'title' => 'Old',
            'content' => str_repeat('E', 140),
        ]);

        $this->actingAs($this->actingMember());

        $this->delete(route('reviews.destroy', $review))
            ->assertStatus(403);
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'deleted_at' => null]);
    }

    public function test_show_displays_update_information_when_review_was_edited(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);

        Carbon::setTestNow(Carbon::create(2025, 7, 16, 17, 0));
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('A', 140),
        ]);

        Carbon::setTestNow(Carbon::create(2025, 7, 17, 17, 30));
        $review->update(['content' => str_repeat('B', 140)]);
        Carbon::setTestNow();

        $response = $this->get(route('reviews.show', $book));
        $response->assertSee('am 16.07.2025 17:00 Uhr', false);
        $response->assertSee('geändert am 17.07.2025 um 17:30 Uhr', false);
    }

    public function test_show_does_not_display_update_information_when_review_not_edited(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);

        Carbon::setTestNow(Carbon::create(2025, 7, 16, 17, 0));
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'R',
            'content' => str_repeat('A', 140),
        ]);
        Carbon::setTestNow();

        $response = $this->get(route('reviews.show', $book));
        $response->assertSee('am 16.07.2025 17:00 Uhr', false);
        $response->assertDontSee('geändert am');
    }
}
