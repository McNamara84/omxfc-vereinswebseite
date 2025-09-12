<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\MitgliedGenehmigtMail;
use App\Enums\TodoStatus;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Admin->value]);
        return $user;
    }

    private function createApplicant(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Anwärter']);
        return $user;
    }

    public function test_admin_can_approve_applicant(): void
    {
        Mail::fake();
        $admin = $this->actingAdmin();
        $applicant = $this->createApplicant();

        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('anwaerter.approve', $applicant))
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('team_user', [
            'user_id' => $applicant->id,
            'role' => \App\Enums\Role::Mitglied->value,
        ]);
        $this->assertNotNull($applicant->fresh()->mitglied_seit);
        Mail::assertQueued(MitgliedGenehmigtMail::class);
    }

    public function test_admin_can_reject_applicant(): void
    {
        $admin = $this->actingAdmin();
        $applicant = $this->createApplicant();

        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('anwaerter.reject', $applicant))
            ->assertRedirect('/dashboard');

        $this->assertDatabaseMissing('users', ['id' => $applicant->id]);
        $this->assertDatabaseMissing('team_user', ['user_id' => $applicant->id]);
    }

    public function test_approving_applicant_clears_dashboard_cache(): void
    {
        Mail::fake();
        $admin = $this->actingAdmin();
        $applicant = $this->createApplicant();

        $this->actingAs($admin)->get('/dashboard');

        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('anwaerter.approve', $applicant))
            ->assertRedirect('/dashboard');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertViewHas('memberCount', 3);
        $this->assertFalse($response->viewData('anwaerter')->contains($applicant));
    }

    public function test_index_displays_dashboard_statistics(): void
    {
        $team = Team::membersTeam();
        $admin = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($admin, ['role' => \App\Enums\Role::Admin->value]);

        $applicant = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($applicant, ['role' => 'Anwärter']);

        $member1 = User::factory()->create(['current_team_id' => $team->id]);
        $member2 = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member1, ['role' => \App\Enums\Role::Mitglied->value]);
        $team->users()->attach($member2, ['role' => \App\Enums\Role::Mitglied->value]);

        $category = \App\Models\TodoCategory::first() ?? \App\Models\TodoCategory::create(['name' => 'Test', 'slug' => 'test']);
        $firstTodo = \App\Models\Todo::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'title' => 'Open',
            'points' => 5,
            'status' => TodoStatus::Open->value,
            'category_id' => $category->id,
        ]);
        \App\Models\Todo::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'title' => 'Pending',
            'points' => 3,
            'status' => TodoStatus::Completed->value,
            'category_id' => $category->id,
        ]);

        \App\Models\UserPoint::create(['user_id' => $admin->id, 'team_id' => $team->id, 'todo_id' => $firstTodo->id, 'points' => 5]);
        \App\Models\UserPoint::create(['user_id' => $member1->id, 'team_id' => $team->id, 'todo_id' => $firstTodo->id, 'points' => 10]);
        \App\Models\UserPoint::create(['user_id' => $member2->id, 'team_id' => $team->id, 'todo_id' => $firstTodo->id, 'points' => 7]);

        $book = \App\Models\Book::create(['roman_number' => 1, 'title' => 'B1', 'author' => 'A']);
        \App\Models\Review::create(['team_id' => $team->id, 'user_id' => $admin->id, 'book_id' => $book->id, 'title' => 'T', 'content' => 'X']);
        $review2 = \App\Models\Review::create(['team_id' => $team->id, 'user_id' => $member1->id, 'book_id' => $book->id, 'title' => 'T2', 'content' => 'Y']);
        \App\Models\ReviewComment::create(['review_id' => $review2->id, 'user_id' => $admin->id, 'content' => 'C']);

        $this->actingAs($admin);
        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('memberCount', 4);
        $response->assertViewHas('openTodos', 1);
        $response->assertViewHas('userPoints', 5);
        $response->assertViewHas('completedTodos', 1);
        $response->assertViewHas('pendingVerification', 1);
        $response->assertViewHas('allReviews', 2);
        $response->assertViewHas('myReviews', 1);
        $response->assertViewHas('myReviewComments', 1);
        $topUsers = $response->viewData('topUsers');
        $this->assertEquals($member1->id, $topUsers[0]['id']);
        $anwaerter = $response->viewData('anwaerter');
        $this->assertTrue($anwaerter->contains($applicant));
    }

    public function test_redirect_when_membership_missing(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertRedirect('/');
        $response->assertSessionHas('error');
    }
}
