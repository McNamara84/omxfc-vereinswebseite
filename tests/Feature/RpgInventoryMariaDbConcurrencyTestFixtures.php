<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgCharacter;
use App\Services\RpgInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\RpgInventoryFixtures;
use Tests\Support\RpgMariaDbParallel;
use Tests\TestCase;

abstract class RpgInventoryMariaDbConcurrencyTestFixtures extends TestCase
{
    use RefreshDatabase, RpgInventoryFixtures;
    use RpgMariaDbParallel;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Separate MariaDB-Konfiguration erforderlich.');
        }
        if (getenv('DB_DATABASE') !== 'omxfc_rpg_test') {
            throw new RuntimeException('Only the isolated omxfc_rpg_test database may be used.');
        }
        parent::setUp();
        $this->progressionFixtures();
        $this->inventoryInitialize();
    }

    protected function action(RpgCharacter $character, string $kind, array $changes): array
    {
        $input = $this->inventoryInput($kind, $changes, $character);
        $input['preview_hash'] = app(RpgInventoryService::class)->preview($this->leader, $character->id, $input)['preview_hash'];

        return ['action' => 'inventory', 'actor' => $this->leader->id, 'character' => $character->id, 'input' => $input];
    }
}
