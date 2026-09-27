<?php

namespace Tests\Unit;

use App\Services\RpgCombat\AutomaticDecision;
use App\Services\RpgCombat\CombatEngine;
use App\Services\RpgCombat\CombatStats;
use App\Support\RpgCombatRules;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\CombatTestDice;

class RpgCombatEngineTest extends TestCase
{
    private CombatEngine $engine;

    private CombatTestDice $dice;

    protected function setUp(): void
    {
        $this->dice = new CombatTestDice;
        $this->engine = new CombatEngine($this->dice);
    }

    private function profile(string $weapon = 'sword', bool $ranged = false): array
    {
        $mode = ['kind' => $ranged ? 'ranged' : 'melee', 'skill' => $ranged ? 'Feuerwaffen' : 'Nahkampf', 'attributes' => $ranged ? ['wa'] : ['st', 'ge'],
            'damage' => 1, 'precision' => 1, 'type' => $ranged ? 'Schießpulverwaffe' : '', 'fireRate' => 'A', 'rangeIncrement' => '10m', 'maxRange' => '100m', 'magazine' => 40];
        $w = ['instance' => $weapon, 'id' => $weapon, 'name' => $weapon, 'modes' => [$mode], 'hands' => 1, 'size' => 'medium', 'education' => 0,
            'capacity' => $ranged ? 40 : 0, 'loaded' => $ranged ? 40 : 0, 'reserve' => $ranged ? 120 : 0, 'thrown' => false, 'natural' => false, 'jammed' => false, 'broken' => false, 'position' => null, 'fuel' => null, 'progress' => 0];

        return ['name' => 'Figur', 'revision' => 1, 'attributes' => array_fill_keys(['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'], 0),
            'skills' => ['Nahkampf' => 3, 'Athletik' => 2, 'Feuerwaffen' => 3, 'Bildung' => 3], 'advantages' => [], 'disadvantages' => [], 'advantage_counts' => [], 'disadvantage_details' => [],
            'weapons' => [$weapon => $w], 'items' => ['heilgel' => 1], 'armor' => null, 'shield' => null, 'psychic' => ['pep' => 100, 'powers' => []], 'sources' => []];
    }

    private function state(bool $ranged = false, bool $tied = false, int $limit = 100): array
    {
        $profile = $this->profile('sword', $ranged);
        $state = $this->engine->start([$profile, $profile], 100, $limit)['state'];
        $state['rules'] = array_combine(array_keys(RpgCombatRules::rulings()), array_map(RpgCombatRules::defaultRuling(...), array_keys(RpgCombatRules::rulings())));
        foreach ([1, 2] as $side) {
            $state = $this->step($state, 'prepare', $side, ['weapons' => ['sword'], 'shield' => false, 'skill' => $ranged ? 'Feuerwaffen' : 'Nahkampf']);
        }
        $this->dice->values = $tied ? [4, 4] : [6, 1];
        foreach ([1, 2] as $side) {
            $state = $this->step($state, 'initiative', $side);
        }

        return $state;
    }

    private function step(array $state, string $type, int $side, array $input = [], ?array $dice = null): array
    {
        if ($dice !== null) {
            $this->dice->values = $dice;
        }
        $task = array_values(array_filter($state['tasks'], fn ($t) => $t['type'] === $type && $t['side'] === $side))[0] ?? null;
        $this->assertNotNull($task, 'Expected '.$type.' for '.$side.'; '.json_encode($state['tasks']));

        return $this->engine->decide($state, $task['token'], $input)['state'];
    }

    public function test_initiative_is_fixed_and_round_limit_is_terminal(): void
    {
        $state = $this->state(limit: 2);
        $this->assertSame([1], $state['group']);
        foreach ([1, 2, 1, 2] as $side) {
            $state = $this->step($state, 'action', $side, ['kind' => 'wait']);
        }
        $this->assertSame(['winner' => null, 'reason' => 'round_limit'], $state['end']);
        $this->assertSame(6, $state['seconds']);
        $this->assertSame(2, $this->dice->calls);
    }

