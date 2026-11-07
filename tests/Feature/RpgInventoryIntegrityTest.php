<?php

namespace Tests\Feature;

use App\Models\RpgCharacter;
use App\Models\RpgInventoryEntry;
use App\Models\RpgInventoryTransaction;
use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCharacterSheetPresenter;
use App\Services\RpgInventoryAdapter;
use App\Services\RpgInventoryHistory;
use App\Services\RpgInventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\RpgInventoryFixtures;
use Tests\TestCase;

class RpgInventoryIntegrityTest extends TestCase
{
    use RefreshDatabase, RpgInventoryFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progressionFixtures();
        $this->inventoryInitialize();
    }

    public function test_stale_revision_or_tampered_preview_cannot_book(): void
    {
        $input = $this->inventoryInput('grant', ['amount' => 5]);
        $service = app(RpgInventoryService::class);
        $input['preview_hash'] = $service->preview($this->leader, $this->character->id, $input)['preview_hash'];
        $this->inventoryBook('money', ['amount' => 10]);
        $before = $this->character->fresh()->payload;
        foreach ([$input, array_replace($input, ['revision' => $this->character->fresh()->revision, 'preview_hash' => str_repeat('a', 64)])] as $attempt) {
            try {
                $service->book($this->leader, $this->character->id, $attempt);
                $this->fail('Stale preview accepted.');
            } catch (ValidationException) {
                $this->assertSame($before, $this->character->fresh()->payload);
            }
        }
        $this->assertDatabaseCount('rpg_inventory_transactions', 2);
    }

    public function test_missing_preview_cannot_book(): void
    {
        $this->expectException(ValidationException::class);
        app(RpgInventoryService::class)->book($this->leader, $this->character->id, $this->inventoryInput('grant', ['amount' => 5]));
    }

    public function test_missing_character_is_a_not_found_result_and_not_an_internal_error(): void
    {
        $id = $this->character->id;
        $input = $this->inventoryInput('grant', ['amount' => 5]);
        $this->character->delete();
        $this->expectException(ModelNotFoundException::class);
        app(RpgInventoryService::class)->preview($this->leader, $id, $input);
    }

    public function test_reversal_cannot_hide_an_unrelated_additional_stock_change(): void
    {
        $transaction = $this->inventoryBook('grant', ['amount' => 5]);
        $this->expectException(ValidationException::class);
        $this->inventoryBook('reversal', ['reversal_of' => $transaction->id, 'incoming' => [$this->position('item:bogen')]]);
    }

    public function test_leadership_change_between_preview_and_booking_revokes_authority(): void
    {
        $input = $this->inventoryInput('grant', ['amount' => 5]);
        $service = app(RpgInventoryService::class);
        $input['preview_hash'] = $service->preview($this->leader, $this->character->id, $input)['preview_hash'];
        $this->rpgTeam->update(['user_id' => $this->player->id]);
        $this->expectException(AuthorizationException::class);
        $service->book($this->leader, $this->character->id, $input);
    }

    public function test_departed_owner_retains_read_access_but_cannot_receive_new_bookings(): void
    {
        $this->rpgTeam->users()->detach($this->player);
        $this->actingAs($this->player)->get(route('rpg.inventory.show', $this->character))->assertOk()->assertDontSee('inventory-manager');
        $this->expectException(AuthorizationException::class);
        app(RpgInventoryService::class)->preview($this->leader, $this->character->id, $this->inventoryInput('grant', ['amount' => 5]));
    }

    public function test_journal_failure_rolls_back_money_state_and_revision(): void
    {
        $before = $this->character->fresh();
        RpgInventoryEntry::creating(fn () => throw new \RuntimeException('Journal unavailable.'));
        try {
            $this->inventoryBook('grant', ['amount' => 5]);
            $this->fail('Journal failure expected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Journal unavailable.', $exception->getMessage());
            $this->assertSame($before->payload, $this->character->fresh()->payload);
            $this->assertSame($before->revision, $this->character->fresh()->revision);
            $this->assertDatabaseCount('rpg_inventory_transactions', 1);
        } finally {
            RpgInventoryEntry::flushEventListeners();
            RpgInventoryEntry::clearBootedModels();
        }
    }

    public function test_reversal_of_spent_goods_or_changed_metadata_is_rejected(): void
    {
        $transaction = $this->inventoryBook('grant', ['incoming' => [$this->position('item:bogen')]]);
        $this->inventoryBook('loss', ['outgoing' => [$this->position('item:bogen')]]);
        try {
            $this->inventoryBook('reversal', ['reversal_of' => $transaction->id]);
            $this->fail('Missing stock expected.');
        } catch (ValidationException) {
            $this->assertNull($transaction->fresh()->reversal);
        }
        $transaction = $this->inventoryBook('equip', ['metadata' => ['notes' => 'Erste Änderung']]);
        $this->inventoryBook('equip', ['metadata' => ['notes' => 'Spätere Änderung']]);
        $this->expectException(ValidationException::class);
        $this->inventoryBook('reversal', ['reversal_of' => $transaction->id]);
    }

    public function test_journal_and_rollback_cannot_destroy_recorded_bookings(): void
    {
        $migration = require database_path('migrations/2026_11_07_120000_create_rpg_inventory.php');
        try {
            $migration->down();
            $this->fail('Destructive rollback accepted.');
        } catch (\LogicException) {
            $this->assertTrue(Schema::hasTable('rpg_inventory_entries'));
            $this->assertDatabaseCount('rpg_inventory_transactions', 1);
        }
        $this->expectException(\LogicException::class);
        RpgInventoryTransaction::firstOrFail()->delete();
    }

    public function test_character_deletion_removes_private_entries(): void
    {
        $this->character->delete();
        $this->assertDatabaseCount('rpg_inventory_entries', 0);
        $this->assertDatabaseCount('rpg_characters', 0);
    }

    public function test_legacy_magazines_are_suggestions_until_explicitly_confirmed(): void
    {
        $payload = $this->progressionPayload();
        $payload['equipment'] = ['items' => [['id' => 'revolver', 'quantity' => 1]], 'ammunition' => [['source' => 'Revolver', 'quantity' => 4, 'unit' => 'Magazine']]];
        $state = app(RpgInventoryAdapter::class)->state($payload);
        $this->assertFalse($state['confirmed']);
        $this->assertNull($state['bucks']);
        $this->assertSame(24, $state['stocks']['ammo:revolver']);
        $payload['equipment'] = ['items' => [['id' => 'energiegewehr', 'quantity' => 2]], 'ammunition' => [['source' => 'Energiegewehr', 'quantity' => 4, 'unit' => 'Energiezellen']]];
        $this->assertSame(200, app(RpgInventoryAdapter::class)->state($payload)['stocks']['ammo:energiegewehr']);
    }

    public function test_unknown_legacy_positions_can_be_explicitly_removed_during_takeover(): void
    {
        $payload = $this->progressionPayload();
        $payload['equipment'] = ['items' => [['id' => 'old-item', 'quantity' => 1]], 'ammunition' => [['source' => 'Unbekannt', 'quantity' => 4, 'unit' => 'Kisten']]];
        $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $payload]);
        $state = app(RpgInventoryAdapter::class)->state($payload);
        $this->assertArrayHasKey('unknown:Unbekannt', $state['stocks']);
        $this->actingAs($this->leader)->get(route('rpg.inventory.show', $other))->assertOk()->assertSee('Unbekannter Altbestand');
        $this->inventoryInitialize(0, [$this->position('item:old-item', 0), $this->position('unknown:Unbekannt', 0)], $other);
        $this->assertSame([], app(RpgInventoryAdapter::class)->state($other->fresh()->payload)['stocks']);
        $this->assertSame($payload, $other->fresh()->initial_payload);
    }

    public function test_non_inventory_advancement_does_not_generate_an_inventory_transaction(): void
    {
        $this->credit();
        $service = app(RpgCharacterAdvancementService::class);
        $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
        $service->decide($this->leader, $request->id, 'approved');
        $this->assertDatabaseCount('rpg_inventory_transactions', 1);
        $this->assertSame(100, $this->character->fresh()->payload['equipment']['bucks_balance']);
    }

    public function test_correction_and_equipment_reversals_preserve_independent_stock_and_metadata(): void
    {
        $transaction = $this->inventoryBook('correct', ['amount' => 120, 'set' => [$this->position('item:seil', 2)]]);
        $equipment = $this->inventoryBook('equip', ['metadata' => ['notes' => 'Korrigierte Ausrüstung']]);
        $this->assertSame($this->character->id, $transaction->entries->first()->character->id);
        $this->inventoryBook('grant', ['incoming' => [$this->position('item:bogen')]]);
        $this->inventoryBook('reversal', ['reversal_of' => $transaction->id]);
        $state = app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
        $this->assertSame(100, $state['bucks']);
        $this->assertSame('Korrigierte Ausrüstung', $state['notes']);
        $this->assertSame(1, $state['stocks']['item:bogen']);
        $this->assertArrayNotHasKey('item:seil', $state['stocks']);
        $this->inventoryBook('reversal', ['reversal_of' => $equipment->id]);
        $state = app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
        $this->assertSame('', $state['notes']);
        $this->assertSame(100, $state['bucks']);
        $this->assertSame(1, $state['stocks']['item:bogen']);
        $this->assertArrayNotHasKey('item:seil', $state['stocks']);
    }

    public function test_saved_pdf_includes_inventory_without_any_experience_entries(): void
    {
        $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil')], 'reason' => 'Fund für den PDF-Verlauf']);
        $history = app(RpgInventoryHistory::class)->forCharacter($this->character->fresh());
        $payload = $this->character->fresh()->payload;
        $payload['inventory_history'] = $history;
        $payload['experience'] = ['received' => 0, 'spent' => 0, 'balance' => 0, 'entries' => []];
        $combat = app(RpgCharacterCombatCalculator::class)->calculate($payload);
        $sheet = app(RpgCharacterSheetPresenter::class)->present($payload, $combat);
        $html = view('rpg.char-sheet', ['sheet' => $sheet, 'combat' => $combat])->render();
        $this->assertStringContainsString('Inventarverlauf', $html);
        $this->assertStringContainsString('Fund für den PDF-Verlauf', $html);
        $this->assertStringContainsString('Bucks: 100', $html);
        $this->actingAs($this->player)->get(route('rpg.characters.pdf', $this->character))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(0, DB::table('rpg_experience_entries')->count());
    }
}
