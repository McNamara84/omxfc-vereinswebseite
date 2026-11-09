<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\RpgCharacter;
use App\Models\RpgItemType;
use App\Models\Team;
use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCombat\CombatSnapshotFactory;
use App\Services\RpgEquipmentCatalogResolver;
use App\Services\RpgInventoryAdapter;
use App\Services\RpgItemService;
use App\Support\RpgEquipmentCatalog;
use App\Support\RpgInventoryCatalog;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgItemManagementTestFixtures::class);

beforeEach(function (): void {
    $this->progressionFixtures();
});

test('only active leader can manage and http roundtrip is idempotent', function (): void {
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
    expect(RpgItemType::first()->starting_equipment)->toBeFalse();
    $request['input']['name'] = 'Anderer Inhalt';
    $this->postJson(route('rpg.items.store'), $request)->assertUnprocessable();
    $this->rpgTeam->users()->detach($this->leader);
    $this->postJson(route('rpg.items.store'), $request)->assertForbidden();
});

test('forged values stale previews and foreign type fail', function (): void {
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
});

test('preview and save use one transaction each and version retries remain idempotent', function (): void {
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
    expect($levels)->toBe(array_fill(0, 5, $baseLevel + 1))
        ->and(DB::transactionLevel())->toBe($baseLevel)
        ->and($retry->id)->toBe($second->id)
        ->and($type->fresh()->current_version)->toBe(2)
        ->and($first->fresh()->definition['tw'])->toBe('4')
        ->and($second->definition['tw'])->toBe('12');
    $this->assertDatabaseCount('rpg_item_types', 1);
    $this->assertDatabaseCount('rpg_item_versions', 2);
});

test('versions freeze values and explicit switch is reversible', function (): void {
    $first = $this->createItem();
    $type = $first->itemType;
    $this->inventoryInitialize();
    $this->bookCustom('grant', ['incoming' => [$this->position('item:'.$first->reference, 2)]]);
    $initial = $this->character->fresh()->initial_payload;
    $second = $this->createItem(['masterwork' => true], $type);
    $payload = $this->character->fresh()->payload;
    expect(app(RpgCharacterCombatCalculator::class)->calculate($payload)['weapons'][0]['damage_modifier'])->toBe(1)
        ->and(app(RpgEquipmentCatalogResolver::class)->resolve()->capacity($first->reference))->toBe(0)
        ->and(app(RpgEquipmentCatalogResolver::class)->resolve()->capacity('revolver'))->toBe(6)
        ->and($this->character->fresh()->initial_payload)->toBe($initial);
    $switch = $this->bookCustom('item_version_change', ['version_change' => ['source' => 'item:'.$first->reference, 'target' => 'item:'.$second->reference, 'quantity' => 1]]);
    $state = app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
    expect($state['stocks']['item:'.$first->reference])->toBe(1)
        ->and($state['stocks']['item:'.$second->reference])->toBe(1);
    $this->bookCustom('reversal', ['reversal_of' => $switch->id]);
    $state = app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
    expect($state['stocks']['item:'.$first->reference])->toBe(2)
        ->and($state['stocks'])->not->toHaveKey('item:'.$second->reference)
        ->and($this->character->fresh()->initial_payload)->toBe($initial);
});

test('start release archive and old inventory have distinct contexts', function (): void {
    $item = $this->createItem();
    $catalog = app(RpgEquipmentCatalogResolver::class)->resolve();
    expect($catalog->itemMap('creation'))->not->toHaveKey($item->reference);
    $this->actingAs($this->leader)->patch(route('rpg.items.configure', $item->itemType), ['revision' => 1, 'starting_equipment' => 1])->assertRedirect();
    $catalog = app(RpgEquipmentCatalogResolver::class)->resolve();
    expect($catalog->itemMap('creation'))->toHaveKey($item->reference);
    $this->get(route('rpg.char-editor'))->assertOk()->assertSee('Ruinenschwert');
    app(RpgItemService::class)->configure($this->leader, $item->item_type_id, ['revision' => 2, 'archived' => true]);
    $catalog = app(RpgEquipmentCatalogResolver::class)->resolve();
    expect($catalog->itemMap('creation'))->not->toHaveKey($item->reference)
        ->and($catalog->itemMap())->toHaveKey($item->reference)
        ->and((new RpgEquipmentCatalog)->version())->not->toBe($catalog->version());
    $this->inventoryInitialize();
    expect(fn () => $this->bookCustom('grant', ['incoming' => [$this->position('item:'.$item->reference)]]))->toThrow(ValidationException::class);
});

