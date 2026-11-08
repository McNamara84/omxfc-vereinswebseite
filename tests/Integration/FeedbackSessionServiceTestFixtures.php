<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

trait FeedbackSessionServiceTestFixtures
{
    use RefreshDatabase;

    private function member(Role $role): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    private function newSession(int $number): Store
    {
        $id = str_pad((string) $number, 40, '0', STR_PAD_LEFT);
        $session = new Store('website-feedback-test', new ArraySessionHandler(120), $id);
        $session->start();

        return $session;
    }
}
