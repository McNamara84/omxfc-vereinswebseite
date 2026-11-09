<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RpgCombat\CombatEngine;
use App\Services\RpgCombat\NpcCombatSnapshotFactory;
use App\Support\RpgCombatRules;
use App\Support\RpgNpcCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CombatTestDice;

trait RpgNpcEngineTestFixtures
{
    use RefreshDatabase;

    private CombatTestDice $dice;

    private CombatEngine $engine;

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
        expect($task)->not->toBeNull();

        return $this->engine->decide($state, $task['token'], $input)['state'];
    }

    private function ruling(array $extra = []): array
    {
        return array_replace(['effect' => 'paralyzed', 'duration' => 2, 'modifier' => 2, 'displacement' => 0, 'range' => 1000, 'resistance_attribute' => 'none', 'difficulty' => 6, 'reason' => 'Die ergänzende Regel gilt für diese Fähigkeit.'], $extra);
    }
}
