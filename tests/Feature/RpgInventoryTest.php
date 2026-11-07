<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\RpgInventoryEntry;
use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCharacterSheetPresenter;
use App\Services\RpgInventoryAdapter;
use App\Services\RpgInventoryHistory;
use App\Services\RpgInventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RpgInventoryFixtures;
use Tests\TestCase;

class RpgInventoryTest extends TestCase
{
    use RefreshDatabase, RpgInventoryFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progressionFixtures();
    }

    public function test_initialization_confirms_zero_without_changing_initial_payload_or_experience(): void
    {
        $original = $this->character->payload;
        $this->inventoryInitialize(0);
        $character = $this->character->fresh();
        $this->assertSame(0, $character->payload['equipment']['bucks_balance']);
        $this->assertNotEmpty($character->payload['equipment']['stock_confirmed_at']);
        $this->assertSame($original, $character->initial_payload);
        $this->assertSame(0, $character->experienceBalance());
        $this->assertSame(1, $character->revision);
        $this->assertDatabaseCount('rpg_inventory_transactions', 1);
        $this->assertDatabaseCount('rpg_inventory_entries', 1);
    }

    public function test_later_high_tech_and_more_than_six_items_need_no_advantage_or_experience(): void
    {
        $this->inventoryInitialize();
        $this->inventoryBook('grant', ['incoming' => [$this->position('item:revolver'), $this->position('item:seil', 7), $this->position('ammo:revolver', 12)]]);
        $state = $this->state();
        $this->assertSame(7, $state['stocks']['item:seil']);
        $this->assertSame(12, $state['stocks']['ammo:revolver']);
        $this->assertSame([], $this->character->fresh()->payload['advantages']);
        $this->assertSame(0, $this->character->experienceBalance());
    }

    public function test_legacy_takeover_and_later_bookings_preserve_the_selected_unarmed_attack(): void
    {
        $payload = $this->character->payload;
        $unarmed = ['id' => 'faustschlag-tritt', 'quantity' => 1, 'name' => 'Faustschlag / Tritt', 'counts_toward_limit' => false];
        $payload['equipment']['items'] = [$unarmed, ['id' => 'messer-dolch', 'quantity' => 1]];
        $this->character->update(['payload' => $payload]);
        $calculator = app(RpgCharacterCombatCalculator::class);
        $expected = collect($calculator->calculate($payload)['weapons'])->firstWhere('id', 'faustschlag-tritt');
        $initial = $this->character->initial_payload;

        $this->inventoryInitialize(0);
        $this->assertContains($unarmed, $this->character->fresh()->payload['equipment']['items']);
        $grant = $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil')]]);
        $this->inventoryBook('loss', ['outgoing' => [$this->position('item:messer-dolch')]]);
        $this->inventoryBook('reversal', ['reversal_of' => $grant->id]);

        $saved = $this->character->fresh();
        $this->assertSame([$unarmed], $saved->payload['equipment']['items']);
        $this->assertSame([], $this->state()['stocks']);
        $this->assertSame($initial, $saved->initial_payload);
        $combat = $calculator->calculate($saved->payload);
        $this->assertSame($expected, collect($combat['weapons'])->firstWhere('id', 'faustschlag-tritt'));
        $sheet = app(RpgCharacterSheetPresenter::class)->present($saved->payload, $combat);
        $html = view('rpg.char-sheet', ['sheet' => $sheet, 'combat' => $combat])->render();
        $this->assertStringContainsString('Faustschlag / Tritt', $html);
        $this->assertStringContainsString('1W6-1+VM', $html);
    }

    public function test_rebuilding_zero_stock_preserves_only_a_previously_selected_virtual_attack(): void
    {
        $adapter = app(RpgInventoryAdapter::class);
        $state = $adapter->state($this->character->payload);
        $state['bucks'] = 0;
        $state['stocks'] = ['item:bogen' => 0];
        $this->assertSame([], $adapter->equipment($state)['items']);
        $unarmed = ['id' => 'faustschlag-tritt', 'quantity' => 1];
        $equipment = $adapter->equipment($state, ['items' => [$unarmed]]);
        $this->assertSame([$unarmed], $equipment['items']);
        $this->assertSame([], $equipment['ammunition']);
        $this->assertSame([], $adapter->state(['equipment' => $equipment])['stocks']);
    }

    public function test_clothing_is_retained_when_changed_and_armor_is_deactivated_when_removed(): void
    {
        $this->inventoryInitialize();
        $this->inventoryBook('grant', ['incoming' => [$this->position('clothing:kleidung-einfach'), $this->position('clothing:kleidung-wanderer'), $this->position('item:kettenhemd')]]);
        $this->inventoryBook('equip', ['metadata' => ['clothing' => 'kleidung-einfach', 'armor' => 'kettenhemd', 'notes' => 'Persönliche Ausrüstung']]);
        $this->inventoryBook('equip', ['metadata' => ['clothing' => 'kleidung-wanderer']]);
        $preview = app(RpgInventoryService::class)->preview($this->leader, $this->character->id, $this->inventoryInput('loss', ['outgoing' => [$this->position('item:kettenhemd')]]));
        $this->assertSame('', $preview['entries'][$this->character->id]['after']['armor']);
        $this->inventoryBook('loss', ['outgoing' => [$this->position('item:kettenhemd')]]);
        $this->assertSame(1, $this->state()['stocks']['clothing:kleidung-einfach']);
        $this->assertSame('kleidung-wanderer', $this->state()['clothing']);
        $this->assertSame('Persönliche Ausrüstung', $this->character->fresh()->payload['character']['equipment']);
    }

    public function test_munition_is_not_refilled_by_other_equipment_or_high_tech_advantage(): void
    {
        $this->inventoryInitialize(100, [$this->position('item:bogen'), $this->position('ammo:pfeile', 30)]);
        $this->inventoryBook('loss', ['outgoing' => [$this->position('ammo:pfeile', 7)]]);
        $this->inventoryBook('grant', ['incoming' => [$this->position('item:bogen')]]);
        $this->credit();
        $service = app(RpgCharacterAdvancementService::class);
        $input = $this->advancementInput([$this->operation(type: 'advantage', name: 'High-Tech-Ausrüstung', extra: ['items' => ['revolver', 'revolver', 'revolver', 'revolver']])]);
        $input['revision'] = $this->character->fresh()->revision;
        $request = $service->submit($this->player, $this->character->id, $input);
        $service->decide($this->leader, $request->id, 'approved');
        $this->assertSame(23, $this->state()['stocks']['ammo:pfeile']);
        $this->assertArrayNotHasKey('ammo:revolver', $this->state()['stocks']);
        $this->assertSame(40, $this->character->experienceBalance());
        $this->assertDatabaseHas('rpg_inventory_transactions', ['advancement_id' => $request->id, 'kind' => 'advancement']);
    }

    public function test_new_character_ammunition_converts_starting_packages_once(): void
    {
        $payload = $this->progressionPayload();
        $payload['equipment']['items'] = [['id' => 'armbrust', 'quantity' => 1], ['id' => 'revolver', 'quantity' => 1], ['id' => 'energiegewehr', 'quantity' => 1]];
        $payload = app(RpgInventoryAdapter::class)->initializeNew($payload);
        $state = app(RpgInventoryAdapter::class)->state($payload);
        $this->assertSame(30, $state['stocks']['ammo:bolzen']);
        $this->assertSame(24, $state['stocks']['ammo:revolver']);
        $this->assertSame(100, $state['stocks']['ammo:energiegewehr']);
    }

    public function test_energy_regeneration_is_capped_and_needs_game_time(): void
    {
        $this->inventoryInitialize(0, [$this->position('item:energiegewehr'), $this->position('ammo:energiegewehr', 80)]);
        $this->inventoryBook('regenerate', ['minutes' => 7]);
        $this->assertSame(87, $this->state()['stocks']['ammo:energiegewehr']);
        $this->inventoryBook('regenerate', ['minutes' => 100]);
        $this->assertSame(100, $this->state()['stocks']['ammo:energiegewehr']);
    }

    public function test_inventory_change_supersedes_a_pending_advancement_without_spending_experience(): void
    {
        $this->inventoryInitialize();
        $this->credit();
        $input = $this->advancementInput();
        $input['revision'] = $this->character->fresh()->revision;
        $request = app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $input);
        $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil')]]);
        $this->assertSame('superseded', $request->fresh()->status);
        $this->assertSame(60, $this->character->experienceBalance());
        $this->actingAs($this->player)->get(route('rpg.advancements.show', $request))
            ->assertOk()->assertSee('Durch Charakteränderung überholt')->assertSee('Auf aktuellem Stand erneut prüfen');
        $this->actingAs($this->player)->get(route('rpg.characters.improve', $this->character))->assertOk()->assertViewHas('config', fn ($config) => $config['renewedOperations'] === []);
        $this->get(route('rpg.characters.improve', ['rpgCharacter' => $this->character, 'erneuern' => $request->id]))->assertOk()
            ->assertViewHas('config', fn ($config) => $config['renewedOperations'] === $request->operations);
        $this->expectException(ValidationException::class);
        app(RpgCharacterAdvancementService::class)->decide($this->leader, $request->id, 'approved');
    }

    public function test_identical_retries_book_only_once_but_changed_key_content_is_rejected(): void
    {
        $this->inventoryInitialize();
        $input = $this->inventoryInput('grant', ['incoming' => [$this->position('item:seil')]]);
        $service = app(RpgInventoryService::class);
        $input['preview_hash'] = $service->preview($this->leader, $this->character->id, $input)['preview_hash'];
        $first = $service->book($this->leader, $this->character->id, $input);
        $this->assertSame($first->id, $service->book($this->leader, $this->character->id, $input)->id);
        $this->assertSame(1, $this->state()['stocks']['item:seil']);
        $input['reason'] = 'Andere Angaben';
        $this->expectException(ValidationException::class);
        $service->book($this->leader, $this->character->id, $input);
    }

    public function test_reversal_preserves_later_stock_changes_and_is_recorded_once(): void
    {
        $this->inventoryInitialize();
        $transaction = $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil', 2)], 'amount' => 20]);
        $this->inventoryBook('grant', ['incoming' => [$this->position('item:seil')], 'amount' => 5]);
        $this->inventoryBook('reversal', ['reversal_of' => $transaction->id]);
        $this->assertSame(1, $this->state()['stocks']['item:seil']);
        $this->assertSame(105, $this->state()['bucks']);
        $this->assertNotNull($transaction->fresh()->reversal);
        $this->expectException(ValidationException::class);
        $this->inventoryBook('reversal', ['reversal_of' => $transaction->id]);
    }

    public function test_owner_and_admin_cannot_write_and_foreign_members_cannot_read(): void
    {
        $this->actingAs($this->player)->get(route('rpg.inventory.show', $this->character))->assertOk()->assertDontSee('inventory-manager');
        $this->actingAs($this->player)->postJson(route('rpg.inventory.preview', $this->character), $this->inventoryInput('initialize', ['amount' => 0]))->assertForbidden();
        $admin = $this->progressionMember(Role::Admin);
        $this->actingAs($admin)->get(route('rpg.inventory.show', $this->character))->assertForbidden();
        $this->actingAs($admin)->get(route('rpg.inventory.index'))->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(RpgInventoryService::class)->preview($admin, $this->character->id, $this->inventoryInput('initialize', ['amount' => 0]));
    }

    public function test_leader_can_use_http_preview_and_store_and_owner_sees_history(): void
    {
        $input = $this->inventoryInput('initialize', ['amount' => 100]);
        $this->actingAs($this->leader)->get(route('rpg.inventory.index'))->assertOk()->assertSee($this->character->character_name);
        $this->get(route('rpg.inventory.show', $this->character))->assertOk()->assertSee('inventory-manager');
        $preview = $this->postJson(route('rpg.inventory.preview', $this->character), $input)->assertOk()->json();
        $this->postJson(route('rpg.inventory.store', $this->character), $input + ['preview_hash' => $preview['preview_hash']])->assertOk()->assertJsonStructure(['redirect']);
        $this->actingAs($this->player)->get(route('rpg.inventory.show', $this->character))->assertOk()->assertSee('100 Bucks')->assertSee('Bestand übernehmen');
        $this->assertCount(1, app(RpgInventoryHistory::class)->forCharacter($this->character->fresh()));
    }

    #[DataProvider('invalidBookings')]
    public function test_invalid_bookings_leave_state_revision_and_journal_unchanged(array $changes): void
    {
        $this->inventoryInitialize();
        $before = $this->character->fresh();
        try {
            $this->inventoryBook('grant', $changes);
            $this->fail('Ungültige Buchung wurde akzeptiert.');
        } catch (ValidationException) {
            $this->assertSame($before->payload, $this->character->fresh()->payload);
            $this->assertSame($before->revision, $this->character->fresh()->revision);
            $this->assertDatabaseCount('rpg_inventory_transactions', 1);
        }
    }

    public static function invalidBookings(): array
    {
        return [
            'unknown' => [['incoming' => [['id' => 'item:unknown', 'quantity' => 1]]]],
            'virtual' => [['incoming' => [['id' => 'item:faustschlag-tritt', 'quantity' => 1]]]],
            'zero' => [['incoming' => [['id' => 'item:seil', 'quantity' => 0]]]],
            'too many' => [['incoming' => [['id' => 'item:seil', 'quantity' => 21]]]],
            'negative' => [['incoming' => [['id' => 'item:seil', 'quantity' => -1]]]],
            'fraction' => [['incoming' => [['id' => 'item:seil', 'quantity' => 1.5]]]],
            'no change' => [[]], 'whitespace reason' => [['reason' => '  ']],
            'metadata injection' => [['metadata' => ['advantages' => ['Zäh']]]],
            'payload injection' => [['payload' => ['attributes' => ['st' => 100]]]],
            'money overflow' => [['amount' => 1000000001]],
            'non-trade peer' => [['other_character_id' => 100]],
            'no energy weapon' => [['kind' => 'regenerate', 'minutes' => 1]],
            'unowned armor' => [['kind' => 'equip', 'metadata' => ['armor' => 'kettenhemd']]],
        ];
    }

    public function test_journal_models_are_immutable(): void
    {
        $this->inventoryInitialize();
        $this->expectException(\LogicException::class);
        RpgInventoryEntry::firstOrFail()->update(['changes' => []]);
    }

    private function state(): array
    {
        return app(RpgInventoryAdapter::class)->state($this->character->fresh()->payload);
    }
}
