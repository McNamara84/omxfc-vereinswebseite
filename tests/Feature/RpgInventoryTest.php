<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\RpgInventoryEntry;
use App\Models\RpgInventoryTransaction;
use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCharacterSheetPresenter;
use App\Services\RpgInventoryAdapter;
use App\Services\RpgInventoryHistory;
use App\Services\RpgInventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgInventoryTestFixtures::class);
dataset('RpgInventoryTest.nonEquipMetadata', static function (): array {
    $cases = [];
    foreach (['initialize', 'grant', 'loss', 'correct', 'money', 'purchase', 'sale', 'swap', 'regenerate', 'reversal'] as $kind) {
        foreach (['notes' => 'Versteckte Änderung', 'armor' => 'kettenhemd', 'shield' => 'holzschild', 'clothing' => 'kleidung-wanderer'] as $field => $value) {
            $cases[$kind.': '.$field] = [$kind, [$field => $value]];
        }
    }
    foreach (['empty string' => '', 'null' => null, 'whitespace' => '  '] as $name => $value) {
        $cases['grant: notes '.$name] = ['grant', ['notes' => $value]];
    }

    return $cases;
});
dataset('RpgInventoryTest.invalidBookings', static fn (): array => ['unknown' => [['incoming' => [['id' => 'item:unknown', 'quantity' => 1]]]], 'virtual' => [['incoming' => [['id' => 'item:faustschlag-tritt', 'quantity' => 1]]]], 'zero' => [['incoming' => [['id' => 'item:seil', 'quantity' => 0]]]], 'too many' => [['incoming' => [['id' => 'item:seil', 'quantity' => 21]]]], 'negative' => [['incoming' => [['id' => 'item:seil', 'quantity' => -1]]]], 'fraction' => [['incoming' => [['id' => 'item:seil', 'quantity' => 1.5]]]], 'no change' => [[]], 'whitespace reason' => [['reason' => '  ']], 'metadata injection' => [['metadata' => ['advantages' => ['Zäh']]]], 'payload injection' => [['payload' => ['attributes' => ['st' => 100]]]], 'money overflow' => [['amount' => 1000000001]], 'non-trade peer' => [['other_character_id' => 100]], 'no energy weapon' => [['kind' => 'regenerate', 'minutes' => 1]], 'unowned armor' => [['kind' => 'equip', 'metadata' => ['armor' => 'kettenhemd']]]]);
beforeEach(function (): void {
    $this->progressionFixtures();
});
test('initialization confirms zero without changing initial payload or experience', function (): void {
    $original = $this->character->payload;
    $this->inventoryInitialize(0);
    $character = $this->character->fresh();
    expect($character->payload['equipment']['bucks_balance'])->toBe(0)
        ->and($character->payload['equipment']['stock_confirmed_at'])->not->toBeEmpty()
        ->and($character->initial_payload)->toBe($original)
        ->and($character->experienceBalance())->toBe(0)
        ->and($character->revision)->toBe(1);
    $this->assertDatabaseCount('rpg_inventory_transactions', 1);
    $this->assertDatabaseCount('rpg_inventory_entries', 1);
});
test('later high tech and more than six items need no advantage or experience', function (): void {
    $this->inventoryInitialize();
    $this->inventoryBook('grant', ['incoming' => [$this->position('item:revolver'), $this->position('item:seil', 7), $this->position('ammo:revolver', 12)]]);
    $state = $this->state();
    expect($state['stocks']['item:seil'])->toBe(7)
        ->and($state['stocks']['ammo:revolver'])->toBe(12)
        ->and($this->character->fresh()->payload['advantages'])->toBe([])
        ->and($this->character->experienceBalance())->toBe(0);
});
test('legacy takeover and later bookings preserve the selected unarmed attack', function (): void {
    $payload = $this->character->payload;
    $unarmed = ['id' => 'faustschlag-tritt', 'quantity' => 1, 'name' => 'Faustschlag / Tritt', 'counts_toward_limit' => false];
    $payload['equipment']['items'] = [$unarmed, ['id' => 'messer-dolch', 'quantity' => 1]];
    $this->character->update(['payload' => $payload]);
    $calculator = app(RpgCharacterCombatCalculator::class);
    $expected = collect($calculator->calculate($payload)['weapons'])->firstWhere('id', 'faustschlag-tritt');
    $initial = $this->character->initial_payload;
    $this->inventoryInitialize(0);
    expect($this->character->fresh()->payload['equipment']['items'])->toContain($unarmed);
    $grant = $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil')]]);
    $this->inventoryBook('loss', ['outgoing' => [$this->position('item:messer-dolch')]]);
    $this->inventoryBook('reversal', ['reversal_of' => $grant->id]);
    $saved = $this->character->fresh();
    expect($saved->payload['equipment']['items'])->toBe([$unarmed])
        ->and($this->state()['stocks'])->toBe([])
        ->and($saved->initial_payload)->toBe($initial);
    $combat = $calculator->calculate($saved->payload);
    expect(collect($combat['weapons'])->firstWhere('id', 'faustschlag-tritt'))->toBe($expected);
    $sheet = app(RpgCharacterSheetPresenter::class)->present($saved->payload, $combat);
    $html = view('rpg.char-sheet', ['sheet' => $sheet, 'combat' => $combat])->render();
    expect($html)->toContain('Faustschlag / Tritt')
        ->toContain('1W6-1+VM');
});
test('rebuilding zero stock preserves only a previously selected virtual attack', function (): void {
    $adapter = app(RpgInventoryAdapter::class);
    $state = $adapter->state($this->character->payload);
    $state['bucks'] = 0;
    $state['stocks'] = ['item:bogen' => 0];
    expect($adapter->equipment($state)['items'])->toBe([]);
    $unarmed = ['id' => 'faustschlag-tritt', 'quantity' => 1];
    $equipment = $adapter->equipment($state, ['items' => [$unarmed]]);
    expect($equipment['items'])->toBe([$unarmed])
        ->and($equipment['ammunition'])->toBe([])
        ->and($adapter->state(['equipment' => $equipment])['stocks'])->toBe([]);
});
test('clothing is retained when changed and armor is deactivated when removed', function (): void {
    $this->inventoryInitialize();
    $this->inventoryBook('grant', ['incoming' => [$this->position('clothing:kleidung-einfach'), $this->position('clothing:kleidung-wanderer'), $this->position('item:kettenhemd')]]);
    $this->inventoryBook('equip', ['metadata' => ['clothing' => 'kleidung-einfach', 'armor' => 'kettenhemd', 'notes' => 'Persönliche Ausrüstung']]);
    $this->inventoryBook('equip', ['metadata' => ['clothing' => 'kleidung-wanderer']]);
    $preview = app(RpgInventoryService::class)->preview($this->leader, $this->character->id, $this->inventoryInput('loss', ['outgoing' => [$this->position('item:kettenhemd')]]));
    expect($preview['entries'][$this->character->id]['after']['armor'])->toBe('');
    $this->inventoryBook('loss', ['outgoing' => [$this->position('item:kettenhemd')]]);
    expect($this->state()['stocks']['clothing:kleidung-einfach'])->toBe(1)
        ->and($this->state()['clothing'])->toBe('kleidung-wanderer')
        ->and($this->character->fresh()->payload['character']['equipment'])->toBe('Persönliche Ausrüstung');
});
test('munition is not refilled by other equipment or high tech advantage', function (): void {
    $this->inventoryInitialize(100, [$this->position('item:bogen'), $this->position('ammo:pfeile', 30)]);
    $this->inventoryBook('loss', ['outgoing' => [$this->position('ammo:pfeile', 7)]]);
    $this->inventoryBook('grant', ['incoming' => [$this->position('item:bogen')]]);
    $this->credit();
    $service = app(RpgCharacterAdvancementService::class);
    $input = $this->advancementInput([$this->operation(type: 'advantage', name: 'High-Tech-Ausrüstung', extra: ['items' => ['revolver', 'revolver', 'revolver', 'revolver']])]);
    $input['revision'] = $this->character->fresh()->revision;
    $request = $service->submit($this->player, $this->character->id, $input);
    $service->decide($this->leader, $request->id, 'approved');
    expect($this->state()['stocks']['ammo:pfeile'])->toBe(23)
        ->and($this->state()['stocks'])->not->toHaveKey('ammo:revolver')
        ->and($this->character->experienceBalance())->toBe(40);
    $this->assertDatabaseHas('rpg_inventory_transactions', ['advancement_id' => $request->id, 'kind' => 'advancement']);
});
test('new character ammunition converts starting packages once', function (): void {
    $payload = $this->progressionPayload();
    $payload['equipment']['items'] = [['id' => 'armbrust', 'quantity' => 1], ['id' => 'revolver', 'quantity' => 1], ['id' => 'energiegewehr', 'quantity' => 1]];
    $payload = app(RpgInventoryAdapter::class)->initializeNew($payload);
    $state = app(RpgInventoryAdapter::class)->state($payload);
    expect($state['stocks']['ammo:bolzen'])->toBe(30)
        ->and($state['stocks']['ammo:revolver'])->toBe(24)
        ->and($state['stocks']['ammo:energiegewehr'])->toBe(100);
});
test('energy regeneration is capped and needs game time', function (): void {
    $this->inventoryInitialize(0, [$this->position('item:energiegewehr'), $this->position('ammo:energiegewehr', 80)]);
    $this->inventoryBook('regenerate', ['minutes' => 7]);
    expect($this->state()['stocks']['ammo:energiegewehr'])->toBe(87);
    $this->inventoryBook('regenerate', ['minutes' => 100]);
    expect($this->state()['stocks']['ammo:energiegewehr'])->toBe(100);
});
test('inventory change supersedes a pending advancement without spending experience', function (): void {
    $this->inventoryInitialize();
    $this->credit();
    $input = $this->advancementInput();
    $input['revision'] = $this->character->fresh()->revision;
    $request = app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $input);
    $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil')]]);
    expect($request->fresh()->status)->toBe('superseded')
        ->and($this->character->experienceBalance())->toBe(60);
    $this->actingAs($this->player)->get(route('rpg.advancements.show', $request))->assertOk()->assertSee('Durch Charakteränderung überholt')->assertSee('Auf aktuellem Stand erneut prüfen');
    $this->actingAs($this->player)->get(route('rpg.characters.improve', $this->character))->assertOk()->assertViewHas('config', fn ($config) => $config['renewedOperations'] === []);
    $this->get(route('rpg.characters.improve', ['rpgCharacter' => $this->character, 'erneuern' => $request->id]))->assertOk()->assertViewHas('config', fn ($config) => $config['renewedOperations'] === $request->operations);
    expect(fn () => app(RpgCharacterAdvancementService::class)->decide($this->leader, $request->id, 'approved'))->toThrow(ValidationException::class);
});
test('identical retries book only once but changed key content is rejected', function (): void {
    $this->inventoryInitialize();
    $input = $this->inventoryInput('grant', ['incoming' => [$this->position('item:seil')]]);
    $service = app(RpgInventoryService::class);
    $input['preview_hash'] = $service->preview($this->leader, $this->character->id, $input)['preview_hash'];
    $first = $service->book($this->leader, $this->character->id, $input);
    expect($service->book($this->leader, $this->character->id, $input)->id)->toBe($first->id)
        ->and($this->state()['stocks']['item:seil'])->toBe(1);
    $input['reason'] = 'Andere Angaben';
    expect(fn () => $service->book($this->leader, $this->character->id, $input))->toThrow(ValidationException::class);
});
test('reversal preserves later stock changes and is recorded once', function (): void {
    $this->inventoryInitialize();
    $transaction = $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil', 2)], 'amount' => 20]);
    $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil')], 'amount' => 5]);
    $this->inventoryBook('reversal', ['reversal_of' => $transaction->id]);
    expect($this->state()['stocks']['item:seil'])->toBe(1)
        ->and($this->state()['bucks'])->toBe(105)
        ->and($transaction->fresh()->reversal)->not->toBeNull()
        ->and(fn () => $this->inventoryBook('reversal', ['reversal_of' => $transaction->id]))->toThrow(ValidationException::class);
});
test('owner and admin cannot write and foreign members cannot read', function (): void {
    $this->actingAs($this->player)->get(route('rpg.inventory.show', $this->character))->assertOk()->assertDontSee('inventory-manager');
    $this->actingAs($this->player)->postJson(route('rpg.inventory.preview', $this->character), $this->inventoryInput('initialize', ['amount' => 0]))->assertForbidden();
    $admin = $this->progressionMember(Role::Admin);
    $this->actingAs($admin)->get(route('rpg.inventory.show', $this->character))->assertForbidden();
    $this->actingAs($admin)->get(route('rpg.inventory.index'))->assertForbidden();
    expect(fn () => app(RpgInventoryService::class)->preview($admin, $this->character->id, $this->inventoryInput('initialize', ['amount' => 0])))->toThrow(AuthorizationException::class);
});
test('leader can use http preview and store and owner sees history', function (): void {
    $input = $this->inventoryInput('initialize', ['amount' => 100]);
    $this->actingAs($this->leader)->get(route('rpg.inventory.index'))->assertOk()->assertSee($this->character->character_name);
    $this->get(route('rpg.inventory.show', $this->character))->assertOk()->assertSee('inventory-manager');
    $preview = $this->postJson(route('rpg.inventory.preview', $this->character), $input)->assertOk()->json();
    $this->postJson(route('rpg.inventory.store', $this->character), $input + ['preview_hash' => $preview['preview_hash']])->assertOk()->assertJsonStructure(['redirect']);
    $this->actingAs($this->player)->get(route('rpg.inventory.show', $this->character))->assertOk()->assertSee('100 Bucks')->assertSee('Bestand übernehmen');
    expect(app(RpgInventoryHistory::class)->forCharacter($this->character->fresh()))->toHaveCount(1);
});
test('non equip metadata is rejected before preview or booking', function (string $kind, array $metadata): void {
    $stock = [$this->position('item:seil', 2), $this->position('item:kettenhemd'), $this->position('item:holzschild'), $this->position('clothing:kleidung-wanderer'), $this->position('item:energiegewehr'), $this->position('ammo:energiegewehr', 80)];
    if ($kind !== 'initialize') {
        $this->inventoryInitialize(100, $stock);
    }
    $changes = match ($kind) {
        'initialize' => ['amount' => 100, 'set' => $stock],
        'grant' => ['incoming' => [$this->position('item:seil')]],
        'loss' => ['outgoing' => [$this->position('item:seil')]],
        'correct' => ['amount' => 120, 'set' => [$this->position('item:seil', 3)]],
        'money' => ['amount' => 10],
        'purchase' => ['amount' => 30, 'incoming' => [$this->position('item:bogen')], 'external_party' => 'Händler'],
        'sale' => ['amount' => 5, 'outgoing' => [$this->position('item:seil')], 'external_party' => 'Händler', 'price_reason' => 'Ausgehandelter Preis'],
        'swap' => ['incoming' => [$this->position('item:bogen')], 'outgoing' => [$this->position('item:seil')], 'external_party' => 'Händler'],
        'regenerate' => ['minutes' => 1],
        'reversal' => ['reversal_of' => $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil')]])->id],
    };
    $input = $this->inventoryInput($kind, $changes);
    $preview = app(RpgInventoryService::class)->preview($this->leader, $this->character->id, $input);
    $before = $this->character->fresh();
    $transactions = RpgInventoryTransaction::count();
    $entries = RpgInventoryEntry::count();
    $input['metadata'] = $metadata;
    $input['preview_hash'] = $preview['preview_hash'];
    $message = $kind === 'reversal' ? 'Eine Rückbuchung darf keine zusätzlichen Bestands- oder Preisänderungen enthalten.' : 'Ausrüstungsangaben sind nur beim Ausrüstungswechsel möglich.';
    $this->actingAs($this->leader);
    foreach (['rpg.inventory.preview', 'rpg.inventory.store'] as $route) {
        $this->postJson(route($route, $this->character), $input)->assertUnprocessable()->assertJsonValidationErrors('inventory')->assertJsonPath('errors.inventory.0', $message);
    }
    expect($this->character->fresh()->payload)->toBe($before->payload)
        ->and($this->character->fresh()->revision)->toBe($before->revision);
    $this->assertDatabaseCount('rpg_inventory_transactions', $transactions);
    $this->assertDatabaseCount('rpg_inventory_entries', $entries);
})->with('RpgInventoryTest.nonEquipMetadata');
test('equip can set clear and restore metadata without changing stock or bucks', function (): void {
    $this->inventoryInitialize(100, [$this->position('item:kettenhemd'), $this->position('item:holzschild'), $this->position('clothing:kleidung-wanderer')]);
    $before = $this->state();
    $metadata = ['notes' => 'Ausrüstung angelegt', 'armor' => 'kettenhemd', 'shield' => 'holzschild', 'clothing' => 'kleidung-wanderer'];
    $this->inventoryBook('equip', ['metadata' => $metadata]);
    expect(array_intersect_key($this->state(), $metadata))->toEqual($metadata);
    $clear = $this->inventoryBook('equip', ['metadata' => array_fill_keys(array_keys($metadata), null)]);
    foreach (array_keys($metadata) as $field) {
        expect($this->state()[$field])->toBe('');
    }
    expect($this->state()['stocks'])->toBe($before['stocks'])
        ->and($this->state()['bucks'])->toBe($before['bucks']);
    $this->inventoryBook('reversal', ['reversal_of' => $clear->id]);
    expect(array_intersect_key($this->state(), $metadata))->toEqual($metadata)
        ->and($this->state()['stocks'])->toBe($before['stocks'])
        ->and($this->state()['bucks'])->toBe($before['bucks']);
});
test('invalid bookings leave state revision and journal unchanged', function (array $changes): void {
    $this->inventoryInitialize();
    $before = $this->character->fresh();
    try {
        $this->inventoryBook('grant', $changes);
        $this->fail('Ungültige Buchung wurde akzeptiert.');
    } catch (ValidationException) {
        expect($this->character->fresh()->payload)->toBe($before->payload)
            ->and($this->character->fresh()->revision)->toBe($before->revision);
        $this->assertDatabaseCount('rpg_inventory_transactions', 1);
    }
})->with('RpgInventoryTest.invalidBookings');
test('journal models are immutable', function (): void {
    $this->inventoryInitialize();
    expect(fn () => RpgInventoryEntry::firstOrFail()->update(['changes' => []]))->toThrow(\LogicException::class);
});
