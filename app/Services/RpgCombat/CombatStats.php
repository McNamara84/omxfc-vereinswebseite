<?php

namespace App\Services\RpgCombat;

use InvalidArgumentException;

final class CombatStats
{
    public static function advantage(array $actor, string $name): int
    {
        return in_array($name, $actor['profile']['advantages'], true) ? max(1, $actor['profile']['advantage_counts'][$name] ?? 1) : 0;
    }

    public static function disadvantage(array $actor, string $name): bool
    {
        return in_array($name, $actor['profile']['disadvantages'], true);
    }

    public static function skill(array $actor, string $name): int
    {
        return $actor['profile']['skills'][$name] ?? 0;
    }

    public static function attribute(array $actor, string $key): int
    {
        return $actor['profile']['attributes'][$key];
    }

    public static function vm(array $actor, array $rulings): int
    {
        return CombatMath::injuryModifier($actor['wounds'], $rulings['wounds'] ?? 'persistent');
    }

    public static function movement(array $actor): int
    {
        return 100 * max(0, 4 + self::attribute($actor, 'ge') + (self::advantage($actor, 'Schnell') ? 2 : 0));
    }

    public static function armor(array $actor): int
    {
        return $actor['armor_broken'] ? 0 : (int) ($actor['profile']['armor']['protection'] ?? 0);
    }

    public static function bm(array $actor): int
    {
        return $actor['armor_broken'] ? 0 : (int) ($actor['profile']['armor']['movementModifier'] ?? 0);
    }

    public static function protection(array $actor): int
    {
        return self::armor($actor) + self::advantage($actor, 'Panzerung') + (self::advantage($actor, 'Zäh') ? 1 : 0);
    }

    public static function weapon(array $actor, string $id, bool $held = true): array
    {
        $weapon = $actor['weapons'][$id] ?? throw new InvalidArgumentException('Diese Waffe ist nicht vorhanden.');
        if ($weapon['broken'] || $weapon['position'] !== null || ($held && ! $weapon['natural'] && ! in_array($id, $actor['held'], true))) {
            throw new InvalidArgumentException('Diese Waffe kann gerade nicht eingesetzt werden.');
        }
        if (self::disadvantage($actor, 'Primitiv') && $weapon['education'] > 0) {
            throw new InvalidArgumentException('Primitiv verhindert den Einsatz technischer Waffen.');
        }
        if ($weapon['education'] - self::skill($actor, 'Bildung') > 2 && ! ($actor['technology'][$id] ?? false)) {
            throw new InvalidArgumentException('Für diese Waffe fehlt eine erfolgreiche Benutzungsprobe.');
        }

        return $weapon;
    }

    public static function mode(array $weapon, int $index): array
    {
        return $weapon['modes'][$index] ?? throw new InvalidArgumentException('Unzulässige Angriffsart.');
    }

    public static function attack(array $actor, array $weapon, array $mode, array $rulings, int $distance, array $input): array
    {
        $attribute = $input['attribute'] ?? collect($mode['attributes'])->sortByDesc(fn ($a) => self::attribute($actor, $a))->first();
        if (! in_array($attribute, $mode['attributes'], true)) {
            throw new InvalidArgumentException('Dieses Attribut passt nicht zur Waffe.');
        }
        $ranged = $mode['kind'] === 'ranged';
        $modifiers = [
            $mode['skill'] => self::skill($actor, $mode['skill']), strtoupper($attribute) => self::attribute($actor, $attribute),
            'Verletzungen' => -self::vm($actor, $rulings),
            'Rüstung' => ! $ranged && $attribute === 'ge' ? self::bm($actor) : 0,
            'Bodenlage' => ! $ranged && $actor['prone'] && ($rulings['prone'] ?? 'penalty') === 'penalty' ? -2 : 0,
            'Zwei Waffen' => count($actor['held']) === 2 ? -2 : 0,
            'Mindestbildung' => -max(0, $weapon['education'] - self::skill($actor, 'Bildung')),
        ];
        if ($ranged) {
            $modifiers['Präzision'] = $mode['precision'];
            $modifiers['Entfernung'] = CombatMath::rangePenalty($distance, (int) $mode['rangeIncrement'] * 100, (int) $mode['maxRange'] * 100);
            $modifiers['Scharfschütze'] = self::advantage($actor, 'Scharfschütze') ? 1 : 0;
            $modifiers['Feuerart'] = CombatMath::fireMode($input['fire'] ?? 'E', $mode['fireRate'])['attack'];
        } elseif ($distance > 100) {
            throw new InvalidArgumentException('Das Ziel ist nicht in Nahkampfreichweite.');
        }
        $aim = $input['aim'] ?? 0;
        if (! is_int($aim) || $aim < 0 || $aim > CombatMath::aimedLimit(array_sum($modifiers))) {
            throw new InvalidArgumentException('Unzulässiger gezielter Schlag.');
        }
        $modifiers['Gezielter Schlag'] = -2 * $aim;

        return $modifiers;
    }

