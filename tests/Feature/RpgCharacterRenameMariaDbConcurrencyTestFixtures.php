<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgCharacter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RpgCharacterRenameFixtures;
use Tests\Support\RpgCombatFixtures;
use Tests\Support\RpgInventoryFixtures;
use Tests\Support\RpgMariaDbParallel;
use Tests\TestCase;

abstract class RpgCharacterRenameMariaDbConcurrencyTestFixtures extends TestCase
{
    use RefreshDatabase, RpgCharacterRenameFixtures, RpgCombatFixtures, RpgInventoryFixtures, RpgMariaDbParallel;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Separate MariaDB-Konfiguration erforderlich.');
        }
        if (getenv('DB_DATABASE') !== 'omxfc_rpg_test') {
            throw new \RuntimeException('Only the isolated omxfc_rpg_test database may be used.');
        }
        parent::setUp();
        $this->combatFixtures();
        $this->player->incrementTeamPoints(25);
    }

    protected function renameAction(string $name = 'Birka', ?RpgCharacter $character = null): array
    {
        $character ??= $this->character;

        return ['action' => 'rename', 'actor' => $this->player->id, 'character' => $character->id, 'input' => $this->renameInput($name, $character)];
    }
}
