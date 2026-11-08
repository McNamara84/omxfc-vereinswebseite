<?php

declare(strict_types=1);

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

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgInventoryFixtures::class);

beforeEach(function (): void {
    $this->progressionFixtures();
    $this->inventoryInitialize();
});

test('stale revision or tampered preview cannot book', function (): void {
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
            expect($this->character->fresh()->payload)->toBe($before);
        }
    }
    $this->assertDatabaseCount('rpg_inventory_transactions', 2);
});

test('missing preview cannot book', function (): void {
    expect(fn () => app(RpgInventoryService::class)->book($this->leader, $this->character->id, $this->inventoryInput('grant', ['amount' => 5])))->toThrow(ValidationException::class);
});

test('missing character is a not found result and not an internal error', function (): void {
    $id = $this->character->id;
    $input = $this->inventoryInput('grant', ['amount' => 5]);
    $this->character->delete();
    expect(fn () => app(RpgInventoryService::class)->preview($this->leader, $id, $input))->toThrow(ModelNotFoundException::class);
});

test('reversal cannot hide an unrelated additional stock change', function (): void {
    $transaction = $this->inventoryBook('grant', ['amount' => 5]);
    expect(fn () => $this->inventoryBook('reversal', ['reversal_of' => $transaction->id, 'incoming' => [$this->position('item:bogen')]]))->toThrow(ValidationException::class);
});

test('leadership change between preview and booking revokes authority', function (): void {
    $input = $this->inventoryInput('grant', ['amount' => 5]);
    $service = app(RpgInventoryService::class);
    $input['preview_hash'] = $service->preview($this->leader, $this->character->id, $input)['preview_hash'];
    $this->rpgTeam->update(['user_id' => $this->player->id]);
    expect(fn () => $service->book($this->leader, $this->character->id, $input))->toThrow(AuthorizationException::class);
});

test('departed owner retains read access but cannot receive new bookings', function (): void {
    $this->rpgTeam->users()->detach($this->player);
    $this->actingAs($this->player)->get(route('rpg.inventory.show', $this->character))->assertOk()->assertDontSee('inventory-manager');
    expect(fn () => app(RpgInventoryService::class)->preview($this->leader, $this->character->id, $this->inventoryInput('grant', ['amount' => 5])))->toThrow(AuthorizationException::class);
});

test('journal failure rolls back money state and revision', function (): void {
    $before = $this->character->fresh();
    RpgInventoryEntry::creating(fn () => throw new \RuntimeException('Journal unavailable.'));
    try {
        $this->inventoryBook('grant', ['amount' => 5]);
        $this->fail('Journal failure expected.');
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Journal unavailable.')
            ->and($this->character->fresh()->payload)->toBe($before->payload)
            ->and($this->character->fresh()->revision)->toBe($before->revision);
        $this->assertDatabaseCount('rpg_inventory_transactions', 1);
    } finally {
        RpgInventoryEntry::flushEventListeners();
        RpgInventoryEntry::clearBootedModels();
    }
});

test('reversal of spent goods or changed metadata is rejected', function (): void {
    $transaction = $this->inventoryBook('grant', ['incoming' => [$this->position('item:bogen')]]);
    $this->inventoryBook('loss', ['outgoing' => [$this->position('item:bogen')]]);
    try {
        $this->inventoryBook('reversal', ['reversal_of' => $transaction->id]);
        $this->fail('Missing stock expected.');
    } catch (ValidationException) {
        expect($transaction->fresh()->reversal)->toBeNull();
    }
    $transaction = $this->inventoryBook('equip', ['metadata' => ['notes' => 'Erste Änderung']]);
    $this->inventoryBook('equip', ['metadata' => ['notes' => 'Spätere Änderung']]);
    expect(fn () => $this->inventoryBook('reversal', ['reversal_of' => $transaction->id]))->toThrow(ValidationException::class);
});

test('journal and rollback cannot destroy recorded bookings', function (): void {
    $migration = require database_path('migrations/2026_11_07_120000_create_rpg_inventory.php');
    try {
        $migration->down();
        $this->fail('Destructive rollback accepted.');
    } catch (\LogicException) {
        expect(Schema::hasTable('rpg_inventory_entries'))->toBeTrue();
        $this->assertDatabaseCount('rpg_inventory_transactions', 1);
    }
    expect(fn () => RpgInventoryTransaction::firstOrFail()->delete())->toThrow(\LogicException::class);
});

test('character deletion removes private entries', function (): void {
    $this->character->delete();
    $this->assertDatabaseCount('rpg_inventory_entries', 0);
    $this->assertDatabaseCount('rpg_characters', 0);
});

