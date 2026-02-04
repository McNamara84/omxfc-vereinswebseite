<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Providers\AuthServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the gate definitions are registered
        $this->app->register(AuthServiceProvider::class);
    }

    public function test_owner_can_access_dashboard(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->assertTrue(Gate::forUser($user)->allows('access-dashboard'));
    }

    public function test_member_with_read_permission_can_access_dashboard(): void
    {
        $team = Team::factory()->create(['personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        $this->assertTrue(Gate::forUser($user)->allows('access-dashboard'));
    }

    public function test_member_without_read_permission_cannot_access_dashboard(): void
    {
        $team = Team::factory()->create(['personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Anwaerter->value]);

        $this->assertFalse(Gate::forUser($user)->allows('access-dashboard'));
    }

    public function test_user_without_team_membership_cannot_access_dashboard(): void
    {
        $team = Team::factory()->create(['personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $team->id]);

        $this->assertFalse(Gate::forUser($user)->allows('access-dashboard'));
    }
}
