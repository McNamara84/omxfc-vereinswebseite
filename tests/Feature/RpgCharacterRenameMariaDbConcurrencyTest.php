<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\RpgCharacter;
use App\Models\RpgCharacterRename;
use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgInventoryService;
use Illuminate\Support\Str;

pest()->extend(RpgCharacterRenameMariaDbConcurrencyTestFixtures::class);

test('parallel retries book one rename and one purchase', function (): void {
    $action = $this->renameAction();
    $results = $this->parallel($action, $action);
    expect(array_column($results, 'ok'))->toBe([true, true])->and($results[0]['id'])->toBe($results[1]['id'])
        ->and($this->character->fresh()->revision)->toBe(1);
    $this->assertDatabaseCount('reward_purchases', 1);
    $this->assertDatabaseCount('rpg_character_renames', 1);
});

test('competing names for one revision cannot both charge', function (): void {
    $this->player->incrementTeamPoints(25);
    $results = $this->parallel($this->renameAction(), $this->renameAction('Mira'));
    expect(array_sum(array_column($results, 'ok')))->toBe(1)->and($this->character->fresh()->revision)->toBe(1);
    $this->assertDatabaseCount('reward_purchases', 1);
});

test('different characters share one spendable wallet', function (): void {
    $other = RpgCharacter::factory()->create(['user_id' => $this->player->id]);
    $results = $this->parallel($this->renameAction(), $this->renameAction('Mira', $other));
    expect(array_sum(array_column($results, 'ok')))->toBe(1)->and((int) RewardPurchase::sum('cost_baxx'))->toBe(25);
    $this->assertDatabaseCount('rpg_character_renames', 1);
});

test('rename competes with existing wallet purchases without overspending', function (string $kind): void {
    $reward = Reward::factory()->create(['is_active' => true, 'cost_baxx' => 25]);
    $results = $this->parallel($this->renameAction(), ['action' => $kind, 'actor' => $this->player->id, 'reward' => $reward->id]);
    expect(array_sum(array_column($results, 'ok')))->toBe(1)
        ->and((int) RewardPurchase::where('user_id', $this->player->id)->sum('cost_baxx'))->toBeLessThanOrEqual(25);
    $this->assertDatabaseCount('reward_purchases', 1);
})->with(['reward_purchase', 'slot_purchase']);

test('rename and new advancement submission cannot leave a stale pending request', function (): void {
    $this->credit();
    $results = $this->parallel($this->renameAction(), ['action' => 'submit', 'actor' => $this->player->id, 'character' => $this->character->id, 'input' => $this->advancementInput()]);
    expect(array_sum(array_column($results, 'ok')))->toBe(1);
    $request = $this->character->advancements()->first();
    if ($request) {
        expect($request->revision)->toBe($this->character->fresh()->revision);
        $this->assertDatabaseCount('reward_purchases', 0);
    } else {
        $this->assertDatabaseCount('reward_purchases', 1);
    }
});

test('pending approval cannot be invalidated by a racing rename', function (): void {
    $this->credit();
    $request = app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $this->advancementInput());
    $results = $this->parallel($this->renameAction(), ['action' => 'approved', 'actor' => $this->leader->id, 'request' => $request->id]);
    expect(array_column($results, 'ok'))->toBe([false, true])->and($request->fresh()->status)->toBe('approved');
    $this->assertDatabaseCount('reward_purchases', 0);
});

test('inventory and rename preserve the winner without partially applying the loser', function (): void {
    $this->inventoryInitialize();
    $input = $this->inventoryInput('grant', ['amount' => 10]);
    $input['preview_hash'] = app(RpgInventoryService::class)->preview($this->leader, $this->character->id, $input)['preview_hash'];
    $results = $this->parallel($this->renameAction(), ['action' => 'inventory', 'actor' => $this->leader->id, 'character' => $this->character->id, 'input' => $input]);
    expect(array_sum(array_column($results, 'ok')))->toBe(1);
    $character = $this->character->fresh();
    expect($character->character_name)->toBe($results[0]['ok'] ? 'Birka' : 'Arkon')
        ->and($character->payload['equipment']['bucks_balance'])->toBe($results[0]['ok'] ? 100 : 110)
        ->and(RewardPurchase::count())->toBe($results[0]['ok'] ? 1 : 0);
});

test('deletion racing rename never leaves a partial charge or dangling character reference', function (): void {
    $results = $this->parallel($this->renameAction(), ['action' => 'delete', 'actor' => $this->player->id, 'character' => $this->character->id]);
    expect($results[1]['ok'])->toBeTrue()->and(RewardPurchase::count())->toBe($results[0]['ok'] ? 1 : 0)
        ->and(RpgCharacterRename::count())->toBe($results[0]['ok'] ? 1 : 0);
    $this->assertModelMissing($this->character);
    expect(RpgCharacterRename::whereNotNull('rpg_character_id')->count())->toBe(0);
});

test('invitation acceptance and rename never mix character snapshots', function (): void {
    $combat = $this->combat(false);
    $results = $this->parallel($this->renameAction(), ['action' => 'combat_command', 'actor' => $this->opponent->id, 'combat' => $combat->id, 'command' => 'accept', 'key' => (string) Str::uuid()]);
    expect($results[0]['ok'])->toBeTrue()->and($combat->fresh()->status)->toBe($results[1]['ok'] ? 'preparing' : 'challenged')
        ->and($combat->participants()->where('side', 1)->firstOrFail()->snapshot['name'])->toBe('Arkon');
    $this->assertDatabaseCount('reward_purchases', 1);
});