test('legacy magazines are suggestions until explicitly confirmed', function (): void {
    $payload = $this->progressionPayload();
    $payload['equipment'] = ['items' => [['id' => 'revolver', 'quantity' => 1]], 'ammunition' => [['source' => 'Revolver', 'quantity' => 4, 'unit' => 'Magazine']]];
    $state = app(RpgInventoryAdapter::class)->state($payload);
    expect($state['confirmed'])->toBeFalse()
        ->and($state['bucks'])->toBeNull()
        ->and($state['stocks']['ammo:revolver'])->toBe(24);
    $payload['equipment'] = ['items' => [['id' => 'energiegewehr', 'quantity' => 2]], 'ammunition' => [['source' => 'Energiegewehr', 'quantity' => 4, 'unit' => 'Energiezellen']]];
    expect(app(RpgInventoryAdapter::class)->state($payload)['stocks']['ammo:energiegewehr'])->toBe(200);
});

test('unknown legacy positions can be explicitly removed during takeover', function (): void {
    $payload = $this->progressionPayload();
    $payload['equipment'] = ['items' => [['id' => 'old-item', 'quantity' => 1]], 'ammunition' => [['source' => 'Unbekannt', 'quantity' => 4, 'unit' => 'Kisten']]];
    $other = RpgCharacter::factory()->create(['user_id' => $this->player->id, 'payload' => $payload]);
    $state = app(RpgInventoryAdapter::class)->state($payload);
    expect($state['stocks'])->toHaveKey('unknown:Unbekannt');
    $this->actingAs($this->leader)->get(route('rpg.inventory.show', $other))->assertOk()->assertSee('Unbekannter Altbestand');
    $this->inventoryInitialize(0, [$this->position('item:old-item', 0), $this->position('unknown:Unbekannt', 0)], $other);
    expect(app(RpgInventoryAdapter::class)->state($other->fresh()->payload)['stocks'])->toBe([])
        ->and($other->fresh()->initial_payload)->toBe($payload);
});

test('non inventory advancement does not generate an inventory transaction', function (): void {
    $this->credit();
    $service = app(RpgCharacterAdvancementService::class);
    $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
    $service->decide($this->leader, $request->id, 'approved');
    $this->assertDatabaseCount('rpg_inventory_transactions', 1);
    expect($this->character->fresh()->payload['equipment']['bucks_balance'])->toBe(100);
});

test('correction and equipment reversals preserve independent stock and metadata', function (): void {
    $transaction = $this->inventoryBook('correct', ['amount' => 120, 'set' => [$this->position('item:seil', 2)]]);
    $equipment = $this->inventoryBook('equip', ['metadata' => ['notes' => 'Korrigierte Ausrüstung']]);
    expect($transaction->entries->first()->character->id)->toBe($this->character->id);
    $this->inventoryBook('grant', ['incoming' => [$this->position('item:bogen')]]);
    $this->inventoryBook('reversal', ['reversal_of' => $transaction->id]);
    $state = app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
    expect($state['bucks'])->toBe(100)
        ->and($state['notes'])->toBe('Korrigierte Ausrüstung')
        ->and($state['stocks']['item:bogen'])->toBe(1)
        ->and($state['stocks'])->not->toHaveKey('item:seil');
    $this->inventoryBook('reversal', ['reversal_of' => $equipment->id]);
    $state = app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
    expect($state['notes'])->toBe('')
        ->and($state['bucks'])->toBe(100)
        ->and($state['stocks']['item:bogen'])->toBe(1)
        ->and($state['stocks'])->not->toHaveKey('item:seil');
});

test('saved pdf includes inventory without any experience entries', function (): void {
    $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil')], 'reason' => 'Fund für den PDF-Verlauf']);
    $history = app(RpgInventoryHistory::class)->forCharacter($this->character->fresh());
    $payload = $this->character->fresh()->payload;
    $payload['inventory_history'] = $history;
    $payload['experience'] = ['received' => 0, 'spent' => 0, 'balance' => 0, 'entries' => []];
    $combat = app(RpgCharacterCombatCalculator::class)->calculate($payload);
    $sheet = app(RpgCharacterSheetPresenter::class)->present($payload, $combat);
    $html = view('rpg.char-sheet', ['sheet' => $sheet, 'combat' => $combat])->render();
    expect($html)->toContain('Inventarverlauf')
        ->toContain('Fund für den PDF-Verlauf')
        ->toContain('Bucks: 100');
    $this->actingAs($this->player)->get(route('rpg.characters.pdf', $this->character))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(DB::table('rpg_experience_entries')->count())->toBe(0);
});
