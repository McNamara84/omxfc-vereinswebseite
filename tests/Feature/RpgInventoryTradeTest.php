<?php

namespace Tests\Feature;

use App\Models\RpgCharacter;
use App\Models\RpgInventoryEntry;
use App\Models\RpgInventoryTransaction;
use App\Services\RpgInventoryAdapter;
use App\Services\RpgInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RpgInventoryFixtures;
use Tests\TestCase;

class RpgInventoryTradeTest extends TestCase
{
    use RefreshDatabase, RpgInventoryFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progressionFixtures();
        $this->inventoryInitialize();
    }

    public function test_purchase_and_sale_book_goods_and_money_together(): void
    {
        $purchase = $this->inventoryBook('purchase', ['incoming' => [$this->position('item:bogen')], 'amount' => 30, 'external_party' => 'Händler']);
        $this->assertSame(70, $this->state()['bucks']);
        $this->assertSame(1, $this->state()['stocks']['item:bogen']);
        $this->assertArrayNotHasKey('ammo:pfeile', $this->state()['stocks']);
        $this->assertSame(30, $purchase->details['price']['base']);
        $this->inventoryBook('sale', ['outgoing' => [$this->position('item:bogen')], 'amount' => 20, 'external_party' => 'Händler', 'price_reason' => 'Gebraucht verkauft.']);
        $this->assertSame(90, $this->state()['bucks']);
        $this->assertArrayNotHasKey('item:bogen', $this->state()['stocks']);
    }

    #[DataProvider('negotiations')]
    public function test_negotiation_uses_winner_direction_and_integer_rounding(string $winner, int $points, int $price): void
    {
        $transaction = $this->inventoryBook('purchase', ['incoming' => [$this->position('item:bogen')], 'amount' => $price,
            'external_party' => 'Händler', 'winner' => $winner, 'success_points' => $points]);
        $this->assertSame($price, $transaction->details['price']['suggested']);
        $this->assertSame(100 - $price, $this->state()['bucks']);
    }

    public static function negotiations(): array
    {
        return [['buyer', 1, 29], ['buyer', 2, 27], ['seller', 1, 32], ['seller', 2, 33], ['none', 0, 30]];
    }

    public function test_ammunition_without_price_needs_a_ruling_and_is_not_limited_to_twenty(): void
    {
        $this->inventoryBook('purchase', ['incoming' => [$this->position('ammo:revolver', 50)], 'amount' => 25,
            'external_party' => 'Munitionshändler', 'price_reason' => '25 Bucks für den ausgehandelten Vorrat.']);
        $this->assertSame(50, $this->state()['stocks']['ammo:revolver']);
        $this->assertSame(75, $this->state()['bucks']);
    }

    public function test_character_swap_transfers_goods_ammunition_and_cash_atomically_and_can_be_reversed(): void
    {
        $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()]);
        $this->inventoryInitialize(10, [$this->position('item:bogen'), $this->position('ammo:pfeile', 30)], $other);
        $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil', 2)]]);
        $transaction = $this->inventoryBook('swap', ['incoming' => [$this->position('item:bogen'), $this->position('ammo:pfeile', 12)],
            'outgoing' => [$this->position('item:seil')], 'amount' => 20, 'cash_direction' => 'expense',
            'other_character_id' => $other->id, 'other_revision' => $other->fresh()->revision]);
        $state = $this->state();
        $otherState = app(RpgInventoryAdapter::class)->state($other->fresh()->payload);
        $this->assertSame(80, $state['bucks']);
        $this->assertSame(30, $otherState['bucks']);
        $this->assertSame(12, $state['stocks']['ammo:pfeile']);
        $this->assertSame(18, $otherState['stocks']['ammo:pfeile']);
        $this->assertSame(1, $otherState['stocks']['item:seil']);
        $this->assertArrayNotHasKey('item:bogen', $otherState['stocks']);
        $this->assertCount(2, $transaction->entries);
        $this->inventoryBook('reversal', ['reversal_of' => $transaction->id, 'other_revision' => $other->fresh()->revision]);
        $this->assertSame(100, $this->state()['bucks']);
        $this->assertSame(10, app(RpgInventoryAdapter::class)->state($other->fresh()->payload)['bucks']);
    }

    #[DataProvider('unfundedOverlappingSwaps')]
    public function test_overlapping_swap_quantities_cannot_hide_insufficient_pre_trade_stock(bool $external, int $owned, int $otherOwned, int $outgoing, int $incoming, string $position): void
    {
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
        $input = $this->inventoryInput('swap', $party + ['outgoing' => [$this->position($position, $outgoing)],
            'incoming' => [$this->position($position, $incoming)], 'amount' => 10, 'cash_direction' => 'expense',
            'preview_hash' => str_repeat('0', 64)]);
        $service = app(RpgInventoryService::class);

        foreach (['preview', 'book'] as $method) {
            try {
                $service->{$method}($this->leader, $this->character->id, $input);
                $this->fail('Overlapping quantities concealed missing outgoing stock in '.$method.'.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('Warenausgang', $exception->errors()['inventory'][0]);
            }
        }

        $this->assertSame($before->payload, $this->character->fresh()->payload);
        $this->assertSame($before->revision, $this->character->fresh()->revision);
        if ($other !== null) {
            $this->assertSame($otherBefore->payload, $other->fresh()->payload);
            $this->assertSame($otherBefore->revision, $other->fresh()->revision);
        }
        $this->assertDatabaseCount('rpg_inventory_transactions', $transactions);
        $this->assertDatabaseCount('rpg_inventory_entries', $entries);
    }

    public static function unfundedOverlappingSwaps(): array
    {
        return [
            'external missing item' => [true, 0, 0, 1, 1, 'item:bogen'],
            'external insufficient item' => [true, 1, 0, 2, 1, 'item:bogen'],
            'both characters missing item' => [false, 0, 0, 1, 1, 'item:bogen'],
            'initiator missing item' => [false, 0, 1, 1, 1, 'item:bogen'],
            'counterparty missing item' => [false, 1, 0, 1, 1, 'item:bogen'],
            'initiator insufficient quantity' => [false, 1, 2, 2, 1, 'item:bogen'],
            'counterparty insufficient quantity' => [false, 2, 1, 1, 2, 'item:bogen'],
            'both characters insufficient quantity' => [false, 1, 1, 2, 2, 'item:bogen'],
            'missing ammunition' => [false, 0, 0, 1, 1, 'ammo:pfeile'],
            'counterparty missing clothing' => [false, 1, 0, 1, 1, 'clothing:kleidung-einfach'],
        ];
    }

    #[DataProvider('fundedOverlappingSwaps')]
    public function test_overlapping_swaps_remain_valid_when_both_characters_own_the_outgoing_goods(int $owned, int $otherOwned, int $outgoing, int $incoming): void
    {
        $this->inventoryBook('grant', ['incoming' => [$this->position('item:bogen', $owned)]]);
        $payload = $this->progressionPayload();
        $unarmed = ['id' => 'faustschlag-tritt', 'quantity' => 1];
        $payload['equipment']['items'] = [$unarmed];
        $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $payload]);
        $this->inventoryInitialize(100, [$this->position('item:bogen', $otherOwned)], $other);
        $before = $this->character->fresh();
        $otherBefore = $other->fresh();
        $transaction = $this->inventoryBook('swap', ['incoming' => [$this->position('item:bogen', $incoming)],
            'outgoing' => [$this->position('item:bogen', $outgoing)], 'amount' => 10, 'cash_direction' => 'expense',
            'other_character_id' => $other->id, 'other_revision' => $otherBefore->revision]);

        $otherState = app(RpgInventoryAdapter::class)->state($other->fresh()->payload);
        $this->assertSame($owned - $outgoing + $incoming, $this->state()['stocks']['item:bogen']);
        $this->assertSame($otherOwned - $incoming + $outgoing, $otherState['stocks']['item:bogen']);
        $this->assertSame(90, $this->state()['bucks']);
        $this->assertSame(110, $otherState['bucks']);
        $this->assertContains($unarmed, $other->fresh()->payload['equipment']['items']);
        $this->assertArrayNotHasKey('item:faustschlag-tritt', $otherState['stocks']);
        $this->assertSame($before->revision + 1, $this->character->fresh()->revision);
        $this->assertSame($otherBefore->revision + 1, $other->fresh()->revision);
        $this->assertCount(2, $transaction->entries);
    }

    public static function fundedOverlappingSwaps(): array
    {
        return ['equal quantities' => [1, 1, 1, 1], 'different quantities' => [2, 3, 2, 3]];
    }

    public function test_insufficient_counterparty_stock_rolls_back_both_sides(): void
    {
        $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $this->progressionPayload()]);
        $this->inventoryInitialize(0, [], $other);
        $before = $this->character->fresh();
        $otherBefore = $other->fresh();
        try {
            $this->inventoryBook('purchase', ['incoming' => [$this->position('item:bogen')], 'amount' => 30,
                'other_character_id' => $other->id, 'other_revision' => $otherBefore->revision]);
            $this->fail('Expected insufficient inventory.');
        } catch (ValidationException) {
            $this->assertSame($before->payload, $this->character->fresh()->payload);
            $this->assertSame($otherBefore->payload, $other->fresh()->payload);
            $this->assertSame($before->revision, $this->character->fresh()->revision);
            $this->assertSame($otherBefore->revision, $other->fresh()->revision);
            $this->assertDatabaseCount('rpg_inventory_transactions', 2);
        }
    }

    #[DataProvider('invalidTrades')]
    public function test_invalid_trade_has_no_partial_money_or_goods_changes(array $overrides): void
    {
        $input = $this->inventoryInput('purchase', array_replace(['incoming' => [$this->position('item:bogen')], 'amount' => 30, 'external_party' => 'Händler'], $overrides));
        $before = $this->character->fresh()->payload;
        try {
            app(RpgInventoryService::class)->preview($this->leader, $this->character->id, $input);
            $this->fail('Invalid trade accepted.');
        } catch (ValidationException) {
            $this->assertSame($before, $this->character->fresh()->payload);
            $this->assertDatabaseCount('rpg_inventory_transactions', 1);
        }
    }

    public static function invalidTrades(): array
    {
        return [
            'insufficient bucks' => [['amount' => 101, 'price_reason' => 'Teuer']],
            'free purchase' => [['amount' => 0]], 'missing counterparty' => [['external_party' => '']],
            'missing goods' => [['incoming' => []]], 'unexplained price' => [['amount' => 29]],
            'missing ammo price' => [['incoming' => [['id' => 'ammo:revolver', 'quantity' => 30]]]],
            'missing winner' => [['success_points' => 1]],
            'ambiguous counterparty' => [['other_character_id' => 99999]],
            'sale of unowned good' => [['kind' => 'sale', 'incoming' => [], 'outgoing' => [['id' => 'item:bogen', 'quantity' => 1]]]],
        ];
    }

    private function state(): array
    {
        return app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
    }
}
