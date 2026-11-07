<?php

namespace Tests\Feature;

use App\Models\RpgCharacter;
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
