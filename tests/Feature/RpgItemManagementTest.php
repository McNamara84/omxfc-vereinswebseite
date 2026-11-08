<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\RpgCharacter;
use App\Models\RpgInventoryTransaction;
use App\Models\RpgItemType;
use App\Models\RpgItemVersion;
use App\Models\Team;
use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCombat\CombatSnapshotFactory;
use App\Services\RpgEquipmentCatalogResolver;
use App\Services\RpgInventoryAdapter;
use App\Services\RpgItemService;
use App\Support\RpgEquipmentCatalog;
use App\Support\RpgInventoryCatalog;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\RpgInventoryFixtures;
use Tests\TestCase;

class RpgItemManagementTest extends TestCase
{
    use RefreshDatabase, RpgInventoryFixtures, \Tests\Support\RpgItemCreationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progressionFixtures();
    }

    private function input(array $changes = []): array
    {
        $definition = array_replace(['name' => 'Ruinenschwert', 'description' => 'Alte Klinge', 'reference' => 'Schwert S. 41',
            'category' => 'melee_weapons', 'size' => 'medium', 'rarity' => 'common', 'hands' => 1, 'bucks' => 90], $changes);
        if (! in_array($definition['category'], ['melee_weapons', 'ranged_weapons'], true)) {
            unset($definition['hands']);
        }

        return ['input' => $definition, 'revision' => 0, 'submission_key' => (string) Str::uuid()];
    }

    private function createItem(array $input = [], ?RpgItemType $type = null): RpgItemVersion
    {
        $data = $this->input($input);
        $data['revision'] = $type?->fresh()->revision ?? 0;
        $service = app(RpgItemService::class);
        $preview = $service->preview($this->leader, $data, $type?->id);

        return $service->save($this->leader, $data + ['preview_hash' => $preview['preview_hash']], $type?->id);
    }

    public function test_only_active_leader_can_manage_and_http_roundtrip_is_idempotent(): void
    {
        $this->actingAs($this->player)->get(route('rpg.items.index'))->assertForbidden();
        $this->actingAs($this->progressionMember(Role::Admin))->get(route('rpg.items.create'))->assertForbidden();
        $this->actingAs($this->leader)->get(route('rpg.items.create'))->assertOk()->assertSee('Vorschau prüfen');
        $data = $this->input();
        $preview = $this->postJson(route('rpg.items.preview'), $data)->assertOk()->json();
        $request = $data + ['preview_hash' => $preview['preview_hash']];
        $first = $this->postJson(route('rpg.items.store'), $request)->assertOk()->json();
        $this->postJson(route('rpg.items.store'), $request)->assertOk()->assertExactJson($first);
        $this->get($first['redirect'])->assertOk()->assertSee('Ruinenschwert')->assertSee('Version 1');
        $this->get(route('rpg.items.index'))->assertOk()->assertSee('Ruinenschwert');
        $this->assertDatabaseCount('rpg_item_versions', 1);
        $this->assertFalse(RpgItemType::first()->starting_equipment);
        $request['input']['name'] = 'Anderer Inhalt';
        $this->postJson(route('rpg.items.store'), $request)->assertUnprocessable();
        $this->rpgTeam->users()->detach($this->leader);
        $this->postJson(route('rpg.items.store'), $request)->assertForbidden();
    }

    public function test_forged_values_stale_previews_and_foreign_type_fail(): void
    {
        $this->actingAs($this->leader);
        $data = $this->input(['damage' => 200]);
        $this->postJson(route('rpg.items.preview'), $data)->assertUnprocessable();
        $data = $this->input();
        $data['preview_hash'] = str_repeat('a', 64);
        $this->postJson(route('rpg.items.store'), $data)->assertUnprocessable();
        $this->assertDatabaseCount('rpg_item_types', 0);
        $item = $this->createItem();
        $otherTeam = Team::factory()->create(['personal_team' => false]);
        $item->itemType->update(['team_id' => $otherTeam->id]);
        $this->get(route('rpg.items.show', $item->itemType))->assertForbidden();
    }

    public function test_preview_and_save_use_one_transaction_each_and_version_retries_remain_idempotent(): void
    {
        $baseLevel = DB::transactionLevel();
        $levels = [];
        Event::listen(TransactionBeginning::class, function (TransactionBeginning $event) use (&$levels): void {
            $levels[] = $event->connection->transactionLevel();
        });
        $first = $this->createItem();
        $type = $first->itemType;
        $data = $this->input(['masterwork' => true]);
        $data['revision'] = $type->revision;
        $service = app(RpgItemService::class);
        $data['preview_hash'] = $service->preview($this->leader, $data, $type->id)['preview_hash'];

        $second = $service->save($this->leader, $data, $type->id);
        $retry = $service->save($this->leader, $data, $type->id);

        $this->assertSame(array_fill(0, 5, $baseLevel + 1), $levels);
        $this->assertSame($baseLevel, DB::transactionLevel());
        $this->assertSame($second->id, $retry->id);
        $this->assertSame(2, $type->fresh()->current_version);
        $this->assertSame('4', $first->fresh()->definition['tw']);
        $this->assertSame('12', $second->definition['tw']);
        $this->assertDatabaseCount('rpg_item_types', 1);
        $this->assertDatabaseCount('rpg_item_versions', 2);
    }

    public function test_versions_freeze_values_and_explicit_switch_is_reversible(): void
    {
        $first = $this->createItem();
        $type = $first->itemType;
        $this->inventoryInitialize();
        $this->bookCustom('grant', ['incoming' => [$this->position('item:'.$first->reference, 2)]]);
        $initial = $this->character->fresh()->initial_payload;
        $second = $this->createItem(['masterwork' => true], $type);
        $payload = $this->character->fresh()->payload;
        $this->assertSame(1, app(RpgCharacterCombatCalculator::class)->calculate($payload)['weapons'][0]['damage_modifier']);
        $this->assertSame(0, app(RpgEquipmentCatalogResolver::class)->resolve()->capacity($first->reference));
        $this->assertSame(6, app(RpgEquipmentCatalogResolver::class)->resolve()->capacity('revolver'));
        $this->assertSame($initial, $this->character->fresh()->initial_payload);
        $switch = $this->bookCustom('item_version_change', ['version_change' => ['source' => 'item:'.$first->reference, 'target' => 'item:'.$second->reference, 'quantity' => 1]]);
        $state = app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
        $this->assertSame(1, $state['stocks']['item:'.$first->reference]);
        $this->assertSame(1, $state['stocks']['item:'.$second->reference]);
        $this->bookCustom('reversal', ['reversal_of' => $switch->id]);
        $state = app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
        $this->assertSame(2, $state['stocks']['item:'.$first->reference]);
        $this->assertArrayNotHasKey('item:'.$second->reference, $state['stocks']);
        $this->assertSame($initial, $this->character->fresh()->initial_payload);
    }

    public function test_start_release_archive_and_old_inventory_have_distinct_contexts(): void
    {
        $item = $this->createItem();
        $catalog = app(RpgEquipmentCatalogResolver::class)->resolve();
        $this->assertArrayNotHasKey($item->reference, $catalog->itemMap('creation'));
        $this->actingAs($this->leader)->patch(route('rpg.items.configure', $item->itemType), ['revision' => 1, 'starting_equipment' => 1])->assertRedirect();
        $catalog = app(RpgEquipmentCatalogResolver::class)->resolve();
        $this->assertArrayHasKey($item->reference, $catalog->itemMap('creation'));
        $this->get(route('rpg.char-editor'))->assertOk()->assertSee('Ruinenschwert');
        app(RpgItemService::class)->configure($this->leader, $item->item_type_id, ['revision' => 2, 'archived' => true]);
        $catalog = app(RpgEquipmentCatalogResolver::class)->resolve();
        $this->assertArrayNotHasKey($item->reference, $catalog->itemMap('creation'));
        $this->assertArrayHasKey($item->reference, $catalog->itemMap());
        $this->assertNotSame($catalog->version(), (new RpgEquipmentCatalog)->version());
        $this->inventoryInitialize();
        $this->expectException(ValidationException::class);
        $this->bookCustom('grant', ['incoming' => [$this->position('item:'.$item->reference)]]);
    }

    public function test_persisted_versions_reject_mutation(): void
    {
        $item = $this->createItem();
        $this->expectException(\LogicException::class);
        $item->update(['definition' => []]);
    }

    public function test_armor_switch_updates_active_version_without_rewriting_earlier_entries(): void
    {
        $first = $this->createItem(['category' => 'armor', 'effectiveness' => 1, 'movement_modifier' => -1]);
        $this->inventoryInitialize();
        $grant = $this->bookCustom('grant', ['incoming' => [$this->position('item:'.$first->reference, 2)]]);
        $this->bookCustom('equip', ['metadata' => ['armor' => $first->reference]]);
        $second = $this->createItem(['category' => 'armor', 'effectiveness' => 2, 'movement_modifier' => -1], $first->itemType);
        $this->bookCustom('item_version_change', ['version_change' => ['source' => 'item:'.$first->reference, 'target' => 'item:'.$second->reference, 'quantity' => 1]]);
        $this->assertSame($first->reference, $this->character->fresh()->payload['equipment']['active_armor_id']);
        $this->bookCustom('item_version_change', ['version_change' => ['source' => 'item:'.$first->reference, 'target' => 'item:'.$second->reference, 'quantity' => 1]]);
        $this->assertSame($second->reference, $this->character->fresh()->payload['equipment']['active_armor_id']);
        $this->assertSame(2, app(RpgCharacterCombatCalculator::class)->calculate($this->character->fresh()->payload)['defense']['components']['armor']);
        $this->assertSame(2, $grant->entries()->first()->after_state['stocks']['item:'.$first->reference]);
    }

    public function test_own_clothing_can_be_equipped_and_switched(): void
    {
        $first = $this->createItem(['category' => 'clothing']);
        $this->inventoryInitialize();
        $this->bookCustom('grant', ['incoming' => [$this->position('clothing:'.$first->reference)]]);
        $this->bookCustom('equip', ['metadata' => ['clothing' => $first->reference]]);
        $second = $this->createItem(['category' => 'clothing', 'beautiful' => true], $first->itemType);
        $this->bookCustom('item_version_change', ['version_change' => ['source' => 'clothing:'.$first->reference, 'target' => 'clothing:'.$second->reference, 'quantity' => 1]]);
        $this->assertSame($second->reference, $this->character->fresh()->payload['equipment']['clothing']['id']);
        $this->assertSame(100, $this->character->fresh()->payload['equipment']['bucks_balance']);
    }

    public function test_new_ammunition_is_stable_across_versions_and_never_refilled_by_a_switch(): void
    {
        $definition = ['category' => 'ranged_weapons', 'weapon_type' => 'Schießpulverwaffe', 'precision' => 1, 'fire_rate' => 'H',
            'range_increment' => 10, 'max_range' => 100, 'magazine' => 6, 'ammunition_name' => 'Patronen für Ruinenwaffe', 'ammunition_unit' => 'Schuss'];
        $first = $this->createItem($definition);
        $ammo = $first->definition['ammunitionProfile']['id'];
        $second = $this->createItem($definition + ['ammunition_id' => $ammo], $first->itemType);
        $this->assertSame($ammo, $second->definition['ammunitionProfile']['id']);
        $this->assertDatabaseCount('rpg_ammunition_profiles', 1);
        $catalog = app(RpgEquipmentCatalogResolver::class)->resolve();
        $this->assertSame(6, $catalog->capacity($first->reference));
        $this->assertSame($ammo, $catalog->ammunitionId($first->reference));
        $this->assertSame([$ammo => 48], $catalog->startingAmmunition([['id' => $first->reference, 'quantity' => 2]]));
        $this->actingAs($this->leader)->get(route('rpg.items.versions.create', $first->itemType))->assertOk()->assertSee('ammunition_id');
        $this->inventoryInitialize();
        $this->bookCustom('grant', ['incoming' => [$this->position('item:'.$second->reference), $this->position($ammo, 7)]]);
        $this->bookCustom('item_version_change', ['version_change' => ['source' => 'item:'.$second->reference, 'target' => 'item:'.$first->reference, 'quantity' => 1]]);
        $this->assertSame(7, app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload)['stocks'][$ammo]);
        $snapshot = app(CombatSnapshotFactory::class)->make($this->character->fresh());
        $weapon = array_values(array_filter($snapshot['weapons'], fn ($weapon) => $weapon['id'] === $first->reference))[0];
        $this->assertSame(2, $weapon['education']);
        $this->assertSame(6, $weapon['capacity']);
        $this->assertSame('medium', $weapon['size']);
    }

    public function test_archived_old_version_can_still_be_transferred_and_rendered_as_pdf(): void
    {
        $first = $this->createItem();
        $this->inventoryInitialize();
        $this->bookCustom('grant', ['incoming' => [$this->position('item:'.$first->reference)]]);
        $second = $this->createItem(['masterwork' => true], $first->itemType);
        $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()]);
        $this->inventoryInitialize(100, [], $other);
        app(RpgItemService::class)->configure($this->leader, $first->item_type_id, ['revision' => 2, 'archived' => true]);
        $this->bookCustom('sale', ['outgoing' => [$this->position('item:'.$first->reference)], 'amount' => 90, 'other_character_id' => $other->id, 'other_revision' => $other->fresh()->revision]);
        $state = app(RpgInventoryAdapter::class)->state($other->fresh()->payload);
        $this->assertSame(1, $state['stocks']['item:'.$first->reference]);
        $this->assertArrayNotHasKey('item:'.$second->reference, $state['stocks']);
        $response = $this->actingAs($this->player)->get(route('rpg.characters.pdf', $other))->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_creation_and_direct_export_require_current_start_release(): void
    {
        $this->character->delete();
        $item = $this->createItem();
        $data = $this->creationInput($item->reference);
        $this->actingAs($this->player)->postJson(route('rpg.characters.store'), $data)->assertUnprocessable();
        $this->postJson(route('rpg.characters.store'), array_replace($data, ['clothing' => ['invalid']]))->assertUnprocessable();
        app(RpgItemService::class)->configure($this->leader, $item->item_type_id, ['revision' => 1, 'starting_equipment' => true]);
        $data['equipment_catalog_version'] = app(RpgEquipmentCatalogResolver::class)->resolve()->version();
        $this->post(route('rpg.characters.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $saved = RpgCharacter::where('character_name', 'Neuer Held')->firstOrFail();
        $this->assertContains($item->reference, array_column($saved->payload['equipment']['items'], 'id'));
        app(RpgItemService::class)->configure($this->leader, $item->item_type_id, ['revision' => 2, 'starting_equipment' => false]);
        $this->postJson(route('rpg.char-editor.pdf'), $data)->assertUnprocessable()
            ->assertJsonValidationErrors(['inventory'])
            ->assertJsonPath('errors.inventory.0', 'Die Ausrüstungsfreigabe wurde geändert. Bitte neu laden.');
        $this->get(route('rpg.characters.pdf', $saved))->assertOk();
    }

    public function test_catalog_resolver_without_an_rpg_team_preserves_the_base_catalog(): void
    {
        $this->rpgTeam->update(['name' => 'Andere AG']);
        $this->assertSame(RpgInventoryCatalog::VERSION, app(RpgEquipmentCatalogResolver::class)->resolve()->version());
    }

    private function bookCustom(string $kind, array $changes): RpgInventoryTransaction
    {
        return $this->inventoryBook($kind, $changes + ['catalog_version' => app(RpgEquipmentCatalogResolver::class)->resolve()->version()]);
    }
}
