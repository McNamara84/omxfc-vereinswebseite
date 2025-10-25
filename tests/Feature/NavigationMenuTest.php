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

    public function test_authenticated_users_see_satzung_between_protokolle_and_kassenbuch(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertSeeInOrder([
            '>Protokolle<',
            '>Satzung<',
            '>Kassenbuch<',
        ], false);
    }

    public function test_guests_see_termine_link_in_navigation(): void
    {
        $response = $this->get('/');

        $response->assertSee(route('termine'));
    }

    public function test_admin_users_see_admin_menu_with_statistik_link(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Admin->value]);

        $response = $this->actingAs($user)->get('/');

        $response->assertSee(route('admin.statistiken.index'));
        $response->assertSee('Admin');
        $response->assertSee('admin-button');
        $response->assertSee('admin-mobile-button');
    }

    public function test_non_admin_users_do_not_see_admin_menu(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);

        $response = $this->actingAs($user)->get('/');

        $response->assertDontSee(route('admin.statistiken.index'));
        $response->assertDontSee('admin-button');
        $response->assertDontSee('admin-mobile-button');
    }
    public function test_admin_users_do_not_see_hoerbuch_create_link_in_navigation_menu(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Admin->value]);

        $response = $this->actingAs($user)->get('/');
        $response->assertDontSee(route('hoerbuecher.create'));
    }

    public function test_non_admin_users_do_not_see_hoerbuch_create_link(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);

        $response = $this->actingAs($user)->get('/');

        $response->assertDontSee(route('hoerbuecher.create'));
    }
}
