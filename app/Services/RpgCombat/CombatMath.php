<?php

namespace App\Services\RpgCombat;

use InvalidArgumentException;

/** Pure calculations. Page numbers refer to the 2007 rulebook. */
final class CombatMath
{
    public const FIRE_MODES = [
        'E' => ['ammunition' => 1, 'attack' => 0, 'damage' => 0, 'area' => 0],
        'H' => ['ammunition' => 2, 'attack' => -1, 'damage' => 0, 'area' => 1],
        'S' => ['ammunition' => 6, 'attack' => -2, 'damage' => 0, 'area' => 3],
        'A' => ['ammunition' => 15, 'attack' => -4, 'damage' => 1, 'area' => 6],
    ];

    public static function roll(array $dice, array $modifiers): array
    {
        if (! in_array(count($dice), [1, 2], true) || array_filter($dice, fn ($d) => ! is_int($d) || $d < 1 || $d > 6)) {
            throw new InvalidArgumentException('Ungültiger W6-Wurf.');
        }
        if (array_filter($modifiers, fn ($v) => ! is_int($v))) {
            throw new InvalidArgumentException('Modifikatoren müssen ganzzahlig sein.');
        }

        return ['dice' => array_values($dice), 'raw' => array_sum($dice), 'modifiers' => $modifiers,
            'total' => array_sum($dice) + array_sum($modifiers)];
    }

    public static function result(array $roll, int $difficulty): array
    {
        $margin = $roll['total'] - $difficulty;

        return $roll + ['difficulty' => $difficulty, 'margin' => $margin, 'success' => $margin >= 0,
            'critical' => count($roll['dice']) === 2 && $roll['raw'] === 12 && $margin >= 0,
            'fumble' => count($roll['dice']) === 2 && $roll['raw'] === 2 && $margin < 0];
    }

    /** S. 47; centimetres avoid floating-point boundary errors. */
    public static function rangePenalty(int $distance, int $increment, int $maximum): int
    {
        if ($distance < 0 || $increment < 1 || $distance > $maximum) {
            throw new InvalidArgumentException('Ziel außerhalb der Reichweite.');
        }

        return -max(0, intdiv($distance + $increment - 1, $increment) - 1);
    }

    public static function fireMode(string $mode, string $maximum): array
    {
        $modes = array_keys(self::FIRE_MODES);
        if (! in_array($mode, $modes, true) || ! in_array($maximum, $modes, true)
            || array_search($mode, $modes, true) > array_search($maximum, $modes, true)) {
            throw new InvalidArgumentException('Diese Feuerart ist nicht verfügbar.');
        }

        return self::FIRE_MODES[$mode];
    }

    public static function aimedLimit(int $attackModifier): int
    {
        return max(0, intdiv($attackModifier + 1, 2));
    }

    /** S. 49: one bonus, even when both conditions apply. */
    public static function criticalDamage(array $attack, int $defense): int
    {
        return $attack['total'] > $defense && ($attack['raw'] === 12 || $attack['total'] - $defense >= 4) ? 1 : 0;
    }

    public static function wound(int $damage): int
    {
        return match (true) {
            $damage <= 0 => 0,
            $damage <= 2 => 1,
            $damage <= 4 => 2,
            $damage <= 6 => 3,
            default => 4,
        };
    }

    /** S. 47–48, SL-06: middle wounds persist, or reset at a direct severe wound. */
    public static function injuryModifier(array $wounds, string $method = 'persistent'): int
    {
        $medium = 0;
        $severe = 0;
        foreach ($wounds as $wound) {
            if ($wound === 2) {
                $medium++;
                if ($medium === 2) {
                    $severe++;
                    $medium = 0;
                }
            } elseif ($wound >= 3) {
                $severe++;
                if ($method === 'reset') {
                    $medium = 0;
                }
            }
        }

        return $severe > 0 ? 1 + $severe : ($medium > 0 ? 1 : 0);
    }

    public static function heal(array $wounds): array
    {
        if ($wounds === []) {
            return [];
        }
        $max = max($wounds);
        $index = array_key_last(array_filter($wounds, fn ($w) => $w === $max));
        $wounds[$index]--;

        return array_values(array_filter($wounds, fn ($w) => $w > 0));
    }

    public static function psychicParameters(int $skill, int $duration, int $range, int $strength): array
    {
        if ($skill < 1 || min($duration, $range, $strength) < 0 || max($duration, $range, $strength) > 12
            || $duration + $range + $strength > $skill * 2) {
            throw new InvalidArgumentException('Ungültige psychische Parameter.');
        }
        $durations = [0, 10, 60, 360, 1800, 10800];
        $ranges = [0, 2, 10, 50, 250, 1000];
        $strengths = [1, 5, 10, 20, 40, 80];

        return [
            'points' => ['duration' => $duration, 'range' => $range, 'strength' => $strength],
            'duration' => $durations[min(5, $duration)] * (6 ** max(0, $duration - 5)),
            'range' => 100 * $ranges[min(5, $range)] * (5 ** max(0, $range - 5)),
            'strength' => $strengths[min(5, $strength)] * (2 ** max(0, $strength - 5)),
            'cost' => $duration + $range + $strength,
        ];
    }

    public static function pyrokineticCost(array $parameters, int $damage, int $skill): int
    {
        if ($damage < 0 || $damage > intdiv($skill, 2)) {
            throw new InvalidArgumentException('Ungültiger Pyrokinese-Schaden.');
        }

        return ($parameters['cost'] + $damage + 2) * 3;
    }
}
