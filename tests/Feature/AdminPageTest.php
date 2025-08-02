<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPageTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Admin']);
        return $user;
    }

    private function memberUser(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    public function test_admin_route_denied_for_non_admin(): void
    {
        $member = $this->memberUser();

        $this->actingAs($member)
            ->get('/admin')
            ->assertStatus(403);
    }

    public function test_page_visit_logged_and_admin_can_view(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->get('/dashboard')->assertOk();

        $this->assertDatabaseHas('page_visits', [
            'user_id' => $admin->id,
            'path' => '/dashboard',
        ]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }
}
