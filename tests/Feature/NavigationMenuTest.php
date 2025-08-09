<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Team;

class NavigationMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_see_termine_link_in_veranstaltungen_menu(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertSee(route('termine'));
    }

    public function test_guests_see_termine_link_in_navigation(): void
    {
        $response = $this->get('/');

        $response->assertSee(route('termine'));
    }

    public function test_admin_users_see_admin_link_in_navigation_menu(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Admin']);

        $response = $this->actingAs($user)->get('/');

        $response->assertSee(route('admin.index'));
    }

    public function test_non_admin_users_do_not_see_admin_link_in_navigation_menu(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);

        $response = $this->actingAs($user)->get('/');

        $response->assertDontSee(route('admin.index'));
    }
}
