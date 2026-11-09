<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCombat\AutomaticDecision;
use App\Services\RpgCombat\CombatEngine;
use App\Services\RpgCombat\CombatStats;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\CombatTestDice;

pest()->extend(TestCase::class)->use(RpgCombatEngineTestFixtures::class);
dataset('RpgCombatEngineTest.informationPowers', static fn (): array => [['Empathie', [], false], ['Empathie', ['strength' => 4], true], ['Telepathie', ['effect' => 'read', 'strength' => 2], true], ['Telepathie', ['effect' => 'send'], false], ['Gedankenschild', [], false]]);
dataset('RpgCombatEngineTest.invalidActions', static fn (): array => array_map(fn ($input) => [$input], [['kind' => 'unknown'], ['kind' => 'attack', 'weapon' => 'absent'], ['kind' => 'attack', 'weapon' => []], ['kind' => 'attack', 'weapon' => 'sword', 'mode' => 55], ['kind' => 'attack', 'weapon' => 'sword', 'aim' => 100], ['kind' => 'attack', 'weapon' => 'sword', 'attribute' => 'in'], ['kind' => 'attack', 'weapon' => 'sword', 'dice' => [6, 6]], ['kind' => 'move', 'move' => 401], ['kind' => 'run', 'move' => 1601], ['kind' => 'move', 'move' => '1'], ['kind' => 'stand'], ['kind' => 'heal'], ['kind' => 'reload', 'weapon' => 'sword'], ['kind' => 'unjam', 'weapon' => 'sword'], ['kind' => 'pickup', 'weapon' => 'sword'], ['kind' => 'psychic', 'power' => 'Pyrokinese'], ['kind' => 'creative', 'description' => 'x'], ['kind' => 'switch', 'weapons' => [['bad']], 'shield' => false], ['kind' => 'switch', 'weapons' => ['sword', 'sword'], 'shield' => false]]));
beforeEach(function (): void {
    $this->dice = new CombatTestDice;
    $this->engine = new CombatEngine($this->dice);
});
test('initiative is fixed and round limit is terminal', function (): void {
    $state = $this->state(limit: 2);
    expect($state['group'])->toBe([1]);
    foreach ([1, 2, 1, 2] as $side) {
        $state = $this->step($state, 'action', $side, ['kind' => 'wait']);
    }
    expect($state['end'])->toBe(['winner' => null, 'reason' => 'round_limit'])
        ->and($state['seconds'])->toBe(6)
        ->and($this->dice->calls)->toBe(2);
});
test('equal initiative seals actions and rerolls next round', function (): void {
    $state = $this->state(tied: true);
    $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
    expect($state['tasks'])->toHaveCount(1)
        ->and($state['seconds'])->toBe(0);
    $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
    expect($state['phase'])->toBe('initiative')
        ->and($state['round'])->toBe(2);
});
test('simultaneous knockouts are draws', function (): void {
    $state = $this->state(tied: true);
    foreach ([1, 2] as $side) {
        $state = $this->step($state, 'action', $side, ['kind' => 'attack', 'weapon' => 'sword'], [6, 6]);
    }
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [2, 2]);
    $state = $this->step($state, 'damage', 1, [], [6, 6, 6]);
    expect($state['end'])->toBeNull();
    $state = $this->step($state, 'defense', 1, ['defense' => 'dodge'], [2, 2]);
    $state = $this->step($state, 'damage', 2, [], [6]);
    expect($state['end'])->toBe(['winner' => null, 'reason' => 'mutual_incapacity']);
});
test('tied defense blocks and parry is consumed', function (): void {
    $state = $this->state();
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [4, 4]);
    $state = $this->step($state, 'defense', 2, ['defense' => 'parry'], [4, 4]);
    expect($state['actors'][2]['parried'])->toBeTrue()
        ->and($state['actors'][2]['wounds'])->toBe([])
        ->and(fn () => CombatStats::defense($state['actors'][2], 'parry', $state['rules'], 1, false))->toThrow(InvalidArgumentException::class);
});
test('full defense forbids own attack but allows movement', function (): void {
    $state = $this->state();
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [4, 4]);
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge', 'full_defense' => true], [6, 6]);
    expect(fn () => $this->step($state, 'action', 2, ['kind' => 'attack', 'weapon' => 'sword']))->toThrow(InvalidArgumentException::class);
});
test('defense fumble blocks current and next round', function (): void {
    $state = $this->state();
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [4, 4]);
    $state = $this->step($state, 'defense', 2, ['defense' => 'parry'], [1, 1]);
    $state = $this->step($state, 'damage', 1, [], [1]);
    expect($state['actors'][2]['blocked_until'])->toBe(2)
        ->and($state['round'])->toBe(2);
    $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
    expect($state['round'])->toBe(3);
    $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
    $state = $this->step($state, 'action', 2, ['kind' => 'stand']);
    expect($state['actors'][2]['prone'])->toBeFalse();
});
test('melee fumble drops weapon only after failed agility', function (): void {
    $state = $this->state();
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [1, 1]);
    $state = $this->step($state, 'defense', 2, ['defense' => 'parry'], [6, 6]);
    $state = $this->step($state, 'fumble', 1, [], [1, 2]);
    expect($state['actors'][1]['held'])->toBe([])
        ->and($state['actors'][1]['weapons']['sword']['position'])->toBe(0)
        ->and($state['actors'][1]['prone'])->toBeFalse();
});
test('ranged fumble refunds unfired ammo and jams', function (): void {
    $state = $this->state(ranged: true);
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword', 'fire' => 'A'], [1, 1]);
    expect($state['actors'][1]['weapons']['sword']['loaded'])->toBe(25);
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [6, 6, 1]);
    expect($state['actors'][1]['weapons']['sword']['jammed'])->toBeTrue()
        ->and($state['actors'][1]['weapons']['sword']['loaded'])->toBe(40);
});
test('automatic fire spends fifteen but creates one hit', function (): void {
    $state = $this->state(ranged: true);
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword', 'fire' => 'A'], [6, 6]);
    expect($state['tasks'])->toHaveCount(1)
        ->and($state['queue'])->toBe([]);
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [3, 3]);
    $state = $this->step($state, 'damage', 1, [], [1]);
    expect($state['actors'][2]['wounds'])->toHaveCount(1)
        ->and($state['actors'][1]['weapons']['sword']['loaded'])->toBe(25);
});
test('knockdown never inflicts normal wounds', function (): void {
    $state = $this->state();
    $state = $this->step($state, 'action', 1, ['kind' => 'knockdown', 'weapon' => 'sword'], [6, 6]);
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [3, 3]);
    $state = $this->step($state, 'strength', 2, [], [1, 1]);
    expect($state['actors'][2]['prone'])->toBeTrue()
        ->and($state['actors'][2]['wounds'])->toBe([])
        ->and($state['actors'][2]['held'])->toBe([])
        ->and($state['actors'][2]['blocked_until'])->toBe(0);
});
test('disarm tie requests ruling without rerolling', function (): void {
    $state = $this->state();
    unset($state['rules']['tie']);
    $state = $this->step($state, 'action', 1, ['kind' => 'disarm', 'weapon' => 'sword'], [4, 5]);
    $state = $this->step($state, 'resistance', 2, [], [4, 4]);
    expect($state['phase'])->toBe('awaiting_ruling');
    $calls = $this->dice->calls;
    $state = $this->step($state, 'ruling', 0, ['choices' => ['tie' => 'attacker'], 'reason' => 'Angreifer gewinnt diesen Gleichstand.']);
    expect($this->dice->calls)->toBe($calls)
        ->and($state['actors'][2]['held'])->toBe([]);
});
test('ruling invalidating choice reopens it', function (): void {
    $state = $this->state();
    unset($state['rules']['full_defense']);
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [4, 4]);
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge', 'full_defense' => true]);
    $state = $this->step($state, 'ruling', 0, ['choices' => ['full_defense' => 'own_turn'], 'reason' => 'Erst bei eigener Aktion erklären.']);
    expect($state['rules']['full_defense'])->toBe('own_turn')
        ->and(array_first($state['tasks'])['type'])->toBe('defense')
        ->and($this->dice->calls)->toBe(4);
});
test('simultaneous movement uses both positions and can lose range', function (): void {
    $state = $this->state(tied: true);
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword']);
    $state = $this->step($state, 'action', 2, ['kind' => 'move', 'move' => 400]);
    expect($state['actors'][2]['position'])->toBe(500)
        ->and($this->dice->calls)->toBe(2)
        ->and($state['actors'][2]['wounds'])->toBe([]);
});
test('reload and heal use actions and only snapshot resources', function (): void {
    $state = $this->state(ranged: true);
    $state['actors'][1]['weapons']['sword']['loaded'] = 0;
    $state['actors'][2]['wounds'] = [2, 3];
    $state = $this->step($state, 'action', 1, ['kind' => 'reload', 'weapon' => 'sword']);
    expect($state['actors'][1]['weapons']['sword']['loaded'])->toBe(40)
        ->and($state['actors'][1]['weapons']['sword']['reserve'])->toBe(80);
    $state = $this->step($state, 'action', 2, ['kind' => 'heal']);
    expect($state['actors'][2]['wounds'])->toBe([2, 2])
        ->and($state['actors'][2]['items']['heilgel'])->toBe(0);
});
test('psychic cost is paid on resistance and shield is passive', function (): void {
    $state = $this->psychicState('Beherrschung');
    $state['actors'][2]['profile']['psychic']['powers'] = [['name' => 'Gedankenschild', 'value' => 3, 'usable' => true]];
    $state = $this->step($state, 'action', 1, ['kind' => 'psychic', 'power' => 'Beherrschung', 'duration' => 1, 'range' => 1, 'strength' => 0], [1, 1]);
    expect($state['actors'][1]['pep'])->toBe(98);
    $state = $this->step($state, 'psychic_resistance', 2, [], [6, 6]);
    expect($state['actors'][2]['effects'])->toBe([])
        ->and($state['actors'][2]['pep'])->toBe(100);
});
test('control changes decision controller and expires in game time', function (): void {
    $state = $this->psychicState('Beherrschung');
    $state = $this->step($state, 'action', 1, ['kind' => 'psychic', 'power' => 'Beherrschung', 'duration' => 1, 'range' => 1, 'strength' => 0], [6, 6]);
    $state = $this->step($state, 'psychic_resistance', 2, [], [1, 1]);
    expect(array_first($state['tasks'])['controller'])->toBe(1);
    foreach ([2, 1, 2, 1, 2, 1, 2] as $side) {
        $state = $this->step($state, 'action', $side, ['kind' => 'wait']);
    }
    expect($state['seconds'])->toBe(12)
        ->and($state['actors'][2]['effects'])->toBe([]);
});
test('pyrokinesis uses round count and does not add willpower to damage', function (): void {
    $state = $this->psychicState('Pyrokinese');
    $state['actors'][1]['profile']['attributes']['wi'] = 5;
    $state = $this->step($state, 'action', 1, ['kind' => 'psychic', 'power' => 'Pyrokinese', 'duration' => 2, 'range' => 1, 'strength' => 0, 'damage' => 1], [6, 6]);
    expect($state['actors'][1]['pep'])->toBe(82);
    $state = $this->step($state, 'psychic_resistance', 2, [], [1, 1]);
    $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
    $state = $this->step($state, 'damage', 1, [], [1]);
    expect($state['actors'][2]['wounds'])->toBe([1]);
    foreach ([1, 2] as $side) {
        $state = $this->step($state, 'action', $side, ['kind' => 'wait']);
    }
    $state = $this->step($state, 'damage', 1, [], [1]);
    expect($state['actors'][2]['wounds'])->toBe([1, 1])
        ->and($state['actors'][2]['effects'])->toBe([]);
});
test('telekinetic projectile uses strength index minus three', function (): void {
    $state = $this->psychicState('Telekinese');
    $state = $this->step($state, 'action', 1, ['kind' => 'psychic', 'power' => 'Telekinese', 'duration' => 3, 'range' => 1, 'strength' => 4, 'effect' => 'projectile'], [6, 6]);
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [2, 2]);
    $state = $this->step($state, 'damage', 1, [], [2]);
    expect($state['actors'][2]['wounds'])->toBe([2])
        ->and($state['actors'][1]['pep'])->toBe(92);
});
test('automatic decisions can finish a whole duel', function (): void {
    $state = $this->state(limit: 5);
    $automatic = new AutomaticDecision;
    for ($steps = 0; $state['end'] === null && $steps < 200; $steps++) {
        $tasks = $state['tasks'];
        if ($state['continuation']) {
            $tasks = array_filter($tasks, fn ($t) => $t['type'] === 'ruling');
        }
        $task = reset($tasks);
        expect($task)->toBeArray();
        $state = $this->engine->decide($state, $task['token'], $automatic->input($state, $task))['state'];
    }
    expect($state['end'])->not->toBeNull()
        ->and($steps)->toBeLessThan(200);
});
test('technology failure prevents weapon and armor use', function (): void {
    $profile = $this->profile();
    $profile['skills']['Bildung'] = 0;
    $profile['weapons']['sword']['education'] = 4;
    $profile['armor'] = ['id' => 'kampfpanzer', 'education' => 3, 'protection' => 3, 'movementModifier' => -1];
    $state = $this->engine->start([$profile, $this->profile()], 100, 10)['state'];
    $state['rules']['hands'] = 'catalog';
    $state = $this->step($state, 'prepare', 1, ['weapons' => ['sword'], 'shield' => false, 'skill' => 'Nahkampf']);
    $state = $this->step($state, 'technology', 1, [], [1, 1]);
    $state = $this->step($state, 'technology', 1, [], [1, 1]);
    expect($state['actors'][1]['held'])->toBe([])
        ->and($state['actors'][1]['armor_broken'])->toBeTrue()
        ->and(fn () => CombatStats::weapon($state['actors'][1], 'sword', false))->toThrow(InvalidArgumentException::class);
});
test('switching chainsaw starts fuel once and pickup costs an action', function (): void {
    $state = $this->state();
    $state['actors'][1]['weapons']['sword']['id'] = 'kettensaege';
    $state = $this->step($state, 'action', 1, ['kind' => 'switch', 'weapons' => ['sword'], 'shield' => false], [2, 3]);
    expect($state['actors'][1]['weapons']['sword']['fuel'])->toBe(23);
    $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
    expect($state['actors'][1]['weapons']['sword']['fuel'])->toBe(22);
    $state['actors'][1]['held'] = [];
    $state['actors'][1]['weapons']['sword']['position'] = 0;
    $state = $this->step($state, 'action', 1, ['kind' => 'pickup', 'weapon' => 'sword']);
    expect($state['actors'][1]['weapons']['sword']['position'])->toBeNull()
        ->and($state['actors'][1]['held'])->toBe([]);
});
test('dual attacks use two reactions and only one parry', function (): void {
    $state = $this->state();
    $state['actors'][1]['weapons']['second'] = array_replace($state['actors'][1]['weapons']['sword'], ['id' => 'second', 'instance' => 'second']);
    $state['actors'][1]['held'] = ['sword', 'second'];
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [3, 3]);
    expect($state['pending']['modifiers']['Zwei Waffen'])->toBe(-2)
        ->and($state['queue'])->toHaveCount(1);
    $state = $this->step($state, 'defense', 2, ['defense' => 'parry'], [6, 6, 3, 3]);
    expect($state['actors'][2]['parried'])->toBeTrue();
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [6, 6]);
    expect($state['actors'][2]['dodges'])->toBe(1)
        ->and($state['queue'])->toBe([]);
});
test('driller secondary hits shooter once and uses no extra ammo', function (): void {
    $state = $this->state(ranged: true);
    $state['actors'][1]['weapons']['sword']['id'] = 'driller';
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [2, 2]);
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [6, 6, 6, 6]);
    expect($state['pending']['target'])->toBe(1)
        ->and($state['pending']['modifiers']['Präzision'])->toBe(-4);
    $state = $this->step($state, 'defense', 1, ['defense' => 'dodge'], [3, 3]);
    $state = $this->step($state, 'damage', 1, [], [1]);
    expect($state['actors'][1]['wounds'])->toBe([1])
        ->and($state['actors'][2]['wounds'])->toBe([])
        ->and($state['actors'][1]['weapons']['sword']['loaded'])->toBe(39);
});
test('object damage uses leader material and does not wound owner', function (): void {
    $state = $this->state();
    $state['actors'][2]['profile']['shield'] = ['id' => 'holzschild'];
    $state['actors'][2]['shield'] = true;
    $state['actors'][1]['profile']['attributes']['st'] = 5;
    $state = $this->step($state, 'action', 1, ['kind' => 'object', 'weapon' => 'sword', 'target' => 'shield'], [6, 6]);
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [2, 2]);
    $state = $this->step($state, 'damage', 1, [], [4]);
    expect($state['actors'][2]['shield_broken'])->toBeTrue()
        ->and($state['actors'][2]['wounds'])->toBe([]);
});
test('wound ruling reuses damage roll and regeneration uses game time', function (): void {
    $state = $this->state();
    unset($state['rules']['wounds']);
    $state['actors'][2]['wounds'] = [2];
    $state['actors'][2]['profile']['advantages'] = ['Regeneration'];
    $state['actors'][2]['profile']['advantage_counts'] = ['Regeneration' => 8];
    $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [6, 6]);
    $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [2, 2]);
    $state = $this->step($state, 'damage', 1, [], [2]);
    $calls = $this->dice->calls;
    $state = $this->step($state, 'ruling', 0, ['choices' => ['wounds' => 'persistent'], 'reason' => 'Mittlere Wunden behalten.']);
    expect($this->dice->calls)->toBe($calls)
        ->and($state['actors'][2]['wounds'])->toBe([2, 3]);
    $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
    expect($state['actors'][2]['wounds'])->toBe([]);
});
test('telekinetic movement waits for mass ruling and travel time', function (): void {
    $state = $this->psychicState('Telekinese');
    unset($state['rules']['telekinetic_mass']);
    $state = $this->step($state, 'action', 1, ['kind' => 'psychic', 'power' => 'Telekinese', 'effect' => 'move', 'displacement' => 500, 'duration' => 2, 'range' => 2, 'strength' => 0], [6, 6]);
    $state = $this->step($state, 'psychic_resistance', 2, [], [1, 1]);
    $state = $this->step($state, 'ruling', 0, ['choices' => ['telekinetic_mass' => 'sufficient'], 'reason' => 'Zielmasse für dieses Beispiel ausreichend.']);
    expect($state['actors'][2]['position'])->toBe(100);
    foreach ([2, 1, 2, 1, 2, 1, 2] as $side) {
        $state = $this->step($state, 'action', $side, ['kind' => 'wait']);
    }
    expect($state['actors'][2]['position'])->toBe(600)
        ->and($state['actors'][2]['effects'])->toBe([]);
});
test('informational talents and shared shield have no invented attack bonus', function (string $power, array $extra, bool $resist): void {
    $state = $this->psychicState($power);
    $state = $this->step($state, 'action', 1, array_replace(['kind' => 'psychic', 'power' => $power, 'duration' => 1, 'range' => 1, 'strength' => 0], $extra), [6, 6]);
    if ($resist) {
        $state = $this->step($state, 'psychic_resistance', 2, [], [1, 1]);
    }
    expect($state['actors'][2]['wounds'])->toBe([]);
    if ($power === 'Gedankenschild') {
        expect(CombatStats::psychicDefense($state['actors'][2], $state['rules'])['Gedankenschild'])->toBe(4);
    } else {
        expect($state['actors'][2]['effects'])->toBe([]);
    }
})->with('RpgCombatEngineTest.informationPowers');
test('automatic strategy recovers from empty ammo prone and out of range', function (): void {
    $automatic = new AutomaticDecision;
    $state = $this->state(ranged: true);
    $task = ['type' => 'action', 'side' => 1];
    $state['actors'][1]['weapons']['sword']['loaded'] = 0;
    expect($automatic->input($state, $task)['kind'])->toBe('reload');
    $state['actors'][1]['weapons']['sword']['jammed'] = true;
    expect($automatic->input($state, $task)['kind'])->toBe('unjam');
    $state['actors'][1]['prone'] = true;
    expect($automatic->input($state, $task)['kind'])->toBe('stand');
    $state['actors'][1]['prone'] = false;
    $state['actors'][1]['full_defense'] = true;
    expect($automatic->input($state, $task)['kind'])->toBe('wait');
    $state['actors'][1]['full_defense'] = false;
    $state['actors'][1]['weapons']['sword']['jammed'] = false;
    $state['actors'][1]['weapons']['sword']['reserve'] = 0;
    $state['actors'][2]['position'] = 20000;
    expect($automatic->input($state, $task))->toBe(['kind' => 'move', 'move' => 400]);
});
test('invalid actions are rejected before any roll', function (array $input): void {
    $state = $this->state();
    $before = $this->dice->calls;
    try {
        $this->step($state, 'action', 1, $input);
        $this->fail('Expected invalid action');
    } catch (InvalidArgumentException) {
        expect($this->dice->calls)->toBe($before);
    }
})->with('RpgCombatEngineTest.invalidActions');
