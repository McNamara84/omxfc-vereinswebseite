<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgItemType;
use App\Models\RpgItemVersion;
use App\Services\RpgItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\RpgInventoryFixtures;
use Tests\Support\RpgMariaDbParallel;
use Tests\TestCase;

abstract class RpgItemMariaDbConcurrencyTestFixtures extends TestCase
{
    use RefreshDatabase, RpgInventoryFixtures, RpgMariaDbParallel, \Tests\Support\RpgItemCreationFixtures;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Separate MariaDB-Konfiguration erforderlich.');
        }
        if (getenv('DB_DATABASE') !== 'omxfc_rpg_test') {
            throw new \RuntimeException('Only the isolated omxfc_rpg_test database may be used.');
        }
        parent::setUp();
        $this->progressionFixtures();
    }

    protected function action(?RpgItemType $type = null, array $changes = []): array
    {
        $input = ['input' => $changes + ['name' => 'Parallelklinge', 'description' => 'Ein Testgegenstand', 'reference' => 'Schwert S. 41', 'category' => 'melee_weapons', 'size' => 'medium', 'rarity' => 'common', 'hands' => 1, 'bucks' => 90], 'revision' => $type?->fresh()->revision ?? 0, 'submission_key' => (string) Str::uuid()];
        $input['preview_hash'] = app(RpgItemService::class)->preview($this->leader, $input, $type?->id)['preview_hash'];

        return ['action' => 'item', 'actor' => $this->leader->id, 'type' => $type?->id, 'input' => $input];
    }

    protected function first(): RpgItemVersion
    {
        $action = $this->action();

        return app(RpgItemService::class)->save($this->leader, $action['input']);
    }
}