    public static function defense(array $actor, string $kind, array $rulings, int $round, bool $ranged, bool $knockdown = false): array
    {
        if (! in_array($kind, ['parry', 'dodge'], true) || ($kind === 'parry' && ($ranged || $actor['parried'] || $actor['blocked_until'] >= $round))) {
            throw new InvalidArgumentException('Diese Verteidigung ist nicht möglich.');
        }
        $skill = $kind === 'parry' ? 'Nahkampf' : 'Athletik';

        return [$skill => self::skill($actor, $skill), 'GE' => self::attribute($actor, 'ge'),
            'Verletzungen' => -self::vm($actor, $rulings), 'Rüstung' => self::bm($actor),
            'Schild' => $actor['shield'] && ! $actor['shield_broken'] ? 1 : 0,
            'Kaltblütig' => self::advantage($actor, 'Kaltblütig') ? 1 : 0,
            'Kampfreflexe' => $kind === 'dodge' && self::advantage($actor, 'Kampfreflexe') ? 2 : 0,
            'Wiederholtes Ausweichen' => $kind === 'dodge' ? -$actor['dodges'] : 0,
            'Volle Verteidigung' => $actor['full_defense'] ? 2 : 0,
            'Bodenlage / Patzer' => $actor['blocked_until'] >= $round || ($actor['prone'] && ($rulings['prone'] ?? 'penalty') === 'penalty') ? -2 : 0,
            'Niederwerfen' => $knockdown ? -2 : 0];
    }

    public static function damage(array $attacker, array $defender, array $attack, array $rulings): array
    {
        $mode = $attack['mode'];
        $ranged = $mode['kind'] === 'ranged';
        $natural = $attack['weapon']['id'] === 'natural';
        $weaponDamage = $natural && ($rulings['natural_weapons'] ?? 'weapon') === 'bonus' ? 0 : $mode['damage'];
        $attribute = $ranged ? 'wa' : 'st';

        return [strtoupper($attribute) => self::attribute($attacker, $attribute), 'Waffe' => $weaponDamage,
            'Gezielter Schlag' => $attack['input']['aim'] ?? 0,
            'Feuerart' => $ranged ? CombatMath::fireMode($attack['input']['fire'] ?? 'E', $mode['fireRate'])['damage'] : 0,
            'Kritischer Treffer' => $attack['critical'] ?? 0,
            'Scharfschütze' => $ranged && self::advantage($attacker, 'Scharfschütze') && $attack['distance'] <= (int) $mode['rangeIncrement'] * 100 ? 1 : 0,
            'Verletzungen des Ziels' => self::vm($defender, $rulings),
            'Taratzenfutter' => self::disadvantage($defender, 'Taratzenfutter') ? 1 : 0,
            'RO des Ziels' => ($attack['vulnerable'] ?? false) ? 0 : -self::attribute($defender, 'ro'),
            'Schutz des Ziels' => -self::protection($defender)];
    }

    public static function psychicDefense(array $actor, array $rulings): array
    {
        $shield = self::power($actor, 'Gedankenschild');
        foreach ($actor['effects'] as $effect) {
            if ($effect['type'] === 'shield') {
                $shield = max($shield, $effect['value']);
            }
        }

        return ['WI' => self::attribute($actor, 'wi') * (($rulings['psychic_resistance'] ?? 'triple') === 'triple' ? 3 : 1),
            'Gedankenschild' => $shield,
            'Verletzungen' => ($rulings['modifiers'] ?? 'physical') === 'concentration' ? -self::vm($actor, $rulings) : 0];
    }

    public static function power(array $actor, string $name): int
    {
        foreach ($actor['profile']['psychic']['powers'] as $power) {
            if ($power['name'] === $name && $power['usable']) {
                return $power['value'];
            }
        }

        return 0;
    }
}
