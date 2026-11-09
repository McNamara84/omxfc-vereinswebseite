<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait HoerbuchLivewireTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function createAgFanhoerbuchTeam(User $leader): Team
    {
        $ag = Team::factory()->create(['user_id' => $leader->id, 'personal_team' => false, 'name' => 'AG Fanhörbücher']);
        $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);

        return $ag;
    }

    private function actingAgMember(): User
    {
        $leader = $this->actingMember();
        $ag = $this->createAgFanhoerbuchTeam($leader);
        $member = $this->actingMember();
        $ag->users()->attach($member, ['role' => Role::Mitwirkender->value]);

        return $member;
    }

    private function actingAgLeader(): User
    {
        $leader = $this->actingMember();
        $this->createAgFanhoerbuchTeam($leader);

        return $leader;
    }
}
