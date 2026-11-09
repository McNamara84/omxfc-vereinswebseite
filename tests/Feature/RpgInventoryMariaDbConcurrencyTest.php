<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgCharacter;
use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgInventoryAdapter;

pest()->extend(RpgInventoryMariaDbConcurrencyTestFixtures::class);

test('simultaneous retries book money and goods exactly once', function (): void {
    $action = $this->action($this->character, 'grant', ['amount' => 5, 'incoming' => [$this->position('item:seil')]]);
    $results = $this->parallel($action, $action);
    expect($results[0]['ok'])->toBeTrue()
        ->and($results[1]['ok'])->toBeTrue()
        ->and($results[1]['id'])->toBe($results[0]['id'])
        ->and($this->character->fresh()->payload['equipment']['bucks_balance'])->toBe(105);
    $this->assertDatabaseCount('rpg_inventory_transactions', 2);
    $this->assertDatabaseCount('rpg_inventory_entries', 2);
});

test('competing purchases cannot overspend or duplicate goods', function (): void {
    $changes = ['amount' => 70, 'incoming' => [$this->position('item:bogen')], 'external_party' => 'Händler', 'price_reason' => 'Knappes Angebot'];
    $results = $this->parallel($this->action($this->character, 'purchase', $changes), $this->action($this->character, 'purchase', $changes));
    expect(array_filter($results, fn ($row) => $row['ok']))->toHaveCount(1);
    $state = app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
    expect($state['bucks'])->toBe(30)
        ->and($state['stocks']['item:bogen'])->toBe(1);
});

test('opposing character trades preserve totals without deadlocks', function (): void {
    $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()]);
    $this->inventoryInitialize(100, [$this->position('item:bogen', 2)], $other);
    $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil', 2)]]);
    $a = $this->action($this->character, 'swap', ['incoming' => [$this->position('item:bogen')], 'outgoing' => [$this->position('item:seil')], 'amount' => 10, 'cash_direction' => 'expense', 'other_character_id' => $other->id, 'other_revision' => $other->fresh()->revision]);
    $b = $this->action($other, 'swap', ['incoming' => [$this->position('item:seil')], 'outgoing' => [$this->position('item:bogen')], 'amount' => 10, 'other_character_id' => $this->character->id, 'other_revision' => $this->character->fresh()->revision]);
    $results = $this->parallel($a, $b);
    expect(array_filter($results, fn ($row) => $row['ok']))->toHaveCount(1);
    $first = app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
    $second = app(RpgInventoryAdapter::class)->state($other->fresh()->payload);
    expect($first['bucks'] + $second['bucks'])->toBe(200)
        ->and($first['stocks']['item:seil'] + $second['stocks']['item:seil'])->toBe(2)
        ->and($first['stocks']['item:bogen'] + $second['stocks']['item:bogen'])->toBe(2);
});

test('inventory and advancement approval never overwrite each other', function (): void {
    $this->credit();
    $request = app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $this->advancementInput());
    $results = $this->parallel($this->action($this->character, 'grant', ['amount' => 5]), ['action' => 'approved', 'actor' => $this->leader->id, 'request' => $request->id]);
    expect(array_filter($results, fn ($row) => $row['ok']))->toHaveCount(1);
    $approved = $request->fresh()->status === 'approved';
    expect($this->character->experienceBalance())->toBe($approved ? 54 : 60)
        ->and($this->character->fresh()->payload['equipment']['bucks_balance'])->toBe($approved ? 100 : 105)
        ->and(['approved', 'superseded'])->toContain($request->fresh()->status);
});

test('character deletion racing with booking leaves no private entries', function (): void {
    $results = $this->parallel($this->action($this->character, 'grant', ['amount' => 5]), ['action' => 'delete', 'actor' => $this->player->id, 'character' => $this->character->id]);
    expect($results[1]['ok'])->toBeTrue()
        ->and($this->character->fresh())->toBeNull();
    $this->assertDatabaseCount('rpg_inventory_entries', 0);
});
