<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\UserPoint;
use Illuminate\Support\Str;

class ProfileViewControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    private function createTodoWithPoints(User $user, TodoCategory $category, int $points): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $todo = Todo::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'title' => 'Task',
            'points' => $points,
            'category_id' => $category->id,
        ]);

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'todo_id' => $todo->id,
            'points' => $points,
        ]);
    }

    public function test_view_own_profile_shows_details(): void
    {
        $user = $this->createMember();
        $this->actingAs($user);

        $response = $this->get("/profile/{$user->id}");

        $response->assertOk();
        $response->assertViewHas('isOwnProfile', true);
        $response->assertViewHas('memberRole', 'Mitglied');
        $response->assertViewHas('canViewDetails', true);
    }

    public function test_view_other_member_without_privilege_hides_contact(): void
    {
        $viewer = $this->createMember();
        $target = $this->createMember();
        $this->actingAs($viewer);

        $response = $this->get("/profile/{$target->id}");

        $response->assertOk();
        $response->assertViewHas('isOwnProfile', false);
        $response->assertViewHas('memberRole', 'Mitglied');
        $response->assertViewHas('canViewDetails', false);
    }

    public function test_view_other_member_with_admin_role_shows_contact(): void
    {
        $admin = $this->createMember('Admin');
        $target = $this->createMember();
        $this->actingAs($admin);

        $response = $this->get("/profile/{$target->id}");

        $response->assertOk();
        $response->assertViewHas('canViewDetails', true);
    }

    public function test_redirect_when_user_not_in_team(): void
    {
        $viewer = $this->createMember();
        $otherTeam = Team::factory()->create(['personal_team' => false, 'name' => 'Other']);
        $target = User::factory()->create(['current_team_id' => $otherTeam->id]);
        $otherTeam->users()->attach($target, ['role' => 'Mitglied']);
        $this->actingAs($viewer);

        $response = $this->get("/profile/{$target->id}");

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error');
    }

    public function test_redirect_when_current_user_membership_missing(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $viewer = User::factory()->create(['current_team_id' => $team->id]);
        $target = $this->createMember();
        $this->actingAs($viewer);

        $response = $this->get("/profile/{$target->id}");

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error');
    }

    public function test_points_and_badges_are_calculated(): void
    {
        $admin = $this->createMember('Admin');
        $member = $this->createMember();

        $general = TodoCategory::create(['name' => 'General', 'slug' => Str::slug('General')]);
        $maddraxikon = TodoCategory::create(['name' => 'AG Maddraxikon', 'slug' => Str::slug('AG Maddraxikon')]);

        $this->createTodoWithPoints($member, $general, 5);
        $this->createTodoWithPoints($member, $maddraxikon, 3);

        $this->actingAs($admin);

        $response = $this->get("/profile/{$member->id}");

        $response->assertOk();
        $response->assertViewHas('userPoints', 8);
        $response->assertViewHas('completedTasks', 2);
        $response->assertViewHas('categoryPoints', [
            'General' => 5,
            'AG Maddraxikon' => 3,
        ]);
        $badges = $response->viewData('badges');
        $this->assertCount(2, $badges);
        $this->assertEquals('Ersthelfer', $badges[0]['name']);
        $this->assertEquals('Retrologe (Stufe 1)', $badges[1]['name']);
    }

    public function test_member_team_missing_results_in_zero_points(): void
    {
        $admin = $this->createMember('Admin');
        $member = $this->createMember();
        $admin->currentTeam->update(['name' => 'Something']);

        $this->actingAs($admin);

        $response = $this->get("/profile/{$member->id}");

        $response->assertOk();
        $response->assertViewHas('userPoints', 0);
        $response->assertViewHas('categoryPoints', []);
        $response->assertViewHas('badges', []);
    }
}
