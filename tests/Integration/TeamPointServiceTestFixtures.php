<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamPointService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait TeamPointServiceTestFixtures
{
    use RefreshDatabase;

    private TeamPointService $service;

    private Team $team;

    private function memberWithPoints(int $points = 0): User
    {
        $user = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->team->users()->attach($user, ['role' => Role::Mitglied->value]);
        if ($points > 0) {
            $this->addPoints($user, $points, Carbon::now());
        }

        return $user;
    }

    private function addPoints(User $user, int $points, Carbon $createdAt): void
    {
        $previousTestNow = Carbon::getTestNow();
        Carbon::setTestNow($createdAt);
        $user->incrementTeamPoints($points);
        Carbon::setTestNow($previousTestNow);
    }
}
