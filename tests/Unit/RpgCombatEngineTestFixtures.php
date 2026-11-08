<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCombat\CombatEngine;
use App\Support\RpgCombatRules;
use Tests\Support\CombatTestDice;

trait RpgCombatEngineTestFixtures
{
    private CombatEngine $engine;

    private CombatTestDice $dice;

    private function profile(string $weapon = 'sword', bool $ranged = false): array
    {
        $mode = ['kind' => $ranged ? 'ranged' : 'melee', 'skill' => $ranged ? 'Feuerwaffen' : 'Nahkampf', 'attributes' => $ranged ? ['wa'] : ['st', 'ge'], 'damage' => 1, 'precision' => 1, 'type' => $ranged ? 'Schießpulverwaffe' : '', 'fireRate' => 'A', 'rangeIncrement' => '10m', 'maxRange' => '100m', 'magazine' => 40];
        $w = ['instance' => $weapon, 'id' => $weapon, 'name' => $weapon, 'modes' => [$mode], 'hands' => 1, 'size' => 'medium', 'education' => 0, 'capacity' => $ranged ? 40 : 0, 'loaded' => $ranged ? 40 : 0, 'reserve' => $ranged ? 120 : 0, 'thrown' => false, 'natural' => false, 'jammed' => false, 'broken' => false, 'position' => null, 'fuel' => null, 'progress' => 0];

        return ['name' => 'Figur', 'revision' => 1, 'attributes' => array_fill_keys(['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'], 0), 'skills' => ['Nahkampf' => 3, 'Athletik' => 2, 'Feuerwaffen' => 3, 'Bildung' => 3], 'advantages' => [], 'disadvantages' => [], 'advantage_counts' => [], 'disadvantage_details' => [], 'weapons' => [$weapon => $w], 'items' => ['heilgel' => 1], 'armor' => null, 'shield' => null, 'psychic' => ['pep' => 100, 'powers' => []], 'sources' => []];
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
        $task = array_first(array_filter($state['tasks'], fn ($t) => $t['type'] === $type && $t['side'] === $side)) ?? null;
        expect($task)->not->toBeNull();

        return $this->engine->decide($state, $task['token'], $input)['state'];
    }

    private function psychicState(string $power): array
    {
        $state = $this->state();
        $state['actors'][1]['profile']['psychic']['powers'] = [['name' => $power, 'value' => 4, 'usable' => true]];

        return $state;
    }
}