test('persisted versions reject mutation', function (): void {
    $item = $this->createItem();
    expect(fn () => $item->update(['definition' => []]))->toThrow(\LogicException::class);
});

test('armor switch updates active version without rewriting earlier entries', function (): void {
    $first = $this->createItem(['category' => 'armor', 'effectiveness' => 1, 'movement_modifier' => -1]);
    $this->inventoryInitialize();
    $grant = $this->bookCustom('grant', ['incoming' => [$this->position('item:'.$first->reference, 2)]]);
    $this->bookCustom('equip', ['metadata' => ['armor' => $first->reference]]);
    $second = $this->createItem(['category' => 'armor', 'effectiveness' => 2, 'movement_modifier' => -1], $first->itemType);
    $this->bookCustom('item_version_change', ['version_change' => ['source' => 'item:'.$first->reference, 'target' => 'item:'.$second->reference, 'quantity' => 1]]);
    expect($this->character->fresh()->payload['equipment']['active_armor_id'])->toBe($first->reference);
    $this->bookCustom('item_version_change', ['version_change' => ['source' => 'item:'.$first->reference, 'target' => 'item:'.$second->reference, 'quantity' => 1]]);
    expect($this->character->fresh()->payload['equipment']['active_armor_id'])->toBe($second->reference)
        ->and(app(RpgCharacterCombatCalculator::class)->calculate($this->character->fresh()->payload)['defense']['components']['armor'])->toBe(2)
        ->and($grant->entries()->first()->after_state['stocks']['item:'.$first->reference])->toBe(2);
});

test('own clothing can be equipped and switched', function (): void {
    $first = $this->createItem(['category' => 'clothing']);
    $this->inventoryInitialize();
    $this->bookCustom('grant', ['incoming' => [$this->position('clothing:'.$first->reference)]]);
    $this->bookCustom('equip', ['metadata' => ['clothing' => $first->reference]]);
    $second = $this->createItem(['category' => 'clothing', 'beautiful' => true], $first->itemType);
    $this->bookCustom('item_version_change', ['version_change' => ['source' => 'clothing:'.$first->reference, 'target' => 'clothing:'.$second->reference, 'quantity' => 1]]);
    expect($this->character->fresh()->payload['equipment']['clothing']['id'])->toBe($second->reference)
        ->and($this->character->fresh()->payload['equipment']['bucks_balance'])->toBe(100);
});

