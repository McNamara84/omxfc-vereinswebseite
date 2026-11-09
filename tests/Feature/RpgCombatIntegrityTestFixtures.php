<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RpgCombatFixtures;

trait RpgCombatIntegrityTestFixtures
{
    use RefreshDatabase, RpgCombatFixtures;

    private function assertChallengeRolledBack(): void
    {
        foreach (['rpg_combats', 'rpg_combat_participants', 'rpg_combat_character_locks', 'rpg_combat_decisions', 'rpg_combat_events', 'rpg_combat_milestones', 'rpg_combat_deliveries', 'rpg_combat_commands'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
