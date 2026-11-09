<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RpgCombat\CombatEngine;
use App\Services\RpgCombat\CombatStats;
use App\Services\RpgCombat\NpcCombatSnapshotFactory;
use App\Support\RpgNpcCatalog;
use InvalidArgumentException;
use Tests\Support\CombatTestDice;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgNpcEngineTestFixtures::class);
dataset('RpgNpcEngineTest.clawTemplates', static fn (): array => ['avtar' => ['avtar'], 'eluu' => ['eluu'], 'izekeepir' => ['izekeepir']]);
dataset('RpgNpcEngineTest.invalidRulings', static fn (): array => ['effect injection' => [['effect' => 'control']], 'too long' => [['duration' => 101]], 'damage injection' => [['damage' => 999]], 'dice injection' => [['dice' => [6, 6]]], 'negative magnitude' => [['modifier' => -1]], 'unknown attribute' => [['resistance_attribute' => 'foo']], 'no reason' => [['reason' => '']]]);
dataset('RpgNpcEngineTest.movesIntoAbilityRange', static fn (): array => ['sequential NPC approach' => [false, 400, 0, false], 'simultaneous NPC approach' => [true, 400, 0, false], 'simultaneous opponent approach' => [true, 0, -400, false], 'simultaneous joint approach' => [true, 200, -200, false], 'opponent declares approach first' => [true, 0, -400, true]]);
dataset('RpgNpcEngineTest.movesOutsideAbilityRange', static fn (): array => ['stationary out of range' => [false, 0, 0], 'insufficient NPC approach' => [false, 300, 0], 'simultaneous opponent retreat' => [true, 400, 100]]);
beforeEach(function (): void {
    $this->dice = new CombatTestDice;
    $this->engine = new CombatEngine($this->dice);
});
test('two claws have independent defenses and no two weapon penalty', function (string $key): void {
    $state = $this->state($key);
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'body:0'], [1, 3]);
    expect($state['pending']['modifiers']['Zwei Waffen'])->toBe(0)
        ->and($state['queue'])->toHaveCount(1);
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [6, 6, 1, 3]);
    expect($state['actors'][2]['dodges'])->toBe(1)
        ->and(CombatStats::defense($state['actors'][2], 'dodge', [], 1, false)['Wiederholtes Ausweichen'])->toBe(-1);
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [6, 6]);
    expect($state['actors'][2]['dodges'])->toBe(2)
        ->and($state['queue'])->toHaveCount(0);
})->with('RpgNpcEngineTest.clawTemplates');
test('beak is only one attack and creatures cannot parry', function (): void {
    $state = $this->state('avtar');
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'body:1'], [3, 4]);
    expect($state['queue'])->toHaveCount(0)
        ->and(fn () => CombatStats::defense($state['actors'][1], 'parry', [], 1, false))->toThrow(InvalidArgumentException::class);
});
test('wisaau requires real runup and bite remains available', function (): void {
    $state = $this->state('wisaau', 700);
    try {
        $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'body:0']);
        $this->fail('No run-up');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('Anlauf');
    }
    $state = $this->step($state, 'action', 1, ['kind' => 'run', 'move' => 600]);
    $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'body:0'], [3, 4]);
    expect($state['pending']['weapon']['name'])->toBe('Hauer');
    $fresh = $this->state('wisaau');
    $fresh = $this->step($fresh, 'action', 1, ['kind' => 'attack', 'weapon' => 'body:1'], [3, 4]);
    expect($fresh['pending']['weapon']['name'])->toBe('Biss');
});
test('paralysis is adjudicated resisted and expires in game time', function (): void {
    $state = $this->step($this->state('avtar'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Schrei']);
    expect($state['phase'])->toBe('awaiting_ruling')
        ->and($state['seconds'])->toBe(0);
    $state = $this->step($state, 'ruling', 0, $this->ruling(['resistance_attribute' => 'wi']));
    $state = $this->step($state, 'npc_resistance', 2, [], [1, 3]);
    expect($state['actors'][2]['effects'][0]['type'])->toBe('paralyzed')
        ->and($state['seconds'])->toBe(3);
    $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
    expect($state['seconds'])->toBe(6)
        ->and($state['actors'][2]['effects'])->toBeEmpty();
});
test('successful resistance prevents paralysis', function (): void {
    $state = $this->step($this->state('avtar'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Schrei']);
    $state = $this->step($state, 'ruling', 0, $this->ruling(['resistance_attribute' => 'wi']));
    $state = $this->step($state, 'npc_resistance', 2, [], [6, 6]);
    expect($state['actors'][2]['effects'])->toBeEmpty();
});
test('unbounded or injected rulings are rejected', function (array $extra): void {
    $state = $this->step($this->state('avtar'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Schrei']);
    expect(fn () => $this->step($state, 'ruling', 0, $this->ruling($extra)))->toThrow(InvalidArgumentException::class);
})->with('RpgNpcEngineTest.invalidRulings');
test('siragippe acid uses server damage and gejagudoo swallow triggers at four points', function (): void {
    $state = $this->step($this->state('siragippe'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Säuresekret']);
    $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'acid', 'modifier' => 1]));
    expect($state['pending']['kind'])->toBe('npc_acid');
    $state = $this->step($state, 'damage', 1, [], [1]);
    expect($state['actors'][2]['wounds'])->toBe([1]);
    $state = $this->step($this->state('gejagudoo'), 'action', 1, ['kind' => 'attack', 'weapon' => 'body:0'], [4, 4]);
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [1, 3]);
    expect($state['pending']['swallow'])->toBeTrue();
    $state = $this->step($state, 'damage', 1, [], [1]);
    $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'swallowed', 'duration' => 10]));
    expect($state['actors'][2]['swallowed_by'])->toBe(1)
        ->and($state['pending']['damage'])->toBe(1);
});
test('snaekke acid occurs once per round and escape clears the state', function (): void {
    $state = $this->state('snaekke');
    $state['actors'][2]['swallowed_by'] = 1;
    $state['actors'][2]['effects'][] = ['type' => 'swallowed', 'value' => 0, 'source' => 1, 'expires' => 30];
    $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
    $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
    expect($state['pending']['kind'])->toBe('npc_acid')
        ->and($state['pending']['damage'])->toBe(2)
        ->and($state['queue'])->toBeEmpty();
    $state = $this->step($state, 'damage', 1, [], [1]);
    $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
    $state = $this->step($state, 'action', 2, ['kind' => 'npc_ability', 'ability' => 'Befreiung']);
    $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'escape']));
    expect($state['actors'][2])->not->toHaveKey('swallowed_by')
        ->and($state['pending'])->toBeNull();
});
test('controlled npc accepts player order then leader action', function (): void {
    $state = $this->state('androne');
    $state['actors'][1]['effects'][] = ['type' => 'control', 'controller' => 2, 'expires' => 100, 'next_resistance' => 3600];
    $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
    $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
    $state = $this->step($state, 'npc_order', 1, ['description' => 'Bleibe stehen.']);
    $task = collect($state['tasks'])->firstWhere('type', 'action');
    expect($task['context']['order'])->toBe('Bleibe stehen.')
        ->and($task['side'])->toBe(1)
        ->and($task['controller'])->toBe(2);
});
test('shield loss and rank increments change only preincluded components', function (): void {
    $npc = app(NpcCombatSnapshotFactory::class)->build(RpgNpcCatalog::all()['barbarenkrieger'], []);
    $actor = $this->engine->start([$npc, $npc], 100, 100)['state']['actors'][1];
    $actor['shield'] = true;
    expect(array_sum(CombatStats::defense($actor, 'parry', [], 1, false)))->toBe(4);
    $actor['shield_broken'] = true;
    expect(array_sum(CombatStats::defense($actor, 'parry', [], 1, false)))->toBe(3);
    $npc = app(NpcCombatSnapshotFactory::class)->build(RpgNpcCatalog::all()['daamure'], ['rank' => 'Sol', 'profession' => 'Techniker', 'skill_increases' => ['Nahkampf' => 18]]);
    $actor = $this->engine->start([$npc, $npc], 100, 100)['state']['actors'][1];
    $weapon = $actor['weapons']['body:0'];
    expect(array_sum(CombatStats::attack($actor, $weapon, $weapon['modes'][0], [], 100, [])))->toBe(25);
});
test('aruula uses lauschen as book defined telepathy with fixed pep', function (): void {
    $state = $this->state('aruula');
    expect($state['actors'][1]['pep'])->toBe(2)
        ->and(CombatStats::power($state['actors'][1], 'Telepathie'))->toBe(2);
    $state = $this->step($state, 'action', 1, ['kind' => 'psychic', 'power' => 'Telepathie', 'effect' => 'read', 'duration' => 0, 'range' => 1, 'strength' => 0], [6, 6]);
    expect($state['actors'][1]['pep'])->toBe(1);
    $state = $this->step($state, 'psychic_resistance', 2, [], [1, 3]);
    expect($state['actors'][1]['pep'])->toBe(1);
});
test('snaekke protection and trapped weapon require ruling and allow escape', function (): void {
    $state = $this->state('snaekke');
    $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
    $state['actors'][2]['held'] = ['schwert:1'];
    $state = $this->step($state, 'action', 2, ['kind' => 'attack', 'weapon' => 'schwert:1'], [4, 4]);
    $state = $this->step($state, 'defense', 1, ['defense' => 'dodge'], [1, 3]);
    $state = $this->step($state, 'damage', 2);
    expect($state['phase'])->toBe('awaiting_ruling');
    $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'protection', 'modifier' => 2, 'entangle' => true]), [1]);
    expect($state['actors'][2]['weapons']['schwert:1']['stuck'])->toBeTrue()
        ->and($state['actors'][2]['held_by'])->toBe(1);
    $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
    $state = $this->step($state, 'action', 2, ['kind' => 'npc_ability', 'ability' => 'Befreiung']);
    $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'escape']));
    expect($state['actors'][2])->not->toHaveKey('held_by')
        ->and($state['actors'][2]['weapons']['schwert:1'])->not->toHaveKey('stuck');
});
test('snaekke fire vulnerability automatically ignores ro', function (): void {
    $state = $this->state('snaekke');
    $state['actors'][1]['effects'][] = ['type' => 'protection', 'value' => 6, 'scope' => 'ordinary_weapons', 'expires' => 30];
    $state['queue'][] = ['kind' => 'burn', 'side' => 2, 'target' => 1, 'damage' => 0];
    $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
    $state = $this->step($state, 'damage', 2, [], [2]);
    expect($state['actors'][1]['wounds'])->toBe([1]);
});
test('swallowed character moves with creature and can escape at new position', function (): void {
    $state = $this->state('snaekke');
    $state['actors'][2]['swallowed_by'] = 1;
    $state['actors'][2]['effects'][] = ['type' => 'swallowed', 'value' => 0, 'source' => 1, 'expires' => 30];
    $state = $this->step($state, 'action', 1, ['kind' => 'run', 'move' => 500]);
    expect($state['actors'][1]['position'])->toBe(500)
        ->and($state['actors'][2]['position'])->toBe(500);
    $state = $this->step($state, 'action', 2, ['kind' => 'npc_ability', 'ability' => 'Befreiung']);
    $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'escape', 'range' => 0]));
    expect($state['actors'][2])->not->toHaveKey('swallowed_by')
        ->and($state['actors'][2]['position'])->toBe(500);
});
test('jump distance outside published limits is rejected', function (): void {
    $state = $this->step($this->state('frekkeuscher'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Sprung (20–30 m)']);
    expect(fn () => $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'movement', 'displacement' => 100])))->toThrow(InvalidArgumentException::class);
});
test('frekkeuscher jump moves the creature by the adjudicated book distance', function (): void {
    $state = $this->step($this->state('frekkeuscher'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Sprung (20–30 m)']);
    $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'movement', 'displacement' => 2500, 'range' => 0]));
    expect($state['actors'][1]['position'])->toBe(2500)
        ->and($state['actors'][2]['position'])->toBe(100)
        ->and($this->dice->calls)->toBe(2);
});
test('npc ability uses final positions after declared movements', function (bool $tied, int $npcMove, int $opponentMove, bool $opponentFirst): void {
    $state = $this->state('avtar', 500, tied: $tied);
    expect($state['group'])->toHaveCount($tied ? 2 : 1);
    $opponentAction = ['kind' => $opponentMove === 0 ? 'wait' : 'move', 'move' => $opponentMove];
    if ($opponentFirst) {
        $state = $this->step($state, 'action', 2, $opponentAction);
    }
    $state = $this->step($state, 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Schrei', 'move' => $npcMove]);
    expect($state['actors'][1]['position'])->toBe(0)
        ->and($state['actors'][2]['position'])->toBe(500);
    $state = $this->step($state, 'ruling', 0, $this->ruling(['range' => 100, 'resistance_attribute' => 'wi']));
    if ($tied && ! $opponentFirst) {
        expect($state['actors'][1]['position'])->toBe(0)
            ->and($state['actors'][2]['position'])->toBe(500)
            ->and($state['pending_npc_effect'] ?? null)->toBeNull();
        $state = $this->step($state, 'action', 2, $opponentAction);
    }
    expect($state['actors'][1]['position'])->toBe($npcMove)
        ->and($state['actors'][2]['position'])->toBe(500 + $opponentMove)
        ->and(abs($state['actors'][1]['position'] - $state['actors'][2]['position']))->toBe(100)
        ->and($this->dice->calls)->toBe(2);
    $state = $this->step($state, 'npc_resistance', 2, [], [1, 3]);
    expect($state['actors'][2]['effects'][0]['type'])->toBe('paralyzed')
        ->and($this->dice->calls)->toBe(4);
})->with('RpgNpcEngineTest.movesIntoAbilityRange');
test('npc ability outside final range has no effect or resistance roll', function (bool $tied, int $npcMove, int $opponentMove): void {
    $state = $this->state('avtar', 500, tied: $tied);
    $state = $this->step($state, 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Schrei', 'move' => $npcMove]);
    $state = $this->step($state, 'ruling', 0, $this->ruling(['range' => 100, 'resistance_attribute' => 'wi']));
    if ($tied) {
        $state = $this->step($state, 'action', 2, ['kind' => 'move', 'move' => $opponentMove]);
    }
    expect($state['actors'][1]['position'])->toBe($npcMove)
        ->and($state['actors'][2]['position'])->toBe(500 + $opponentMove)
        ->and(abs($state['actors'][1]['position'] - $state['actors'][2]['position']))->toBeGreaterThan(100)
        ->and($state['actors'][2]['effects'])->toBeEmpty()
        ->and($state['pending_npc_effect'] ?? null)->toBeNull()
        ->and(array_column($state['tasks'], 'type'))->not->toContain('npc_resistance')
        ->and($this->dice->calls)->toBe(2);
})->with('RpgNpcEngineTest.movesOutsideAbilityRange');
test('simultaneous movement can escape the adjudicated ability range without resistance', function (): void {
    $state = $this->state('avtar', 100, tied: true);
    $state = $this->step($state, 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Schrei']);
    $state = $this->step($state, 'ruling', 0, $this->ruling(['range' => 100, 'resistance_attribute' => 'wi']));
    $state = $this->step($state, 'action', 2, ['kind' => 'move', 'move' => 400]);
    expect($state['actors'][2]['position'])->toBe(500)
        ->and($state['actors'][2]['effects'])->toBeEmpty()
        ->and($state['pending_npc_effect'] ?? null)->toBeNull()
        ->and($this->dice->calls)->toBe(2);
});
test('active snaekke protection applies to weapons and expires in game time', function (): void {
    $state = $this->step($this->state('snaekke'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Besonderer Schutz gegen gewöhnliche Waffen']);
    $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'protection', 'modifier' => 4, 'duration' => 1]));
    expect(CombatStats::protection($state['actors'][1]))->toBe(4)
        ->and(CombatStats::protection($state['actors'][1], false))->toBe(0);
    $state['actors'][2]['held'] = ['schwert:1'];
    $state = $this->step($state, 'action', 2, ['kind' => 'attack', 'weapon' => 'schwert:1'], [4, 4]);
    $state = $this->step($state, 'defense', 1, ['defense' => 'dodge'], [1, 3]);
    $state = $this->step($state, 'damage', 2, [], [1]);
    expect($state['actors'][1]['wounds'])->toBeEmpty()
        ->and($state['seconds'])->toBe(3)
        ->and(CombatStats::protection($state['actors'][1]))->toBe(0);
});
