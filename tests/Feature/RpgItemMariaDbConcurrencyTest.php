<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgCharacter;
use App\Services\RpgEquipmentCatalogResolver;
use App\Services\RpgInventoryService;
use App\Services\RpgItemService;
use Illuminate\Support\Str;

pest()->extend(RpgItemMariaDbConcurrencyTestFixtures::class);

test('simultaneous retries create exactly one type and version', function (): void {
    $action = $this->action();
    $results = $this->parallel($action, $action);
    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue()
        ->and($results[1]['id'])->toBe($results[0]['id']);
    $this->assertDatabaseCount('rpg_item_types', 1);
    $this->assertDatabaseCount('rpg_item_versions', 1);
});

test('competing new versions cannot overwrite or reuse a number', function (): void {
    $first = $this->first();
    $results = $this->parallel($this->action($first->itemType, ['masterwork' => true]), $this->action($first->itemType, ['name' => 'Andere Klinge']));
    expect(array_filter($results, fn ($row) => $row['ok']))->toHaveCount(1)
        ->and($first->itemType->fresh()->current_version)->toBe(2);
    $this->assertDatabaseCount('rpg_item_versions', 2);
    expect($first->fresh()->definition['tw'])->toBe('4');
});

test('publish against grant preserves the explicit version', function (): void {
    $first = $this->first();
    $this->inventoryInitialize();
    $input = $this->inventoryInput('grant', ['incoming' => [$this->position('item:'.$first->reference)]]);
    $input['preview_hash'] = app(RpgInventoryService::class)->preview($this->leader, $this->character->id, $input)['preview_hash'];
    $results = $this->parallel(['action' => 'inventory', 'actor' => $this->leader->id, 'character' => $this->character->id, 'input' => $input], $this->action($first->itemType, ['masterwork' => true]));
    expect($results[1]['ok'])->toBeTrue();
    $items = $this->character->fresh()->payload['equipment']['items'];
    expect($items)->toHaveCount($results[0]['ok'] ? 1 : 0);
    if ($items !== []) {
        expect($items[0]['id'])->toBe($first->reference);
    }
});

test('competing switches cannot duplicate or lose items', function (): void {
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
    expect(array_filter($results, fn ($row) => $row['ok']))->toHaveCount(1);
    $items = $this->character->fresh()->payload['equipment']['items'];
    expect($items)->toHaveCount(1)
        ->and($items[0]['id'])->toBe($second->reference)
        ->and($items[0]['quantity'])->toBe(1)
        ->and($this->character->fresh()->payload['equipment']['bucks_balance'])->toBe(100);
});

test('release change invalidates a pending version preview', function (): void {
    $first = $this->first();
    $version = $this->action($first->itemType, ['masterwork' => true]);
    $release = ['action' => 'item_configure', 'actor' => $this->leader->id, 'type' => $first->item_type_id, 'input' => ['revision' => 1, 'starting_equipment' => true]];
    $before = app(RpgEquipmentCatalogResolver::class)->resolve()->version();
    $results = $this->parallel($release, $version);
    expect(array_filter($results, fn ($row) => $row['ok']))->toHaveCount(1)
        ->and(app(RpgEquipmentCatalogResolver::class)->resolve()->version())->not->toBe($before);
});

test('withdrawing start release against character creation is atomic', function (): void {
    $first = $this->first();
    app(RpgItemService::class)->configure($this->leader, $first->item_type_id, ['revision' => 1, 'starting_equipment' => true]);
    $this->character->delete();
    $create = ['action' => 'character_create', 'actor' => $this->player->id, 'input' => $this->creationInput($first->reference)];
    $withdraw = ['action' => 'item_configure', 'actor' => $this->leader->id, 'type' => $first->item_type_id, 'input' => ['revision' => 2, 'starting_equipment' => false]];
    $results = $this->parallel($withdraw, $create);
    expect($results[0]['ok'])->toBeTrue()
        ->and($first->itemType->fresh()->starting_equipment)->toBeFalse();
    $characters = RpgCharacter::where('user_id', $this->player->id)->get();
    expect($characters)->toHaveCount($results[1]['ok'] ? 1 : 0);
    if ($characters->isNotEmpty()) {
        expect(array_column($characters[0]->payload['equipment']['items'], 'id'))->toContain($first->reference);
    }
});