    public function test_equal_initiative_seals_actions_and_rerolls_next_round(): void
    {
        $state = $this->state(tied: true);
        $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
        $this->assertCount(1, $state['tasks']);
        $this->assertSame(0, $state['seconds']);
        $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
        $this->assertSame('initiative', $state['phase']);
        $this->assertSame(2, $state['round']);
    }

    public function test_simultaneous_knockouts_are_draws(): void
    {
        $state = $this->state(tied: true);
        foreach ([1, 2] as $side) {
            $state = $this->step($state, 'action', $side, ['kind' => 'attack', 'weapon' => 'sword'], [6, 6]);
        }
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [2, 2]);
        $state = $this->step($state, 'damage', 1, [], [6, 6, 6]);
        $this->assertNull($state['end']);
        $state = $this->step($state, 'defense', 1, ['defense' => 'dodge'], [2, 2]);
        $state = $this->step($state, 'damage', 2, [], [6]);
        $this->assertSame(['winner' => null, 'reason' => 'mutual_incapacity'], $state['end']);
    }

    public function test_tied_defense_blocks_and_parry_is_consumed(): void
    {
        $state = $this->state();
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [4, 4]);
        $state = $this->step($state, 'defense', 2, ['defense' => 'parry'], [4, 4]);
        $this->assertTrue($state['actors'][2]['parried']);
        $this->assertSame([], $state['actors'][2]['wounds']);
        $this->expectException(InvalidArgumentException::class);
        CombatStats::defense($state['actors'][2], 'parry', $state['rules'], 1, false);
    }

    public function test_full_defense_forbids_own_attack_but_allows_movement(): void
    {
        $state = $this->state();
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [4, 4]);
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge', 'full_defense' => true], [6, 6]);
        $this->expectException(InvalidArgumentException::class);
        $this->step($state, 'action', 2, ['kind' => 'attack', 'weapon' => 'sword']);
    }

    public function test_defense_fumble_blocks_current_and_next_round(): void
    {
        $state = $this->state();
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [4, 4]);
        $state = $this->step($state, 'defense', 2, ['defense' => 'parry'], [1, 1]);
        $state = $this->step($state, 'damage', 1, [], [1]);
        $this->assertSame(2, $state['actors'][2]['blocked_until']);
        $this->assertSame(2, $state['round']);
        $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
        $this->assertSame(3, $state['round']);
        $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
        $state = $this->step($state, 'action', 2, ['kind' => 'stand']);
        $this->assertFalse($state['actors'][2]['prone']);
    }

    public function test_melee_fumble_drops_weapon_only_after_failed_agility(): void
    {
        $state = $this->state();
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [1, 1]);
        $state = $this->step($state, 'defense', 2, ['defense' => 'parry'], [6, 6]);
        $state = $this->step($state, 'fumble', 1, [], [1, 2]);
        $this->assertSame([], $state['actors'][1]['held']);
        $this->assertSame(0, $state['actors'][1]['weapons']['sword']['position']);
        $this->assertFalse($state['actors'][1]['prone']);
    }

    public function test_ranged_fumble_refunds_unfired_ammo_and_jams(): void
    {
        $state = $this->state(ranged: true);
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword', 'fire' => 'A'], [1, 1]);
        $this->assertSame(25, $state['actors'][1]['weapons']['sword']['loaded']);
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [6, 6, 1]);
        $this->assertTrue($state['actors'][1]['weapons']['sword']['jammed']);
        $this->assertSame(40, $state['actors'][1]['weapons']['sword']['loaded']);
    }

    public function test_automatic_fire_spends_fifteen_but_creates_one_hit(): void
    {
        $state = $this->state(ranged: true);
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword', 'fire' => 'A'], [6, 6]);
        $this->assertCount(1, $state['tasks']);
        $this->assertSame([], $state['queue']);
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [3, 3]);
        $state = $this->step($state, 'damage', 1, [], [1]);
        $this->assertCount(1, $state['actors'][2]['wounds']);
        $this->assertSame(25, $state['actors'][1]['weapons']['sword']['loaded']);
    }

    public function test_knockdown_never_inflicts_normal_wounds(): void
    {
        $state = $this->state();
        $state = $this->step($state, 'action', 1, ['kind' => 'knockdown', 'weapon' => 'sword'], [6, 6]);
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [3, 3]);
        $state = $this->step($state, 'strength', 2, [], [1, 1]);
        $this->assertTrue($state['actors'][2]['prone']);
        $this->assertSame([], $state['actors'][2]['wounds']);
        $this->assertSame([], $state['actors'][2]['held']);
        $this->assertSame(0, $state['actors'][2]['blocked_until']);
    }

    public function test_disarm_tie_requests_ruling_without_rerolling(): void
    {
        $state = $this->state();
        unset($state['rules']['tie']);
        $state = $this->step($state, 'action', 1, ['kind' => 'disarm', 'weapon' => 'sword'], [4, 5]);
        $state = $this->step($state, 'resistance', 2, [], [4, 4]);
        $this->assertSame('awaiting_ruling', $state['phase']);
        $calls = $this->dice->calls;
        $state = $this->step($state, 'ruling', 0, ['choices' => ['tie' => 'attacker'], 'reason' => 'Angreifer gewinnt diesen Gleichstand.']);
        $this->assertSame($calls, $this->dice->calls);
        $this->assertSame([], $state['actors'][2]['held']);
    }

    public function test_ruling_invalidating_choice_reopens_it(): void
    {
        $state = $this->state();
        unset($state['rules']['full_defense']);
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [4, 4]);
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge', 'full_defense' => true]);
        $state = $this->step($state, 'ruling', 0, ['choices' => ['full_defense' => 'own_turn'], 'reason' => 'Erst bei eigener Aktion erklären.']);
        $this->assertSame('own_turn', $state['rules']['full_defense']);
        $this->assertSame('defense', array_values($state['tasks'])[0]['type']);
        $this->assertSame(4, $this->dice->calls);
    }

    public function test_simultaneous_movement_uses_both_positions_and_can_lose_range(): void
    {
        $state = $this->state(tied: true);
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword']);
        $state = $this->step($state, 'action', 2, ['kind' => 'move', 'move' => 400]);
        $this->assertSame(500, $state['actors'][2]['position']);
        $this->assertSame(2, $this->dice->calls);
        $this->assertSame([], $state['actors'][2]['wounds']);
    }

    public function test_reload_and_heal_use_actions_and_only_snapshot_resources(): void
    {
        $state = $this->state(ranged: true);
        $state['actors'][1]['weapons']['sword']['loaded'] = 0;
        $state['actors'][2]['wounds'] = [2, 3];
        $state = $this->step($state, 'action', 1, ['kind' => 'reload', 'weapon' => 'sword']);
        $this->assertSame(40, $state['actors'][1]['weapons']['sword']['loaded']);
        $this->assertSame(80, $state['actors'][1]['weapons']['sword']['reserve']);
        $state = $this->step($state, 'action', 2, ['kind' => 'heal']);
        $this->assertSame([2, 2], $state['actors'][2]['wounds']);
        $this->assertSame(0, $state['actors'][2]['items']['heilgel']);
    }

    public function test_psychic_cost_is_paid_on_resistance_and_shield_is_passive(): void
    {
        $state = $this->psychicState('Beherrschung');
        $state['actors'][2]['profile']['psychic']['powers'] = [['name' => 'Gedankenschild', 'value' => 3, 'usable' => true]];
        $state = $this->step($state, 'action', 1, ['kind' => 'psychic', 'power' => 'Beherrschung', 'duration' => 1, 'range' => 1, 'strength' => 0], [1, 1]);
        $this->assertSame(98, $state['actors'][1]['pep']);
        $state = $this->step($state, 'psychic_resistance', 2, [], [6, 6]);
        $this->assertSame([], $state['actors'][2]['effects']);
        $this->assertSame(100, $state['actors'][2]['pep']);
    }

    private function psychicState(string $power): array
    {
        $state = $this->state();
        $state['actors'][1]['profile']['psychic']['powers'] = [['name' => $power, 'value' => 4, 'usable' => true]];

        return $state;
    }

    public function test_control_changes_decision_controller_and_expires_in_game_time(): void
    {
        $state = $this->psychicState('Beherrschung');
        $state = $this->step($state, 'action', 1, ['kind' => 'psychic', 'power' => 'Beherrschung', 'duration' => 1, 'range' => 1, 'strength' => 0], [6, 6]);
        $state = $this->step($state, 'psychic_resistance', 2, [], [1, 1]);
        $this->assertSame(1, array_values($state['tasks'])[0]['controller']);
        foreach ([2, 1, 2, 1, 2, 1, 2] as $side) {
            $state = $this->step($state, 'action', $side, ['kind' => 'wait']);
        }
        $this->assertSame(12, $state['seconds']);
        $this->assertSame([], $state['actors'][2]['effects']);
    }

    public function test_pyrokinesis_uses_round_count_and_does_not_add_willpower_to_damage(): void
    {
        $state = $this->psychicState('Pyrokinese');
        $state['actors'][1]['profile']['attributes']['wi'] = 5;
        $state = $this->step($state, 'action', 1, ['kind' => 'psychic', 'power' => 'Pyrokinese', 'duration' => 2, 'range' => 1, 'strength' => 0, 'damage' => 1], [6, 6]);
        $this->assertSame(82, $state['actors'][1]['pep']);
        $state = $this->step($state, 'psychic_resistance', 2, [], [1, 1]);
        $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
        $state = $this->step($state, 'damage', 1, [], [1]);
        $this->assertSame([1], $state['actors'][2]['wounds']);
        foreach ([1, 2] as $side) {
            $state = $this->step($state, 'action', $side, ['kind' => 'wait']);
        }
        $state = $this->step($state, 'damage', 1, [], [1]);
        $this->assertSame([1, 1], $state['actors'][2]['wounds']);
        $this->assertSame([], $state['actors'][2]['effects']);
    }

    public function test_telekinetic_projectile_uses_strength_index_minus_three(): void
    {
        $state = $this->psychicState('Telekinese');
        $state = $this->step($state, 'action', 1, ['kind' => 'psychic', 'power' => 'Telekinese', 'duration' => 3, 'range' => 1, 'strength' => 4, 'effect' => 'projectile'], [6, 6]);
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [2, 2]);
        $state = $this->step($state, 'damage', 1, [], [2]);
        $this->assertSame([2], $state['actors'][2]['wounds']);
        $this->assertSame(92, $state['actors'][1]['pep']);
    }

    public function test_automatic_decisions_can_finish_a_whole_duel(): void
    {
        $state = $this->state(limit: 5);
        $automatic = new AutomaticDecision;
        for ($steps = 0; $state['end'] === null && $steps < 200; $steps++) {
            $tasks = $state['tasks'];
            if ($state['continuation']) {
                $tasks = array_filter($tasks, fn ($t) => $t['type'] === 'ruling');
            }
            $task = reset($tasks);
            $this->assertIsArray($task);
            $state = $this->engine->decide($state, $task['token'], $automatic->input($state, $task))['state'];
        }
        $this->assertNotNull($state['end']);
        $this->assertLessThan(200, $steps);
    }

    public function test_technology_failure_prevents_weapon_and_armor_use(): void
    {
        $profile = $this->profile();
        $profile['skills']['Bildung'] = 0;
        $profile['weapons']['sword']['education'] = 4;
        $profile['armor'] = ['id' => 'kampfpanzer', 'education' => 3, 'protection' => 3, 'movementModifier' => -1];
        $state = $this->engine->start([$profile, $this->profile()], 100, 10)['state'];
        $state['rules']['hands'] = 'catalog';
        $state = $this->step($state, 'prepare', 1, ['weapons' => ['sword'], 'shield' => false, 'skill' => 'Nahkampf']);
        $state = $this->step($state, 'technology', 1, [], [1, 1]);
        $state = $this->step($state, 'technology', 1, [], [1, 1]);
        $this->assertSame([], $state['actors'][1]['held']);
        $this->assertTrue($state['actors'][1]['armor_broken']);
        $this->expectException(InvalidArgumentException::class);
        CombatStats::weapon($state['actors'][1], 'sword', false);
    }

    public function test_switching_chainsaw_starts_fuel_once_and_pickup_costs_an_action(): void
    {
        $state = $this->state();
        $state['actors'][1]['weapons']['sword']['id'] = 'kettensaege';
        $state = $this->step($state, 'action', 1, ['kind' => 'switch', 'weapons' => ['sword'], 'shield' => false], [2, 3]);
        $this->assertSame(23, $state['actors'][1]['weapons']['sword']['fuel']);
        $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
        $this->assertSame(22, $state['actors'][1]['weapons']['sword']['fuel']);
        $state['actors'][1]['held'] = [];
        $state['actors'][1]['weapons']['sword']['position'] = 0;
        $state = $this->step($state, 'action', 1, ['kind' => 'pickup', 'weapon' => 'sword']);
        $this->assertNull($state['actors'][1]['weapons']['sword']['position']);
        $this->assertSame([], $state['actors'][1]['held']);
    }

    public function test_dual_attacks_use_two_reactions_and_only_one_parry(): void
    {
        $state = $this->state();
        $state['actors'][1]['weapons']['second'] = array_replace($state['actors'][1]['weapons']['sword'], ['id' => 'second', 'instance' => 'second']);
        $state['actors'][1]['held'] = ['sword', 'second'];
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [3, 3]);
        $this->assertSame(-2, $state['pending']['modifiers']['Zwei Waffen']);
        $this->assertCount(1, $state['queue']);
        $state = $this->step($state, 'defense', 2, ['defense' => 'parry'], [6, 6, 3, 3]);
        $this->assertTrue($state['actors'][2]['parried']);
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [6, 6]);
        $this->assertSame(1, $state['actors'][2]['dodges']);
        $this->assertSame([], $state['queue']);
    }

    public function test_driller_secondary_hits_shooter_once_and_uses_no_extra_ammo(): void
    {
        $state = $this->state(ranged: true);
        $state['actors'][1]['weapons']['sword']['id'] = 'driller';
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'sword'], [2, 2]);
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [6, 6, 6, 6]);
        $this->assertSame(1, $state['pending']['target']);
        $this->assertSame(-4, $state['pending']['modifiers']['Präzision']);
        $state = $this->step($state, 'defense', 1, ['defense' => 'dodge'], [3, 3]);
        $state = $this->step($state, 'damage', 1, [], [1]);
        $this->assertSame([1], $state['actors'][1]['wounds']);
        $this->assertSame([], $state['actors'][2]['wounds']);
        $this->assertSame(39, $state['actors'][1]['weapons']['sword']['loaded']);
    }

    public function test_object_damage_uses_leader_material_and_does_not_wound_owner(): void
    {
        $state = $this->state();
        $state['actors'][2]['profile']['shield'] = ['id' => 'holzschild'];
        $state['actors'][2]['shield'] = true;
        $state['actors'][1]['profile']['attributes']['st'] = 5;
        $state = $this->step($state, 'action', 1, ['kind' => 'object', 'weapon' => 'sword', 'target' => 'shield'], [6, 6]);
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [2, 2]);
        $state = $this->step($state, 'damage', 1, [], [4]);
        $this->assertTrue($state['actors'][2]['shield_broken']);
        $this->assertSame([], $state['actors'][2]['wounds']);
    }

    public function test_wound_ruling_reuses_damage_roll_and_regeneration_uses_game_time(): void
    {
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
        $this->assertSame($calls, $this->dice->calls);
        $this->assertSame([2, 3], $state['actors'][2]['wounds']);
        $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
        $this->assertSame([], $state['actors'][2]['wounds']);
    }

    public function test_telekinetic_movement_waits_for_mass_ruling_and_travel_time(): void
    {
        $state = $this->psychicState('Telekinese');
        unset($state['rules']['telekinetic_mass']);
        $state = $this->step($state, 'action', 1, ['kind' => 'psychic', 'power' => 'Telekinese', 'effect' => 'move', 'displacement' => 500, 'duration' => 2, 'range' => 2, 'strength' => 0], [6, 6]);
        $state = $this->step($state, 'psychic_resistance', 2, [], [1, 1]);
        $state = $this->step($state, 'ruling', 0, ['choices' => ['telekinetic_mass' => 'sufficient'], 'reason' => 'Zielmasse für dieses Beispiel ausreichend.']);
        $this->assertSame(100, $state['actors'][2]['position']);
        foreach ([2, 1, 2, 1, 2, 1, 2] as $side) {
            $state = $this->step($state, 'action', $side, ['kind' => 'wait']);
        }
        $this->assertSame(600, $state['actors'][2]['position']);
        $this->assertSame([], $state['actors'][2]['effects']);
    }

    #[DataProvider('informationPowers')]
    public function test_informational_talents_and_shared_shield_have_no_invented_attack_bonus(string $power, array $extra, bool $resist): void
    {
        $state = $this->psychicState($power);
        $state = $this->step($state, 'action', 1, array_replace(['kind' => 'psychic', 'power' => $power, 'duration' => 1, 'range' => 1, 'strength' => 0], $extra), [6, 6]);
        if ($resist) {
            $state = $this->step($state, 'psychic_resistance', 2, [], [1, 1]);
        }
        $this->assertSame([], $state['actors'][2]['wounds']);
        if ($power === 'Gedankenschild') {
            $this->assertSame(4, CombatStats::psychicDefense($state['actors'][2], $state['rules'])['Gedankenschild']);
        } else {
            $this->assertSame([], $state['actors'][2]['effects']);
        }
    }

    public static function informationPowers(): array
    {
        return [['Empathie', [], false], ['Empathie', ['strength' => 4], true], ['Telepathie', ['effect' => 'read', 'strength' => 2], true], ['Telepathie', ['effect' => 'send'], false], ['Gedankenschild', [], false]];
    }

    public function test_automatic_strategy_recovers_from_empty_ammo_prone_and_out_of_range(): void
    {
        $automatic = new AutomaticDecision;
        $state = $this->state(ranged: true);
        $task = ['type' => 'action', 'side' => 1];
        $state['actors'][1]['weapons']['sword']['loaded'] = 0;
        $this->assertSame('reload', $automatic->input($state, $task)['kind']);
        $state['actors'][1]['weapons']['sword']['jammed'] = true;
        $this->assertSame('unjam', $automatic->input($state, $task)['kind']);
        $state['actors'][1]['prone'] = true;
        $this->assertSame('stand', $automatic->input($state, $task)['kind']);
        $state['actors'][1]['prone'] = false;
        $state['actors'][1]['full_defense'] = true;
        $this->assertSame('wait', $automatic->input($state, $task)['kind']);
        $state['actors'][1]['full_defense'] = false;
        $state['actors'][1]['weapons']['sword']['jammed'] = false;
        $state['actors'][1]['weapons']['sword']['reserve'] = 0;
        $state['actors'][2]['position'] = 20000;
        $this->assertSame(['kind' => 'move', 'move' => 400], $automatic->input($state, $task));
    }

    #[DataProvider('invalidActions')]
    public function test_invalid_actions_are_rejected_before_any_roll(array $input): void
    {
        $state = $this->state();
        $before = $this->dice->calls;
        try {
            $this->step($state, 'action', 1, $input);
            $this->fail('Expected invalid action');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, $this->dice->calls);
        }
    }

    public static function invalidActions(): array
    {
        return array_map(fn ($input) => [$input], [
            ['kind' => 'unknown'], ['kind' => 'attack', 'weapon' => 'absent'], ['kind' => 'attack', 'weapon' => []],
            ['kind' => 'attack', 'weapon' => 'sword', 'mode' => 55], ['kind' => 'attack', 'weapon' => 'sword', 'aim' => 100],
            ['kind' => 'attack', 'weapon' => 'sword', 'attribute' => 'in'], ['kind' => 'attack', 'weapon' => 'sword', 'dice' => [6, 6]],
            ['kind' => 'move', 'move' => 401], ['kind' => 'run', 'move' => 1601], ['kind' => 'move', 'move' => '1'],
            ['kind' => 'stand'], ['kind' => 'heal'], ['kind' => 'reload', 'weapon' => 'sword'], ['kind' => 'unjam', 'weapon' => 'sword'],
            ['kind' => 'pickup', 'weapon' => 'sword'], ['kind' => 'psychic', 'power' => 'Pyrokinese'], ['kind' => 'creative', 'description' => 'x'],
            ['kind' => 'switch', 'weapons' => [['bad']], 'shield' => false], ['kind' => 'switch', 'weapons' => ['sword', 'sword'], 'shield' => false],
        ]);
    }
}
