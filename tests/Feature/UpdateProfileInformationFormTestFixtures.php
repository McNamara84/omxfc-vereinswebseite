<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait UpdateProfileInformationFormTestFixtures
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->create($this->userAttributes());
    }

    private function createMember(Role $role = Role::Mitglied, array $attributes = []): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(array_merge($this->userAttributes(), ['current_team_id' => $team->id], $attributes));
        $team->users()->attach($user, ['role' => $role->value]);

        return $user->refresh();
    }

    private function userAttributes(): array
    {
        return ['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'strasse' => 'Teststr', 'hausnummer' => '1', 'plz' => '12345', 'stadt' => 'Teststadt', 'land' => 'Deutschland', 'telefon' => '0123', 'mitgliedsbeitrag' => 12.0, 'alias' => 'Maxi', 'author_aliases' => ['Max Power'], 'nextcloud_username' => 'MaxCloud'];
    }

    private function profileFormData(array $overrides = []): array
    {
        return array_merge($this->userAttributes(), ['alias' => null, 'author_aliases' => [''], 'contact_release_email' => false, 'contact_release_phone' => false, 'contact_release_maddraxikon' => false, 'contact_release_nextcloud' => false, 'nextcloud_username' => null], $overrides);
    }
}
