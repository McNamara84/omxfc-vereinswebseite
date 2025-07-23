<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\Book;
use App\Mail\NewReviewNotification;
use Illuminate\Support\Facades\Mail;
use App\Models\Review;

class RezensionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
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
        Mail::assertSent(NewReviewNotification::class);
    }

    public function test_index_requires_valid_role(): void
    {
        $user = $this->actingMember('Gast');
        $this->actingAs($user);

        $this->get('/rezensionen')->assertStatus(403);
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
}
