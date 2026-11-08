<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgNpc;
use App\Services\RpgCombat\CombatEngine;
use App\Services\RpgCombat\CombatStats;
use App\Services\RpgCombat\NpcCombatSnapshotFactory;
use App\Services\RpgNpcService;
use App\Support\RpgNpcCatalog;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgNpcTestFixtures::class);
dataset('RpgNpcTest.invalidInputs', static fn (): array => ['unknown' => [['template_key' => 'dragon']], 'profile injection' => [['profile' => []]], 'special name' => [['template_key' => 'maddrax', 'custom_name' => 'Anderer']], 'irrelevant rank' => [['rank' => 'Sol']], 'bad bandit weapon' => [['template_key' => 'bandit', 'weapon' => 'driller']], 'rank budget' => [['template_key' => 'daamure', 'rank' => 'Lin']], 'rank excess' => [['template_key' => 'daamure', 'rank' => 'Lin', 'skill_increases' => ['Nahkampf' => 4], 'reason' => 'Krieger']], 'negative fp' => [['template_key' => 'daamure', 'skill_increases' => ['Nahkampf' => -1]]], 'unknown skill' => [['template_key' => 'daamure', 'rank' => 'Lin', 'skill_increases' => ['Magie' => 3], 'reason' => 'Magier']], 'intuition conflict' => [['template_key' => 'daamure', 'rank' => 'Lin', 'skill_increases' => ['Intuition' => 3], 'reason' => 'Intuitiv']], 'science education' => [['template_key' => 'daamure', 'profession' => 'Wissenschaftler', 'rank' => 'Lin', 'skill_increases' => ['Wissenschaftler' => 3], 'reason' => 'Forscher']]]);
dataset('RpgNpcTest.banditWeapons', static fn (): array => ['sword' => ['schwert', 2, 1], 'dagger' => ['messer-dolch', 2, 0], 'sling' => ['zwille', 2, 0], 'bow' => ['bogen', 1, 1]]);
beforeEach(function (): void {
    $this->combatFixtures();
});
test('every published template builds and preserves all k values', function (): void {
    expect(RpgNpcCatalog::all())->toHaveCount(23);
    foreach (RpgNpcCatalog::all() as $key => $row) {
        $npc = app(RpgNpcService::class)->create($this->leader, $this->npcInput(['template_key' => $key]));
        $profile = app(NpcCombatSnapshotFactory::class)->make($npc);
        $actor = app(CombatEngine::class)->start([$profile, $profile], 100, 100)['state']['actors'][1];
        $actor['shield'] = $profile['shield'] !== null;
        expect(array_sum(CombatStats::defense($actor, 'dodge', [], 1, false)))->toBe($row['dodge']);
        if ($row['parry'] !== null) {
            expect(array_sum(CombatStats::defense($actor, 'parry', [], 1, false)))->toBe($row['parry']);
        }
        $defender = $actor;
        $defender['profile']['attributes'] = array_fill_keys(array_keys($row['attributes']), 0);
        $defender['profile']['advantages'] = $defender['profile']['disadvantages'] = [];
        $defender['profile']['armor'] = null;
        foreach ($profile['weapons'] as $id => $weapon) {
            $actor['held'] = $weapon['natural'] ? [] : [$id];
            foreach ($weapon['modes'] as $mode) {
                if (! isset($mode['npc_attack'])) {
                    continue;
                }
                foreach ($mode['attributes'] as $attribute) {
                    expect(array_sum(CombatStats::attack($actor, $weapon, $mode, [], 100, ['attribute' => $attribute])))->toBe($mode['npc_attack']);
                }
                expect(array_sum(CombatStats::damage($actor, $defender, ['mode' => $mode, 'weapon' => $weapon, 'input' => [], 'distance' => 100], [])))->toBe($mode['npc_damage']);
            }
        }
        expect($npc->revision)->toBe(0);
    }
});
test('creation is idempotent shared and does not use character slots or money', function (): void {
    $input = $this->npcInput(['custom_name' => '  Wächter  ']);
    $service = app(RpgNpcService::class);
    $npc = $service->create($this->leader, $input);
    expect($service->create($this->leader, $input)->id)->toBe($npc->id)
        ->and($npc->displayName())->toBe('Wächter');
    $service->create($this->leader, $this->npcInput());
    $this->assertDatabaseCount('rpg_npcs', 2);
    $this->assertDatabaseCount('rpg_characters', 2);
    $this->actingAs($this->leader)->get(route('rpg.characters.index'))->assertOk()->assertSee('Wächter');
    $this->actingAs($this->player)->get(route('rpg.characters.index'))->assertOk()->assertDontSee('Wächter');
});
test('http preview creation and rename and delete', function (): void {
    $input = $this->npcInput(['custom_name' => '<script>Wächter</script>']);
    $this->actingAs($this->leader)->get(route('rpg.npcs.create'))->assertOk()->assertSee('Jacob Smythe');
    $this->post(route('rpg.npcs.preview'), $input)->assertOk()->assertSee('NSC verbindlich erstellen');
    $this->assertDatabaseCount('rpg_npcs', 0);
    $this->post(route('rpg.npcs.store'), $input)->assertRedirect();
    $npc = RpgNpc::firstOrFail();
    $this->get(route('rpg.npcs.show', $npc))->assertOk()->assertDontSee('<script>Wächter</script>', false);
    $this->patch(route('rpg.npcs.rename', $npc), ['revision' => 0, 'custom_name' => 'Torwache'])->assertRedirect();
    expect($npc->fresh()->displayName())->toBe('Torwache');
    $this->patchJson(route('rpg.npcs.rename', $npc), ['revision' => 0, 'custom_name' => 'Alt'])->assertUnprocessable();
    $this->delete(route('rpg.npcs.destroy', $npc))->assertRedirect();
    $this->assertDatabaseCount('rpg_npcs', 0);
});
test('invalid inputs never create npcs', function (array $extra): void {
    $this->actingAs($this->leader)->postJson(route('rpg.npcs.store'), $this->npcInput($extra))->assertUnprocessable();
    $this->assertDatabaseCount('rpg_npcs', 0);
})->with('RpgNpcTest.invalidInputs');
test('all ranks apply cumulative attributes and exact fp', function (): void {
    foreach (RpgNpcCatalog::ranks() as $rank => $values) {
        $npc = app(RpgNpcService::class)->create($this->leader, $this->npcInput(['template_key' => 'daamure', 'rank' => $rank, 'profession' => 'Techniker', 'skill_increases' => ['Nahkampf' => $values['fp']], 'reason' => 'Kampftraining']));
        foreach (array_diff_key($values, ['fp' => 1]) as $attribute => $value) {
            expect($npc->profile['attributes'][$attribute])->toBe($value);
        }
        expect($npc->profile['skills']['Nahkampf'])->toBe(3 + $values['fp'])
            ->and($npc->profile['advantage_counts']['Regeneration'])->toBe(2);
    }
});
test('specials are unique and only current active leader can manage', function (): void {
    $npc = app(RpgNpcService::class)->create($this->leader, $this->npcInput(['template_key' => 'maddrax']));
    $this->actingAs($this->leader)->postJson(route('rpg.npcs.store'), $this->npcInput(['template_key' => 'maddrax']))->assertUnprocessable();
    $this->patchJson(route('rpg.npcs.rename', $npc), ['revision' => 0, 'custom_name' => 'Fake'])->assertForbidden();
    $this->actingAs($this->player)->get(route('rpg.npcs.create'))->assertForbidden();
    $this->get(route('rpg.npcs.show', $npc))->assertForbidden();
    $this->postJson(route('rpg.npcs.store'), $this->npcInput())->assertForbidden();
    $this->rpgTeam->update(['user_id' => $this->player->id]);
    $this->get(route('rpg.npcs.show', $npc))->assertOk()->assertSee('Matthew Drax');
    $this->actingAs($this->leader)->deleteJson(route('rpg.npcs.destroy', $npc))->assertForbidden();
});
test('immutable profile cannot be modified through model', function (): void {
    $npc = app(RpgNpcService::class)->create($this->leader, $this->npcInput());
    expect(fn () => $npc->update(['profile' => ['name' => 'Gefälscht']]))->toThrow(\LogicException::class);
});
test('reused submission key with changed data is a conflict', function (): void {
    $input = $this->npcInput();
    app(RpgNpcService::class)->create($this->leader, $input);
    $this->actingAs($this->leader)->postJson(route('rpg.npcs.store'), array_replace($input, ['custom_name' => 'Anders']))->assertConflict();
    $this->assertDatabaseCount('rpg_npcs', 1);
});
test('bandit has only selected equipment and its published k values', function (string $id, int $attack, int $damage): void {
    $npc = app(RpgNpcService::class)->create($this->leader, $this->npcInput(['template_key' => 'bandit', 'weapon' => $id]));
    expect($npc->profile['weapons'])->toHaveCount(2);
    // Selected weapon and the shared unarmed attack.
    expect($npc->profile['weapons'][$id.':1']['modes'][0]['npc_attack'])->toBe($attack);
    expect($npc->profile['weapons'][$id.':1']['modes'][0]['npc_damage'])->toBe($damage);
})->with('RpgNpcTest.banditWeapons');
