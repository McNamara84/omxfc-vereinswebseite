<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Book;
use App\Models\Review;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\Activity;
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\ReviewComment;

class ActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::disk('private')->put('maddrax.json', json_encode([
            ['nummer' => 1, 'titel' => 'Roman1']
        ]));
    }

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    public function test_activity_created_for_new_review(): void
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
            'content' => str_repeat('A', 140),
        ]);

        $response->assertRedirect(route('reviews.show', $book, false));
        $review = Review::first();
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => Review::class,
            'subject_id' => $review->id,
        ]);
    }

    public function test_activity_created_for_new_book_offer(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post('/romantauschboerse/angebot-speichern', [
            'book_number' => 1,
            'condition' => 'neu',
        ]);

        $response->assertRedirect(route('romantausch.index', [], false));
        $offer = BookOffer::first();
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => BookOffer::class,
            'subject_id' => $offer->id,
        ]);
    }

    public function test_activity_created_for_new_book_request(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post('/romantauschboerse/anfrage-speichern', [
            'book_number' => 1,
            'condition' => 'neu',
        ]);

        $response->assertRedirect(route('romantausch.index', [], false));
        $requestModel = BookRequest::first();
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => BookRequest::class,
            'subject_id' => $requestModel->id,
        ]);
    }

    public function test_activity_created_for_new_review_comment(): void
    {
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);

        $user = $this->actingMember();
        $this->actingAs($user);

        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'Meine Rezension',
            'content' => str_repeat('A', 140),
        ]);

        $response = $this->post(route('reviews.comments.store', $review), [
            'content' => 'Tolles Buch!',
        ]);

        $response->assertRedirect();
        $comment = ReviewComment::first();
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => ReviewComment::class,
            'subject_id' => $comment->id,
        ]);

        $dashboard = $this->get('/dashboard');
        $dashboard->assertOk();
        $dashboard->assertSee('Kommentar zu <a href="' . route('reviews.show', $review->book_id) . '" class="text-blue-600 dark:text-blue-400 hover:underline">Meine Rezension</a> von <a href="' . route('profile.view', $user->id) . '" class="text-[#8B0116] hover:underline">' . $user->name . '</a>', false);
    }

    public function test_dashboard_displays_recent_activities(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => Book::create(['roman_number' => 1, 'title' => 'B', 'author' => 'A'])->id,
            'title' => 'R',
            'content' => str_repeat('A', 140),
        ]);
        Activity::create([
            'user_id' => $user->id,
            'subject_type' => Review::class,
            'subject_id' => $review->id,
            'created_at' => now()->subMinutes(2),
        ]);
        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);
        Activity::create([
            'user_id' => $user->id,
            'subject_type' => BookOffer::class,
            'subject_id' => $offer->id,
            'created_at' => now()->subMinute(),
        ]);
        $requestModel = BookRequest::create([
            'user_id' => $user->id,
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);
        Activity::create([
            'user_id' => $user->id,
            'subject_type' => BookRequest::class,
            'subject_id' => $requestModel->id,
        ]);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $this->assertCount(3, $response->viewData('activities'));
    }

    public function test_activity_created_when_challenge_is_accepted(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $category = \App\Models\TodoCategory::create(['name' => 'Test', 'slug' => 'test']);
        $todo = \App\Models\Todo::create([
            'team_id' => $user->currentTeam->id,
            'created_by' => $user->id,
            'title' => 'Challenge',
            'points' => 5,
            'category_id' => $category->id,
            'status' => 'open',
        ]);

        $this->post(route('todos.assign', $todo));

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => \App\Models\Todo::class,
            'subject_id' => $todo->id,
            'action' => 'accepted',
        ]);
    }

    public function test_activity_created_when_challenge_is_verified(): void
    {
        $assignee = $this->actingMember();
        $admin = $this->actingMember('Admin');
        $category = \App\Models\TodoCategory::create(['name' => 'Test2', 'slug' => 'test2']);
        $todo = \App\Models\Todo::create([
            'team_id' => $assignee->currentTeam->id,
            'created_by' => $admin->id,
            'assigned_to' => $assignee->id,
            'title' => 'Challenge',
            'points' => 5,
            'category_id' => $category->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('todos.verify', $todo));

        $this->assertDatabaseHas('activities', [
            'user_id' => $assignee->id,
            'subject_type' => \App\Models\Todo::class,
            'subject_id' => $todo->id,
            'action' => 'completed',
        ]);
    }
}
