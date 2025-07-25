<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\MitgliedGenehmigtMail;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Admin']);
        return $user;
    }

    private function createApplicant(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
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
            'role' => 'Mitglied',
        ]);
        $this->assertNotNull($applicant->fresh()->mitglied_seit);
        Mail::assertSent(MitgliedGenehmigtMail::class);
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

    public function test_index_displays_dashboard_statistics(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $admin = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($admin, ['role' => 'Admin']);

        $applicant = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($applicant, ['role' => 'Anwärter']);

        $member1 = User::factory()->create(['current_team_id' => $team->id]);
        $member2 = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member1, ['role' => 'Mitglied']);
        $team->users()->attach($member2, ['role' => 'Mitglied']);

        $category = \App\Models\TodoCategory::first() ?? \App\Models\TodoCategory::create(['name' => 'Test', 'slug' => 'test']);
        \App\Models\Todo::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'title' => 'Open',
            'points' => 5,
            'status' => 'open',
            'category_id' => $category->id,
        ]);
        \App\Models\Todo::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'title' => 'Pending',
            'points' => 3,
            'status' => 'completed',
            'category_id' => $category->id,
        ]);

        \App\Models\UserPoint::create(['user_id' => $admin->id, 'team_id' => $team->id, 'todo_id' => 1, 'points' => 5]);
        \App\Models\UserPoint::create(['user_id' => $member1->id, 'team_id' => $team->id, 'todo_id' => 1, 'points' => 10]);
        \App\Models\UserPoint::create(['user_id' => $member2->id, 'team_id' => $team->id, 'todo_id' => 1, 'points' => 7]);

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
}
