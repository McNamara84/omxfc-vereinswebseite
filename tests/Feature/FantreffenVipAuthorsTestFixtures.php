<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait FantreffenVipAuthorsTestFixtures
{
    use RefreshDatabase;

    protected Veranstaltung $veranstaltung;

    protected function vipAuthorsRoute(): string
    {
        return route('admin.veranstaltungen.vip-authors', ['veranstaltung' => $this->veranstaltung]);
    }

    protected function publicEventRoute(): string
    {
        return route('veranstaltungen.show', ['veranstaltung' => $this->veranstaltung]);
    }

    protected function createUserWithRole(Role $role): User
    {
        $team = Team::membersTeam() ?? Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $user->teams()->attach($team->id, ['role' => $role->value, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }
}
