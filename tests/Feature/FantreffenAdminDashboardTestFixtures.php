<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use App\Models\VeranstaltungsMerchartikel;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait FantreffenAdminDashboardTestFixtures
{
    use RefreshDatabase;

    protected Veranstaltung $veranstaltung;

    protected function dashboardRoute(): string
    {
        return route('admin.veranstaltungen.anmeldungen', ['veranstaltung' => $this->veranstaltung]);
    }

    protected function createUserWithRole(Role $role): User
    {
        $team = Team::membersTeam() ?? Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $team->id]);
        // Attach user to team with role via pivot table
        $user->teams()->attach($team->id, ['role' => $role->value, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    protected function createMerchartikel(string $bezeichnung = 'T-Shirt', float $preis = 25.0, array $varianten = []): VeranstaltungsMerchartikel
    {
        $artikel = $this->veranstaltung->merchartikel()->create(['bezeichnung' => $bezeichnung, 'preis' => $preis, 'sort_order' => 0, 'is_active' => true]);
        foreach ($varianten as $index => $variante) {
            $artikel->varianten()->create(['bezeichnung' => $variante, 'sort_order' => $index, 'is_active' => true]);
        }

        return $artikel;
    }
}
