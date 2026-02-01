<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;

/**
 * Trait für Tests, die User mit bestimmten Rollen im Mitglieder-Team benötigen.
 *
 * Bietet verschiedene Helper-Methoden zur Erstellung von Usern:
 * - createUserWithRole(): Erstellt User ohne Login
 * - actingMember(): Erstellt User und loggt ihn ein
 * - actingMemberWithPoints(): Erstellt User mit Punkten und loggt ihn ein
 * - actingAdmin/actingVorstand/actingKassenwart: Shortcuts für spezifische Rollen
 *
 * WICHTIG bei Mocking: Wenn du Services wie MembersTeamProvider mocken möchtest,
 * muss der Mock VOR dem Aufruf von actingAs() oder actingMember() registriert werden.
 * Laravel's Service Container bindet Services beim ersten Request, und ein Mock
 * muss vorher registriert sein. Beispiel:
 *
 * ```php
 * // Richtig:
 * $this->mock(MembersTeamProvider::class, fn($m) => ...);
 * $user = $this->createUserWithRole(Role::Mitglied);
 * $this->actingAs($user);
 *
 * // Falsch (Mock wird ignoriert):
 * $this->actingMember();
 * $this->mock(MembersTeamProvider::class, fn($m) => ...);
 * ```
 */
trait CreatesUserWithRole
{
    /**
     * Erstellt einen User mit der angegebenen Rolle im Mitglieder-Team (ohne Login).
     */
    protected function createUserWithRole(Role|string $role): User
    {
        $team = Team::membersTeam();

        if (! $team) {
            $team = Team::factory()->create(['name' => 'Mitglieder']);
        }

        $roleValue = $role instanceof Role ? $role->value : $role;
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $roleValue]);

        return $user->refresh();
    }

    /**
     * Erstellt einen User mit der angegebenen Rolle und loggt ihn ein (actingAs).
     *
     * @param  Role|string  $role  Die Rolle des Users (Standard: Mitglied)
     * @param  array  $attributes  Zusätzliche User-Attribute
     */
    protected function actingMember(Role|string $role = Role::Mitglied, array $attributes = []): User
    {
        $team = Team::membersTeam();

        if (! $team) {
            $team = Team::factory()->create(['name' => 'Mitglieder']);
        }

        $roleValue = $role instanceof Role ? $role->value : $role;

        $user = User::factory()->create(
            array_merge(['current_team_id' => $team->id], $attributes)
        );
        $team->users()->attach($user, ['role' => $roleValue]);

        $this->actingAs($user->refresh());

        return $user;
    }

    /**
     * Erstellt einen User mit Punkten und loggt ihn ein.
     *
     * @param  int  $points  Anzahl der Punkte
     * @param  Role|string  $role  Die Rolle des Users (Standard: Mitglied)
     */
    protected function actingMemberWithPoints(int $points, Role|string $role = Role::Mitglied): User
    {
        $user = $this->actingMember($role);

        if ($points > 0) {
            $user->incrementTeamPoints($points);
        }

        return $user;
    }

    /**
     * Erstellt einen Admin-User und loggt ihn ein.
     */
    protected function actingAdmin(): User
    {
        return $this->actingMember(Role::Admin);
    }

    /**
     * Erstellt einen Vorstand-User und loggt ihn ein.
     */
    protected function actingVorstand(): User
    {
        return $this->actingMember(Role::Vorstand);
    }

    /**
     * Erstellt einen Kassenwart-User und loggt ihn ein.
     */
    protected function actingKassenwart(): User
    {
        return $this->actingMember(Role::Kassenwart);
    }

    /**
     * Erstellt einen Ehrenmitglied-User und loggt ihn ein.
     */
    protected function actingEhrenmitglied(): User
    {
        return $this->actingMember(Role::Ehrenmitglied);
    }

    /**
     * Erstellt ein einfaches Mitglied (keine besondere Rolle) und loggt es ein.
     */
    protected function actingSimpleMember(): User
    {
        return $this->actingMember(Role::Mitglied);
    }
}
