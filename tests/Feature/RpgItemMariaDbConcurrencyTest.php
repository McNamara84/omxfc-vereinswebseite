<?php

namespace Tests\Feature;

use App\Models\RpgCharacter;
use App\Models\RpgItemType;
use App\Models\RpgItemVersion;
use App\Services\RpgEquipmentCatalogResolver;
use App\Services\RpgInventoryService;
use App\Services\RpgItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\RpgInventoryFixtures;
use Tests\Support\RpgMariaDbParallel;
use Tests\TestCase;

class RpgItemMariaDbConcurrencyTest extends TestCase
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

    private function action(?RpgItemType $type = null, array $changes = []): array
    {
        $input = ['input' => $changes + ['name' => 'Parallelklinge', 'description' => 'Ein Testgegenstand', 'reference' => 'Schwert S. 41',
            'category' => 'melee_weapons', 'size' => 'medium', 'rarity' => 'common', 'hands' => 1, 'bucks' => 90],
            'revision' => $type?->fresh()->revision ?? 0, 'submission_key' => (string) Str::uuid()];
        $input['preview_hash'] = app(RpgItemService::class)->preview($this->leader, $input, $type?->id)['preview_hash'];

        return ['action' => 'item', 'actor' => $this->leader->id, 'type' => $type?->id, 'input' => $input];
    }

    private function first(): RpgItemVersion
    {
        $action = $this->action();

        return app(RpgItemService::class)->save($this->leader, $action['input']);
    }

    public function test_simultaneous_retries_create_exactly_one_type_and_version(): void
    {
        $action = $this->action();
        $results = $this->parallel($action, $action);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('rpg_item_types', 1);
        $this->assertDatabaseCount('rpg_item_versions', 1);
    }

    public function test_competing_new_versions_cannot_overwrite_or_reuse_a_number(): void
    {
        $first = $this->first();
        $results = $this->parallel($this->action($first->itemType, ['masterwork' => true]), $this->action($first->itemType, ['name' => 'Andere Klinge']));
        $this->assertSame(1, count(array_filter($results, fn ($row) => $row['ok'])));
        $this->assertSame(2, $first->itemType->fresh()->current_version);
        $this->assertDatabaseCount('rpg_item_versions', 2);
        $this->assertSame('4', $first->fresh()->definition['tw']);
    }

    public function test_publish_against_grant_preserves_the_explicit_version(): void
    {
        $first = $this->first();
        $this->inventoryInitialize();
        $input = $this->inventoryInput('grant', ['incoming' => [$this->position('item:'.$first->reference)]]);
        $input['preview_hash'] = app(RpgInventoryService::class)->preview($this->leader, $this->character->id, $input)['preview_hash'];
        $results = $this->parallel(['action' => 'inventory', 'actor' => $this->leader->id, 'character' => $this->character->id, 'input' => $input], $this->action($first->itemType, ['masterwork' => true]));
        $this->assertTrue($results[1]['ok']);
        $items = $this->character->fresh()->payload['equipment']['items'];
        $this->assertCount($results[0]['ok'] ? 1 : 0, $items);
        if ($items !== []) {
            $this->assertSame($first->reference, $items[0]['id']);
        }
    }

    public function test_competing_switches_cannot_duplicate_or_lose_items(): void
    {
        $first = $this->first();
        $secondAction = $this->action($first->itemType, ['masterwork' => true]);
        $second = app(RpgItemService::class)->save($this->leader, $secondAction['input'], $first->item_type_id);
        $this->inventoryInitialize(100, [$this->position('item:'.$first->reference)]);
        $input = $this->inventoryInput('item_version_change', ['version_change' => ['source' => 'item:'.$first->reference, 'target' => 'item:'.$second->reference, 'quantity' => 1]]);
        $input['preview_hash'] = app(RpgInventoryService::class)->preview($this->leader, $this->character->id, $input)['preview_hash'];
        $other = $input;
        $other['submission_key'] = (string) Str::uuid();
        $other['preview_hash'] = app(RpgInventoryService::class)->preview($this->leader, $this->character->id, $other)['preview_hash'];
        $action = ['action' => 'inventory', 'actor' => $this->leader->id, 'character' => $this->character->id];
        $results = $this->parallel($action + ['input' => $input], $action + ['input' => $other]);
        $this->assertSame(1, count(array_filter($results, fn ($row) => $row['ok'])));
        $items = $this->character->fresh()->payload['equipment']['items'];
        $this->assertCount(1, $items);
        $this->assertSame($second->reference, $items[0]['id']);
        $this->assertSame(1, $items[0]['quantity']);
        $this->assertSame(100, $this->character->fresh()->payload['equipment']['bucks_balance']);
    }

    public function test_release_change_invalidates_a_pending_version_preview(): void
    {
        $first = $this->first();
        $version = $this->action($first->itemType, ['masterwork' => true]);
        $release = ['action' => 'item_configure', 'actor' => $this->leader->id, 'type' => $first->item_type_id, 'input' => ['revision' => 1, 'starting_equipment' => true]];
        $before = app(RpgEquipmentCatalogResolver::class)->resolve()->version();
        $results = $this->parallel($release, $version);
        $this->assertSame(1, count(array_filter($results, fn ($row) => $row['ok'])));
        $this->assertNotSame($before, app(RpgEquipmentCatalogResolver::class)->resolve()->version());
    }

    public function test_withdrawing_start_release_against_character_creation_is_atomic(): void
    {
        $first = $this->first();
        app(RpgItemService::class)->configure($this->leader, $first->item_type_id, ['revision' => 1, 'starting_equipment' => true]);
        $this->character->delete();
        $create = ['action' => 'character_create', 'actor' => $this->player->id, 'input' => $this->creationInput($first->reference)];
        $withdraw = ['action' => 'item_configure', 'actor' => $this->leader->id, 'type' => $first->item_type_id, 'input' => ['revision' => 2, 'starting_equipment' => false]];
        $results = $this->parallel($withdraw, $create);
        $this->assertTrue($results[0]['ok']);
        $this->assertFalse($first->itemType->fresh()->starting_equipment);
        $characters = RpgCharacter::where('user_id', $this->player->id)->get();
        $this->assertCount($results[1]['ok'] ? 1 : 0, $characters);
        if ($characters->isNotEmpty()) {
            $this->assertContains($first->reference, array_column($characters[0]->payload['equipment']['items'], 'id'));
        }
    }
}
