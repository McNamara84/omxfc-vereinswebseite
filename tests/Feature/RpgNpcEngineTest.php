<?php

namespace Tests\Feature;

use App\Services\RpgCombat\CombatEngine;
use App\Services\RpgCombat\CombatStats;
use App\Services\RpgCombat\NpcCombatSnapshotFactory;
use App\Support\RpgCombatRules;
use App\Support\RpgNpcCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CombatTestDice;
use Tests\TestCase;

class RpgNpcEngineTest extends TestCase
{
    use RefreshDatabase;

    private CombatTestDice $dice;

    private CombatEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dice = new CombatTestDice;
        $this->engine = new CombatEngine($this->dice);
    }

    private function state(string $key, int $distance = 100, bool $tied = false): array
    {
        $factory = app(NpcCombatSnapshotFactory::class);
        $npc = $factory->build(RpgNpcCatalog::all()[$key], $key === 'daamure' ? ['rank' => 'Leq', 'profession' => 'Techniker'] : []);
        $player = $factory->build(RpgNpcCatalog::all()['bandit'], ['weapon' => 'schwert']);
        unset($player['npc']);
        $player['disadvantages'] = [];
        $state = $this->engine->start([$npc, $player], $distance, 100)['state'];
        $state['rules'] = array_combine(array_keys(RpgCombatRules::rulings()), array_map(RpgCombatRules::defaultRuling(...), array_keys(RpgCombatRules::rulings())));
        foreach ([1, 2] as $side) {
            $state = $this->step($state, 'prepare', $side, ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf']);
        }
        $this->dice->values = $tied ? [1, 5] : [6, 1];
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
        $task = collect($state['tasks'])->first(fn ($t) => $t['type'] === $type && $t['side'] === $side);
        $this->assertNotNull($task, json_encode($state['tasks']));

        return $this->engine->decide($state, $task['token'], $input)['state'];
    }

    private function ruling(array $extra = []): array
    {
        return array_replace(['effect' => 'paralyzed', 'duration' => 2, 'modifier' => 2, 'displacement' => 0, 'range' => 1000,
            'resistance_attribute' => 'none', 'difficulty' => 6, 'reason' => 'Die ergänzende Regel gilt für diese Fähigkeit.'], $extra);
    }

    public static function clawTemplates(): array
    {
        return ['avtar' => ['avtar'], 'eluu' => ['eluu'], 'izekeepir' => ['izekeepir']];
    }

    #[DataProvider('clawTemplates')]
    public function test_two_claws_have_independent_defenses_and_no_two_weapon_penalty(string $key): void
    {
        $state = $this->state($key);
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'body:0'], [1, 3]);
        $this->assertSame(0, $state['pending']['modifiers']['Zwei Waffen']);
        $this->assertCount(1, $state['queue']);
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [6, 6, 1, 3]);
        $this->assertSame(1, $state['actors'][2]['dodges']);
        $this->assertSame(-1, CombatStats::defense($state['actors'][2], 'dodge', [], 1, false)['Wiederholtes Ausweichen']);
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [6, 6]);
        $this->assertSame(2, $state['actors'][2]['dodges']);
        $this->assertCount(0, $state['queue']);
    }

    public function test_beak_is_only_one_attack_and_creatures_cannot_parry(): void
    {
        $state = $this->state('avtar');
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'body:1'], [3, 4]);
        $this->assertCount(0, $state['queue']);
        $this->expectException(InvalidArgumentException::class);
        CombatStats::defense($state['actors'][1], 'parry', [], 1, false);
    }

    public function test_wisaau_requires_real_runup_and_bite_remains_available(): void
    {
        $state = $this->state('wisaau', 700);
        try {
            $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'body:0']);
            $this->fail('No run-up');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Anlauf', $e->getMessage());
        }
        $state = $this->step($state, 'action', 1, ['kind' => 'run', 'move' => 600]);
        $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
        $state = $this->step($state, 'action', 1, ['kind' => 'attack', 'weapon' => 'body:0'], [3, 4]);
        $this->assertSame('Hauer', $state['pending']['weapon']['name']);
        $fresh = $this->state('wisaau');
        $fresh = $this->step($fresh, 'action', 1, ['kind' => 'attack', 'weapon' => 'body:1'], [3, 4]);
        $this->assertSame('Biss', $fresh['pending']['weapon']['name']);
    }

    public function test_paralysis_is_adjudicated_resisted_and_expires_in_game_time(): void
    {
        $state = $this->step($this->state('avtar'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Schrei']);
        $this->assertSame('awaiting_ruling', $state['phase']);
        $this->assertSame(0, $state['seconds']);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['resistance_attribute' => 'wi']));
        $state = $this->step($state, 'npc_resistance', 2, [], [1, 3]);
        $this->assertSame('paralyzed', $state['actors'][2]['effects'][0]['type']);
        $this->assertSame(3, $state['seconds']);
        $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
        $this->assertSame(6, $state['seconds']);
        $this->assertEmpty($state['actors'][2]['effects']);
    }

    public function test_successful_resistance_prevents_paralysis(): void
    {
        $state = $this->step($this->state('avtar'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Schrei']);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['resistance_attribute' => 'wi']));
        $state = $this->step($state, 'npc_resistance', 2, [], [6, 6]);
        $this->assertEmpty($state['actors'][2]['effects']);
    }

    public static function invalidRulings(): array
    {
        return ['effect injection' => [['effect' => 'control']], 'too long' => [['duration' => 101]], 'damage injection' => [['damage' => 999]],
            'dice injection' => [['dice' => [6, 6]]], 'negative magnitude' => [['modifier' => -1]], 'unknown attribute' => [['resistance_attribute' => 'foo']], 'no reason' => [['reason' => '']]];
    }

    #[DataProvider('invalidRulings')]
    public function test_unbounded_or_injected_rulings_are_rejected(array $extra): void
    {
        $state = $this->step($this->state('avtar'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Schrei']);
        $this->expectException(InvalidArgumentException::class);
        $this->step($state, 'ruling', 0, $this->ruling($extra));
    }

    public function test_siragippe_acid_uses_server_damage_and_gejagudoo_swallow_triggers_at_four_points(): void
    {
        $state = $this->step($this->state('siragippe'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Säuresekret']);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'acid', 'modifier' => 1]));
        $this->assertSame('npc_acid', $state['pending']['kind']);
        $state = $this->step($state, 'damage', 1, [], [1]);
        $this->assertSame([1], $state['actors'][2]['wounds']);
        $state = $this->step($this->state('gejagudoo'), 'action', 1, ['kind' => 'attack', 'weapon' => 'body:0'], [4, 4]);
        $state = $this->step($state, 'defense', 2, ['defense' => 'dodge'], [1, 3]);
        $this->assertTrue($state['pending']['swallow']);
        $state = $this->step($state, 'damage', 1, [], [1]);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'swallowed', 'duration' => 10]));
        $this->assertSame(1, $state['actors'][2]['swallowed_by']);
        $this->assertSame(1, $state['pending']['damage']);
    }

    public function test_snaekke_acid_occurs_once_per_round_and_escape_clears_the_state(): void
    {
        $state = $this->state('snaekke');
        $state['actors'][2]['swallowed_by'] = 1;
        $state['actors'][2]['effects'][] = ['type' => 'swallowed', 'value' => 0, 'source' => 1, 'expires' => 30];
        $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
        $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
        $this->assertSame('npc_acid', $state['pending']['kind']);
        $this->assertSame(2, $state['pending']['damage']);
        $this->assertEmpty($state['queue']);
        $state = $this->step($state, 'damage', 1, [], [1]);
        $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
        $state = $this->step($state, 'action', 2, ['kind' => 'npc_ability', 'ability' => 'Befreiung']);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'escape']));
        $this->assertArrayNotHasKey('swallowed_by', $state['actors'][2]);
        $this->assertNull($state['pending']);
    }

    public function test_controlled_npc_accepts_player_order_then_leader_action(): void
    {
        $state = $this->state('androne');
        $state['actors'][1]['effects'][] = ['type' => 'control', 'controller' => 2, 'expires' => 100, 'next_resistance' => 3600];
        $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
        $state = $this->step($state, 'action', 2, ['kind' => 'wait']);
        $state = $this->step($state, 'npc_order', 1, ['description' => 'Bleibe stehen.']);
        $task = collect($state['tasks'])->firstWhere('type', 'action');
        $this->assertSame('Bleibe stehen.', $task['context']['order']);
        $this->assertSame(1, $task['side']);
        $this->assertSame(2, $task['controller']);
    }

    public function test_shield_loss_and_rank_increments_change_only_preincluded_components(): void
    {
        $npc = app(NpcCombatSnapshotFactory::class)->build(RpgNpcCatalog::all()['barbarenkrieger'], []);
        $actor = $this->engine->start([$npc, $npc], 100, 100)['state']['actors'][1];
        $actor['shield'] = true;
        $this->assertSame(4, array_sum(CombatStats::defense($actor, 'parry', [], 1, false)));
        $actor['shield_broken'] = true;
        $this->assertSame(3, array_sum(CombatStats::defense($actor, 'parry', [], 1, false)));
        $npc = app(NpcCombatSnapshotFactory::class)->build(RpgNpcCatalog::all()['daamure'], ['rank' => 'Sol', 'profession' => 'Techniker', 'skill_increases' => ['Nahkampf' => 18]]);
        $actor = $this->engine->start([$npc, $npc], 100, 100)['state']['actors'][1];
        $weapon = $actor['weapons']['body:0'];
        $this->assertSame(25, array_sum(CombatStats::attack($actor, $weapon, $weapon['modes'][0], [], 100, [])));
    }

    public function test_aruula_uses_lauschen_as_book_defined_telepathy_with_fixed_pep(): void
    {
        $state = $this->state('aruula');
        $this->assertSame(2, $state['actors'][1]['pep']);
        $this->assertSame(2, CombatStats::power($state['actors'][1], 'Telepathie'));
        $state = $this->step($state, 'action', 1, ['kind' => 'psychic', 'power' => 'Telepathie', 'effect' => 'read', 'duration' => 0, 'range' => 1, 'strength' => 0], [6, 6]);
        $this->assertSame(1, $state['actors'][1]['pep']);
        $state = $this->step($state, 'psychic_resistance', 2, [], [1, 3]);
        $this->assertSame(1, $state['actors'][1]['pep']);
    }

    public function test_snaekke_protection_and_trapped_weapon_require_ruling_and_allow_escape(): void
    {
        $state = $this->state('snaekke');
        $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
        $state['actors'][2]['held'] = ['schwert:1'];
        $state = $this->step($state, 'action', 2, ['kind' => 'attack', 'weapon' => 'schwert:1'], [4, 4]);
        $state = $this->step($state, 'defense', 1, ['defense' => 'dodge'], [1, 3]);
        $state = $this->step($state, 'damage', 2);
        $this->assertSame('awaiting_ruling', $state['phase']);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'protection', 'modifier' => 2, 'entangle' => true]), [1]);
        $this->assertTrue($state['actors'][2]['weapons']['schwert:1']['stuck']);
        $this->assertSame(1, $state['actors'][2]['held_by']);
        $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
        $state = $this->step($state, 'action', 2, ['kind' => 'npc_ability', 'ability' => 'Befreiung']);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'escape']));
        $this->assertArrayNotHasKey('held_by', $state['actors'][2]);
        $this->assertArrayNotHasKey('stuck', $state['actors'][2]['weapons']['schwert:1']);
    }

    public function test_snaekke_fire_vulnerability_automatically_ignores_ro(): void
    {
        $state = $this->state('snaekke');
        $state['actors'][1]['effects'][] = ['type' => 'protection', 'value' => 6, 'scope' => 'ordinary_weapons', 'expires' => 30];
        $state['queue'][] = ['kind' => 'burn', 'side' => 2, 'target' => 1, 'damage' => 0];
        $state = $this->step($state, 'action', 1, ['kind' => 'wait']);
        $state = $this->step($state, 'damage', 2, [], [2]);
        $this->assertSame([1], $state['actors'][1]['wounds']);
    }

    public function test_swallowed_character_moves_with_creature_and_can_escape_at_new_position(): void
    {
        $state = $this->state('snaekke');
        $state['actors'][2]['swallowed_by'] = 1;
        $state['actors'][2]['effects'][] = ['type' => 'swallowed', 'value' => 0, 'source' => 1, 'expires' => 30];
        $state = $this->step($state, 'action', 1, ['kind' => 'run', 'move' => 500]);
        $this->assertSame(500, $state['actors'][1]['position']);
        $this->assertSame(500, $state['actors'][2]['position']);
        $state = $this->step($state, 'action', 2, ['kind' => 'npc_ability', 'ability' => 'Befreiung']);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'escape', 'range' => 0]));
        $this->assertArrayNotHasKey('swallowed_by', $state['actors'][2]);
        $this->assertSame(500, $state['actors'][2]['position']);
    }

    public function test_jump_distance_outside_published_limits_is_rejected(): void
    {
        $state = $this->step($this->state('frekkeuscher'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Sprung (20–30 m)']);
        $this->expectException(InvalidArgumentException::class);
        $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'movement', 'displacement' => 100]));
    }

    public function test_frekkeuscher_jump_moves_the_creature_by_the_adjudicated_book_distance(): void
    {
        $state = $this->step($this->state('frekkeuscher'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Sprung (20–30 m)']);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'movement', 'displacement' => 2500, 'range' => 0]));
        $this->assertSame(2500, $state['actors'][1]['position']);
        $this->assertSame(100, $state['actors'][2]['position']);
        $this->assertSame(2, $this->dice->calls);
    }

    public static function movesIntoAbilityRange(): array
    {
        return [
            'sequential NPC approach' => [false, 400, 0, false],
            'simultaneous NPC approach' => [true, 400, 0, false],
            'simultaneous opponent approach' => [true, 0, -400, false],
            'simultaneous joint approach' => [true, 200, -200, false],
            'opponent declares approach first' => [true, 0, -400, true],
        ];
    }

    #[DataProvider('movesIntoAbilityRange')]
    public function test_npc_ability_uses_final_positions_after_declared_movements(bool $tied, int $npcMove, int $opponentMove, bool $opponentFirst): void
    {
        $state = $this->state('avtar', 500, tied: $tied);
        $this->assertCount($tied ? 2 : 1, $state['group']);
        $opponentAction = ['kind' => $opponentMove === 0 ? 'wait' : 'move', 'move' => $opponentMove];
        if ($opponentFirst) {
            $state = $this->step($state, 'action', 2, $opponentAction);
        }
        $state = $this->step($state, 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Schrei', 'move' => $npcMove]);
        $this->assertSame(0, $state['actors'][1]['position']);
        $this->assertSame(500, $state['actors'][2]['position']);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['range' => 100, 'resistance_attribute' => 'wi']));
        if ($tied && ! $opponentFirst) {
            $this->assertSame(0, $state['actors'][1]['position']);
            $this->assertSame(500, $state['actors'][2]['position']);
            $this->assertNull($state['pending_npc_effect'] ?? null);
            $state = $this->step($state, 'action', 2, $opponentAction);
        }
        $this->assertSame($npcMove, $state['actors'][1]['position']);
        $this->assertSame(500 + $opponentMove, $state['actors'][2]['position']);
        $this->assertSame(100, abs($state['actors'][1]['position'] - $state['actors'][2]['position']));
        $this->assertSame(2, $this->dice->calls);
        $state = $this->step($state, 'npc_resistance', 2, [], [1, 3]);
        $this->assertSame('paralyzed', $state['actors'][2]['effects'][0]['type']);
        $this->assertSame(4, $this->dice->calls);
    }

    public static function movesOutsideAbilityRange(): array
    {
        return [
            'stationary out of range' => [false, 0, 0],
            'insufficient NPC approach' => [false, 300, 0],
            'simultaneous opponent retreat' => [true, 400, 100],
        ];
    }

    #[DataProvider('movesOutsideAbilityRange')]
    public function test_npc_ability_outside_final_range_has_no_effect_or_resistance_roll(bool $tied, int $npcMove, int $opponentMove): void
    {
        $state = $this->state('avtar', 500, tied: $tied);
        $state = $this->step($state, 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Schrei', 'move' => $npcMove]);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['range' => 100, 'resistance_attribute' => 'wi']));
        if ($tied) {
            $state = $this->step($state, 'action', 2, ['kind' => 'move', 'move' => $opponentMove]);
        }
        $this->assertSame($npcMove, $state['actors'][1]['position']);
        $this->assertSame(500 + $opponentMove, $state['actors'][2]['position']);
        $this->assertGreaterThan(100, abs($state['actors'][1]['position'] - $state['actors'][2]['position']));
        $this->assertEmpty($state['actors'][2]['effects']);
        $this->assertNull($state['pending_npc_effect'] ?? null);
        $this->assertNotContains('npc_resistance', array_column($state['tasks'], 'type'));
        $this->assertSame(2, $this->dice->calls);
    }

    public function test_simultaneous_movement_can_escape_the_adjudicated_ability_range_without_resistance(): void
    {
        $state = $this->state('avtar', 100, tied: true);
        $state = $this->step($state, 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Schrei']);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['range' => 100, 'resistance_attribute' => 'wi']));
        $state = $this->step($state, 'action', 2, ['kind' => 'move', 'move' => 400]);
        $this->assertSame(500, $state['actors'][2]['position']);
        $this->assertEmpty($state['actors'][2]['effects']);
        $this->assertNull($state['pending_npc_effect'] ?? null);
        $this->assertSame(2, $this->dice->calls);
    }

    public function test_active_snaekke_protection_applies_to_weapons_and_expires_in_game_time(): void
    {
        $state = $this->step($this->state('snaekke'), 'action', 1, ['kind' => 'npc_ability', 'ability' => 'Besonderer Schutz gegen gewöhnliche Waffen']);
        $state = $this->step($state, 'ruling', 0, $this->ruling(['effect' => 'protection', 'modifier' => 4, 'duration' => 1]));
        $this->assertSame(4, CombatStats::protection($state['actors'][1]));
        $this->assertSame(0, CombatStats::protection($state['actors'][1], false));
        $state['actors'][2]['held'] = ['schwert:1'];
        $state = $this->step($state, 'action', 2, ['kind' => 'attack', 'weapon' => 'schwert:1'], [4, 4]);
        $state = $this->step($state, 'defense', 1, ['defense' => 'dodge'], [1, 3]);
        $state = $this->step($state, 'damage', 2, [], [1]);
        $this->assertEmpty($state['actors'][1]['wounds']);
        $this->assertSame(3, $state['seconds']);
        $this->assertSame(0, CombatStats::protection($state['actors'][1]));
    }
}
