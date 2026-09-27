<?php

namespace App\Services\RpgCombat;

use App\Support\RpgCombatRules;
use InvalidArgumentException;

final class AutomaticDecision
{
    public function input(array $state, array $task): array
    {
        $side = $task['side'];
        $actor = $state['actors'][$side] ?? null;
        if ($task['type'] === 'ruling') {
            return ['choices' => array_combine($task['context']['rules'], array_map(RpgCombatRules::defaultRuling(...), $task['context']['rules'])),
                'reason' => '24-Stunden-Frist abgelaufen: veröffentlichte Standardauslegung angewandt.'];
        }
        if ($task['type'] === 'prepare') {
            $weapons = collect($actor['weapons'])->filter(fn ($w) => ! $w['natural'] && (! CombatStats::disadvantage($actor, 'Primitiv') || $w['education'] === 0))
                ->sortByDesc(fn ($w) => CombatStats::skill($actor, $w['modes'][0]['skill']) + $w['modes'][0]['damage']);
            $weapon = $weapons->first();

            return ['weapons' => $weapon ? [$weapon['instance']] : [], 'shield' => $actor['profile']['shield'] !== null && (! $weapon || $weapon['hands'] === 1),
                'skill' => $weapon['modes'][0]['skill'] ?? 'Nahkampf'];
        }
        if ($task['type'] === 'defense') {
            $options = [];
            foreach (['dodge', 'parry'] as $kind) {
                try {
                    $options[$kind] = array_sum(CombatStats::defense($actor, $kind, $state['rules'], $state['round'], $state['pending']['mode']['kind'] === 'ranged', $state['pending']['kind'] === 'knockdown'));
                } catch (InvalidArgumentException) {
                }
            }
            arsort($options);

            return ['defense' => array_key_first($options), 'full_defense' => false];
        }
        if ($task['type'] !== 'action') {
            return [];
        }
        if ($actor['prone']) {
            return ['kind' => 'stand'];
        }
        if ($actor['full_defense']) {
            return ['kind' => 'wait'];
        }
        $distance = abs($actor['position'] - $state['actors'][3 - $side]['position']);
        $direction = $actor['position'] < $state['actors'][3 - $side]['position'] ? 1 : -1;
        $candidates = [];
        foreach ($actor['weapons'] as $id => $weapon) {
            try {
                CombatStats::weapon($actor, $id);
                if ($weapon['jammed']) {
                    return ['kind' => 'unjam', 'weapon' => $id];
                }
                if ($weapon['fuel'] !== null && $weapon['fuel'] <= 0) {
                    continue;
                }
                foreach ($weapon['modes'] as $index => $mode) {
                    $input = ['kind' => 'attack', 'weapon' => $id, 'mode' => $index, 'aim' => 0, 'fire' => 'E'];
                    $reach = $mode['kind'] === 'melee' ? 100 : (int) $mode['maxRange'] * 100;
                    $input['move'] = min(CombatStats::movement($actor), max(0, $distance - $reach)) * $direction;
                    if ($mode['kind'] === 'ranged' && ! $weapon['thrown']) {
                        if ($weapon['capacity'] > 0 && $weapon['loaded'] === 0 && $weapon['reserve'] > 0) {
                            return ['kind' => 'reload', 'weapon' => $id];
                        }
                        if (($weapon['capacity'] > 0 ? $weapon['loaded'] : $weapon['reserve']) === 0) {
                            continue;
                        }
                    }
                    try {
                        $score = array_sum(CombatStats::attack($actor, $weapon, $mode, $state['rules'], $distance - abs($input['move']), $input)) + $mode['damage'];
                        $candidates[] = compact('score', 'input');
                    } catch (InvalidArgumentException) {
                    }
                }
            } catch (InvalidArgumentException) {
            }
        }
        if ($candidates !== []) {
            usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

            return $candidates[0]['input'];
        }

        return ['kind' => 'move', 'move' => $direction * min(CombatStats::movement($actor), max(0, $distance - 100))];
    }
}