test('new ammunition is stable across versions and never refilled by a switch', function (): void {
    $definition = ['category' => 'ranged_weapons', 'weapon_type' => 'Schießpulverwaffe', 'precision' => 1, 'fire_rate' => 'H', 'range_increment' => 10, 'max_range' => 100, 'magazine' => 6, 'ammunition_name' => 'Patronen für Ruinenwaffe', 'ammunition_unit' => 'Schuss'];
    $first = $this->createItem($definition);
    $ammo = $first->definition['ammunitionProfile']['id'];
    $second = $this->createItem($definition + ['ammunition_id' => $ammo], $first->itemType);
    expect($second->definition['ammunitionProfile']['id'])->toBe($ammo);
    $this->assertDatabaseCount('rpg_ammunition_profiles', 1);
    $catalog = app(RpgEquipmentCatalogResolver::class)->resolve();
    expect($catalog->capacity($first->reference))->toBe(6)
        ->and($catalog->ammunitionId($first->reference))->toBe($ammo)
        ->and($catalog->startingAmmunition([['id' => $first->reference, 'quantity' => 2]]))->toBe([$ammo => 48]);
    $this->actingAs($this->leader)->get(route('rpg.items.versions.create', $first->itemType))->assertOk()->assertSee('ammunition_id');
    $this->inventoryInitialize();
    $this->bookCustom('grant', ['incoming' => [$this->position('item:'.$second->reference), $this->position($ammo, 7)]]);
    $this->bookCustom('item_version_change', ['version_change' => ['source' => 'item:'.$second->reference, 'target' => 'item:'.$first->reference, 'quantity' => 1]]);
    expect(app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload)['stocks'][$ammo])->toBe(7);
    $snapshot = app(CombatSnapshotFactory::class)->make($this->character->fresh());
    $weapon = array_first(array_filter($snapshot['weapons'], fn ($weapon) => $weapon['id'] === $first->reference));
    expect($weapon['education'])->toBe(2)
        ->and($weapon['capacity'])->toBe(6)
        ->and($weapon['size'])->toBe('medium');
});

test('archived old version can still be transferred and rendered as pdf', function (): void {
    $first = $this->createItem();
    $this->inventoryInitialize();
    $this->bookCustom('grant', ['incoming' => [$this->position('item:'.$first->reference)]]);
    $second = $this->createItem(['masterwork' => true], $first->itemType);
    $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()]);
    $this->inventoryInitialize(100, [], $other);
    app(RpgItemService::class)->configure($this->leader, $first->item_type_id, ['revision' => 2, 'archived' => true]);
    $this->bookCustom('sale', ['outgoing' => [$this->position('item:'.$first->reference)], 'amount' => 90, 'other_character_id' => $other->id, 'other_revision' => $other->fresh()->revision]);
    $state = app(RpgInventoryAdapter::class)->state($other->fresh()->payload);
    expect($state['stocks']['item:'.$first->reference])->toBe(1)
        ->and($state['stocks'])->not->toHaveKey('item:'.$second->reference);
    $response = $this->actingAs($this->player)->get(route('rpg.characters.pdf', $other))->assertOk();
    expect($response->getContent())->toStartWith('%PDF');
});

test('creation and direct export require current start release', function (): void {
    $this->character->delete();
    $item = $this->createItem();
    $data = $this->creationInput($item->reference);
    $this->actingAs($this->player)->postJson(route('rpg.characters.store'), $data)->assertUnprocessable();
    $this->postJson(route('rpg.characters.store'), array_replace($data, ['clothing' => ['invalid']]))->assertUnprocessable();
    app(RpgItemService::class)->configure($this->leader, $item->item_type_id, ['revision' => 1, 'starting_equipment' => true]);
    $data['equipment_catalog_version'] = app(RpgEquipmentCatalogResolver::class)->resolve()->version();
    $this->post(route('rpg.characters.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $saved = RpgCharacter::where('character_name', 'Neuer Held')->firstOrFail();
    expect(array_column($saved->payload['equipment']['items'], 'id'))->toContain($item->reference);
    app(RpgItemService::class)->configure($this->leader, $item->item_type_id, ['revision' => 2, 'starting_equipment' => false]);
    $this->postJson(route('rpg.char-editor.pdf'), $data)->assertUnprocessable()->assertJsonValidationErrors(['inventory'])->assertJsonPath('errors.inventory.0', 'Die Ausrüstungsfreigabe wurde geändert. Bitte neu laden.');
    $this->get(route('rpg.characters.pdf', $saved))->assertOk();
});

test('catalog resolver without an rpg team preserves the base catalog', function (): void {
    $this->rpgTeam->update(['name' => 'Andere AG']);
    expect(app(RpgEquipmentCatalogResolver::class)->resolve()->version())->toBe(RpgInventoryCatalog::VERSION);
});
