<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
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
use App\Models\AdminMessage;
use App\Enums\BookType;
use App\Enums\TodoStatus;
use Illuminate\Support\Facades\Mail;
use App\Enums\Role;

class ActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);
    }

    private function actingMember(Role $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);
        return $user;
    }

    public function test_activity_created_for_new_review(): void
    {
        $book = Book::first();

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
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
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
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
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
        $book = Book::first();

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
        $dashboard->assertSeeText('Kommentar zu Meine Rezension von ' . $user->name);
        $dashboard->assertSee('<a href="' . route('reviews.show', $review->book_id) . '" class="text-blue-600 dark:text-blue-400 hover:underline">Meine Rezension</a>', false);
        $dashboard->assertSee('<a href="' . route('profile.view', $user->id) . '" class="text-[#8B0116] hover:underline">', false);
    }

    public function test_dashboard_displays_recent_activities(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => Book::create(['roman_number' => 2, 'title' => 'B', 'author' => 'A', 'type' => BookType::MaddraxDieDunkleZukunftDerErde])->id,
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
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
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
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
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
            'status' => TodoStatus::Open->value,
        ]);

        $this->post(route('todos.assign', $todo));

        $todo->refresh();
        $this->assertSame(TodoStatus::Assigned, $todo->status);

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
        $admin = $this->actingMember(Role::Admin);
        $category = \App\Models\TodoCategory::create(['name' => 'Test2', 'slug' => 'test2']);
        $todo = \App\Models\Todo::create([
            'team_id' => $assignee->currentTeam->id,
            'created_by' => $admin->id,
            'assigned_to' => $assignee->id,
            'title' => 'Challenge',
            'points' => 5,
            'category_id' => $category->id,
            'status' => TodoStatus::Completed->value,
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('todos.verify', $todo));

        $todo->refresh();
        $this->assertSame(TodoStatus::Verified, $todo->status);

        $this->assertDatabaseHas('activities', [
            'user_id' => $assignee->id,
            'subject_type' => \App\Models\Todo::class,
            'subject_id' => $todo->id,
            'action' => 'completed',
        ]);
    }

    public function test_activity_created_when_member_application_is_approved(): void
    {
        Mail::fake();
        $admin = $this->actingMember(Role::Admin);
        $team = $admin->currentTeam;
        $anwaerter = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($anwaerter, ['role' => Role::Anwaerter->value]);

        $this->actingAs($admin)->post(route('anwaerter.approve', $anwaerter));

        $this->assertDatabaseHas('activities', [
            'user_id' => $admin->id,
            'subject_type' => User::class,
            'subject_id' => $anwaerter->id,
            'action' => 'member_approved',
        ]);

        $dashboard = $this->get('/dashboard');
        $dashboard->assertSeeText('Wir begrüßen unser neues Mitglied ' . $anwaerter->name);
    }

    public function test_dashboard_shows_fallback_when_activity_subject_missing(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $category = TodoCategory::create(['name' => 'Fallback', 'slug' => 'fallback']);
        $todo = Todo::create([
            'team_id' => $user->currentTeam->id,
            'created_by' => $user->id,
            'title' => 'Vergängliche Challenge',
            'points' => 3,
            'category_id' => $category->id,
            'status' => TodoStatus::Open->value,
        ]);

        $this->post(route('todos.assign', $todo));

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'subject_type' => Todo::class,
            'subject_id' => $todo->id,
            'action' => 'accepted',
        ]);

        $todo->delete();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Gelöschter Eintrag – nicht mehr verfügbar');
    }

    public function test_dashboard_shows_fallback_when_review_subject_soft_deleted(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::first();

        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'Kurzlebige Rezension',
            'content' => str_repeat('C', 160),
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => Review::class,
            'subject_id' => $review->id,
        ]);

        $review->delete();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Gelöschter Eintrag – nicht mehr verfügbar');
    }

    public function test_dashboard_handles_review_comment_with_deleted_review(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $book = Book::first();

        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'Verwaiste Rezension',
            'content' => str_repeat('B', 150),
        ]);

        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'content' => 'Schade, dass sie weg ist.',
        ]);

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => ReviewComment::class,
            'subject_id' => $comment->id,
        ]);

        $review->delete();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Kommentar – Bezug nicht mehr verfügbar');
    }

    public function test_dashboard_handles_missing_admin_message_subject_without_delete_form(): void
    {
        $admin = $this->actingMember(Role::Admin);
        $this->actingAs($admin);

        $message = AdminMessage::create([
            'user_id' => $admin->id,
            'message' => 'Bitte beachtet die Regeln.',
        ]);

        Activity::create([
            'user_id' => $admin->id,
            'subject_type' => AdminMessage::class,
            'subject_id' => $message->id,
        ]);

        $message->delete();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Gelöschter Eintrag – nicht mehr verfügbar');
        $response->assertDontSee('Nachricht löschen?');
    }

    public function test_dashboard_handles_missing_member_subject(): void
    {
        $admin = $this->actingMember(Role::Admin);
        $this->actingAs($admin);

        $newMember = User::factory()->create(['current_team_id' => $admin->currentTeam->id]);

        Activity::create([
            'user_id' => $admin->id,
            'subject_type' => User::class,
            'subject_id' => $newMember->id,
            'action' => 'member_approved',
        ]);

        $newMember->delete();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Gelöschter Eintrag – nicht mehr verfügbar');
        $response->assertDontSee('Wir begrüßen unser neues Mitglied');
    }
}
