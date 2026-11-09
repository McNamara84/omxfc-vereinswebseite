<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgCharacter;
use App\Models\RpgInventoryEntry;
use App\Models\RpgInventoryTransaction;
use App\Services\RpgInventoryAdapter;
use App\Services\RpgInventoryService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgInventoryTradeTestFixtures::class);
dataset('RpgInventoryTradeTest.negotiations', static fn (): array => [['buyer', 1, 29], ['buyer', 2, 27], ['seller', 1, 32], ['seller', 2, 33], ['none', 0, 30]]);
dataset('RpgInventoryTradeTest.unfundedOverlappingSwaps', static fn (): array => ['external missing item' => [true, 0, 0, 1, 1, 'item:bogen'], 'external insufficient item' => [true, 1, 0, 2, 1, 'item:bogen'], 'both characters missing item' => [false, 0, 0, 1, 1, 'item:bogen'], 'initiator missing item' => [false, 0, 1, 1, 1, 'item:bogen'], 'counterparty missing item' => [false, 1, 0, 1, 1, 'item:bogen'], 'initiator insufficient quantity' => [false, 1, 2, 2, 1, 'item:bogen'], 'counterparty insufficient quantity' => [false, 2, 1, 1, 2, 'item:bogen'], 'both characters insufficient quantity' => [false, 1, 1, 2, 2, 'item:bogen'], 'missing ammunition' => [false, 0, 0, 1, 1, 'ammo:pfeile'], 'counterparty missing clothing' => [false, 1, 0, 1, 1, 'clothing:kleidung-einfach']]);
dataset('RpgInventoryTradeTest.fundedOverlappingSwaps', static fn (): array => ['equal quantities' => [1, 1, 1, 1], 'different quantities' => [2, 3, 2, 3]]);
dataset('RpgInventoryTradeTest.invalidTrades', static fn (): array => ['insufficient bucks' => [['amount' => 101, 'price_reason' => 'Teuer']], 'free purchase' => [['amount' => 0]], 'missing counterparty' => [['external_party' => '']], 'missing goods' => [['incoming' => []]], 'unexplained price' => [['amount' => 29]], 'missing ammo price' => [['incoming' => [['id' => 'ammo:revolver', 'quantity' => 30]]]], 'missing winner' => [['success_points' => 1]], 'ambiguous counterparty' => [['other_character_id' => 99999]], 'sale of unowned good' => [['kind' => 'sale', 'incoming' => [], 'outgoing' => [['id' => 'item:bogen', 'quantity' => 1]]]]]);
beforeEach(function (): void {
    $this->progressionFixtures();
    $this->inventoryInitialize();
});
test('purchase and sale book goods and money together', function (): void {
    $purchase = $this->inventoryBook('purchase', ['incoming' => [$this->position('item:bogen')], 'amount' => 30, 'external_party' => 'Händler']);
    expect($this->state()['bucks'])->toBe(70)
        ->and($this->state()['stocks']['item:bogen'])->toBe(1)
        ->and($this->state()['stocks'])->not->toHaveKey('ammo:pfeile')
        ->and($purchase->details['price']['base'])->toBe(30);
    $this->inventoryBook('sale', ['outgoing' => [$this->position('item:bogen')], 'amount' => 20, 'external_party' => 'Händler', 'price_reason' => 'Gebraucht verkauft.']);
    expect($this->state()['bucks'])->toBe(90)
        ->and($this->state()['stocks'])->not->toHaveKey('item:bogen');
});
test('negotiation uses winner direction and integer rounding', function (string $winner, int $points, int $price): void {
    $transaction = $this->inventoryBook('purchase', ['incoming' => [$this->position('item:bogen')], 'amount' => $price, 'external_party' => 'Händler', 'winner' => $winner, 'success_points' => $points]);
    expect($transaction->details['price']['suggested'])->toBe($price)
        ->and($this->state()['bucks'])->toBe(100 - $price);
})->with('RpgInventoryTradeTest.negotiations');
test('ammunition without price needs a ruling and is not limited to twenty', function (): void {
    $this->inventoryBook('purchase', ['incoming' => [$this->position('ammo:revolver', 50)], 'amount' => 25, 'external_party' => 'Munitionshändler', 'price_reason' => '25 Bucks für den ausgehandelten Vorrat.']);
    expect($this->state()['stocks']['ammo:revolver'])->toBe(50)
        ->and($this->state()['bucks'])->toBe(75);
});
test('character swap transfers goods ammunition and cash atomically and can be reversed', function (): void {
    $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()]);
    $this->inventoryInitialize(10, [$this->position('item:bogen'), $this->position('ammo:pfeile', 30)], $other);
    $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil', 2)]]);
    $transaction = $this->inventoryBook('swap', ['incoming' => [$this->position('item:bogen'), $this->position('ammo:pfeile', 12)], 'outgoing' => [$this->position('item:seil')], 'amount' => 20, 'cash_direction' => 'expense', 'other_character_id' => $other->id, 'other_revision' => $other->fresh()->revision]);
    $state = $this->state();
    $otherState = app(RpgInventoryAdapter::class)->state($other->fresh()->payload);
    expect($state['bucks'])->toBe(80)
        ->and($otherState['bucks'])->toBe(30)
        ->and($state['stocks']['ammo:pfeile'])->toBe(12)
        ->and($otherState['stocks']['ammo:pfeile'])->toBe(18)
        ->and($otherState['stocks']['item:seil'])->toBe(1)
        ->and($otherState['stocks'])->not->toHaveKey('item:bogen')
        ->and($transaction->entries)->toHaveCount(2);
    $this->inventoryBook('reversal', ['reversal_of' => $transaction->id, 'other_revision' => $other->fresh()->revision]);
    expect($this->state()['bucks'])->toBe(100)
        ->and(app(RpgInventoryAdapter::class)->state($other->fresh()->payload)['bucks'])->toBe(10);
});
test('overlapping swap quantities cannot hide insufficient pre trade stock', function (bool $external, int $owned, int $otherOwned, int $outgoing, int $incoming, string $position): void {
    if ($owned > 0) {
        $this->inventoryBook('grant', ['incoming' => [$this->position($position, $owned)]]);
    }
    $other = null;
    $party = ['external_party' => 'Händler'];
    if (! $external) {
        $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()]);
        $this->inventoryInitialize(100, $otherOwned > 0 ? [$this->position($position, $otherOwned)] : [], $other);
        $party = ['other_character_id' => $other->id, 'other_revision' => $other->fresh()->revision];
    }
    $before = $this->character->fresh();
    $otherBefore = $other?->fresh();
    $transactions = RpgInventoryTransaction::count();
    $entries = RpgInventoryEntry::count();
    $input = $this->inventoryInput('swap', $party + ['outgoing' => [$this->position($position, $outgoing)], 'incoming' => [$this->position($position, $incoming)], 'amount' => 10, 'cash_direction' => 'expense', 'preview_hash' => str_repeat('0', 64)]);
    $service = app(RpgInventoryService::class);
    foreach (['preview', 'book'] as $method) {
        try {
            $service->{$method}($this->leader, $this->character->id, $input);
            $this->fail('Overlapping quantities concealed missing outgoing stock in '.$method.'.');
        } catch (ValidationException $exception) {
            expect($exception->errors()['inventory'][0])->toContain('Warenausgang');
        }
    }
    expect($this->character->fresh()->payload)->toBe($before->payload)
        ->and($this->character->fresh()->revision)->toBe($before->revision);
    if ($other !== null) {
        expect($other->fresh()->payload)->toBe($otherBefore->payload)
            ->and($other->fresh()->revision)->toBe($otherBefore->revision);
    }
    $this->assertDatabaseCount('rpg_inventory_transactions', $transactions);
    $this->assertDatabaseCount('rpg_inventory_entries', $entries);
})->with('RpgInventoryTradeTest.unfundedOverlappingSwaps');
test('overlapping swaps remain valid when both characters own the outgoing goods', function (int $owned, int $otherOwned, int $outgoing, int $incoming): void {
    $this->inventoryBook('grant', ['incoming' => [$this->position('item:bogen', $owned)]]);
    $payload = $this->progressionPayload();
    $unarmed = ['id' => 'faustschlag-tritt', 'quantity' => 1];
    $payload['equipment']['items'] = [$unarmed];
    $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $payload]);
    $this->inventoryInitialize(100, [$this->position('item:bogen', $otherOwned)], $other);
    $before = $this->character->fresh();
    $otherBefore = $other->fresh();
    $transaction = $this->inventoryBook('swap', ['incoming' => [$this->position('item:bogen', $incoming)], 'outgoing' => [$this->position('item:bogen', $outgoing)], 'amount' => 10, 'cash_direction' => 'expense', 'other_character_id' => $other->id, 'other_revision' => $otherBefore->revision]);
    $otherState = app(RpgInventoryAdapter::class)->state($other->fresh()->payload);
    expect($this->state()['stocks']['item:bogen'])->toBe($owned - $outgoing + $incoming)
        ->and($otherState['stocks']['item:bogen'])->toBe($otherOwned - $incoming + $outgoing)
        ->and($this->state()['bucks'])->toBe(90)
        ->and($otherState['bucks'])->toBe(110)
        ->and($other->fresh()->payload['equipment']['items'])->toContain($unarmed)
        ->and($otherState['stocks'])->not->toHaveKey('item:faustschlag-tritt')
        ->and($this->character->fresh()->revision)->toBe($before->revision + 1)
        ->and($other->fresh()->revision)->toBe($otherBefore->revision + 1)
        ->and($transaction->entries)->toHaveCount(2);
})->with('RpgInventoryTradeTest.fundedOverlappingSwaps');
test('insufficient counterparty stock rolls back both sides', function (): void {
    $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()]);
    $this->inventoryInitialize(0, [], $other);
    $before = $this->character->fresh();
    $otherBefore = $other->fresh();
    try {
        $this->inventoryBook('purchase', ['incoming' => [$this->position('item:bogen')], 'amount' => 30, 'other_character_id' => $other->id, 'other_revision' => $otherBefore->revision]);
        $this->fail('Expected insufficient inventory.');
    } catch (ValidationException) {
        expect($this->character->fresh()->payload)->toBe($before->payload)
            ->and($other->fresh()->payload)->toBe($otherBefore->payload)
            ->and($this->character->fresh()->revision)->toBe($before->revision)
            ->and($other->fresh()->revision)->toBe($otherBefore->revision);
        $this->assertDatabaseCount('rpg_inventory_transactions', 2);
    }
});
test('invalid trade has no partial money or goods changes', function (array $overrides): void {
    $input = $this->inventoryInput('purchase', array_replace(['incoming' => [$this->position('item:bogen')], 'amount' => 30, 'external_party' => 'Händler'], $overrides));
    $before = $this->character->fresh()->payload;
    try {
        app(RpgInventoryService::class)->preview($this->leader, $this->character->id, $input);
        $this->fail('Invalid trade accepted.');
    } catch (ValidationException) {
        expect($this->character->fresh()->payload)->toBe($before);
        $this->assertDatabaseCount('rpg_inventory_transactions', 1);
    }
})->with('RpgInventoryTradeTest.invalidTrades');
