<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

trait ActivityFeedTestFixtures
{
    use RefreshDatabase;

    private function actingMember(Role $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    private function dashboardWithLoadedFeed(): TestResponse
    {
        // A preceding Livewire interaction flushes its test configuration, so
        // enable resolved rendering immediately before each content assertion.
        // DashboardActivityFeedTest separately covers the deferred response.
        Livewire::withoutLazyLoading();

        return $this->get('/dashboard');
    }
}
