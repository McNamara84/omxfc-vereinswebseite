<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\KassenbuchEntryType;
use App\Enums\Role;
use App\Models\KassenbuchEntry;
use App\Models\Team;
use App\Models\User;
use App\Policies\KassenbuchEntryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait KassenbuchEntryPolicyTestFixtures
{
    use RefreshDatabase;

    private KassenbuchEntryPolicy $policy;

    private Team $team;

    private function createUserWithRole(Role $role): User
    {
        $user = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    private function createKassenbuchEntry(User $creator): KassenbuchEntry
    {
        return KassenbuchEntry::create(['team_id' => $this->team->id, 'created_by' => $creator->id, 'buchungsdatum' => now(), 'betrag' => 50.0, 'beschreibung' => 'Test-Eintrag', 'typ' => KassenbuchEntryType::Einnahme->value]);
    }
}
