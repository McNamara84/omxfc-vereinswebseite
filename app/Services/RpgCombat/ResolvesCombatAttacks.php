<?php

namespace App\Services\RpgCombat;

use App\Support\RpgCombatRules;
use InvalidArgumentException;

trait ResolvesCombatAttacks
{
    private function queueAttack(array &$state, int $side, array $input): void
    {
        try {
            $actor = $state['actors'][$side];
            $weapon = CombatStats::weapon($actor, $input['weapon']);
            $mode = CombatStats::mode($weapon, $input['mode'] ?? 0);
            $distance = abs($actor['position'] - $state['actors'][3 - $side]['position']);
            $modifiers = CombatStats::attack($actor, $weapon, $mode, $state['rules'], $distance, $input);
            if ($input['kind'] === 'disarm') {
                $modifiers = [$mode['skill'] => CombatStats::skill($actor, $mode['skill']), 'GE' => CombatStats::attribute($actor, 'ge'),
                    'Verletzungen' => -CombatStats::vm($actor, $state['rules']), 'Rüstung' => CombatStats::bm($actor)];
            }
            $cost = $mode['kind'] === 'ranged' ? CombatMath::fireMode($input['fire'] ?? 'E', $mode['fireRate'])['ammunition'] : 0;
            if ($cost && ! $weapon['thrown']) {
                $field = $weapon['capacity'] > 0 ? 'loaded' : 'reserve';
                $this->ensure($weapon[$field] >= $cost, 'Nicht genügend Munition für den Folgeangriff.');
                $state['actors'][$side]['weapons'][$input['weapon']][$field] -= $cost;
                $this->event($state, 'ammunition', $side, ['message' => 'Munition verbraucht.', 'weapon' => $weapon['name'], 'amount' => $cost,
                    'remaining' => $state['actors'][$side]['weapons'][$input['weapon']][$field], 'pages' => '48–49']);
            }
            if ($weapon['thrown'] && $mode['kind'] === 'ranged') {
                $state['actors'][$side]['weapons'][$input['weapon']]['position'] = $state['actors'][3 - $side]['position'];
                $state['actors'][$side]['held'] = array_values(array_diff($actor['held'], [$input['weapon']]));
            }
            foreach ($actor['effects'] as $effect) {
                if ($effect['type'] === 'creative' && $effect['expires'] > $state['seconds']) {
                    $modifiers['Kreative Vorbereitung'] = $effect['value'];
                }
            }
            $state['queue'][] = ['kind' => $input['kind'], 'side' => $side, 'target' => 3 - $side,
                'weapon' => $weapon, 'mode' => $mode, 'input' => $input, 'distance' => $distance,
                'modifiers' => $modifiers, 'cost' => $cost];
        } catch (InvalidArgumentException $exception) {
            // A valid declaration can lose range through the opponent's simultaneous move.
            $this->event($state, 'attack_unavailable', $side, ['message' => $exception->getMessage().' Der angesagte Angriff entfällt.', 'pages' => '46–47']);
        }
    }

    private function nextAttack(array &$state): void
    {
        if ($state['tasks'] !== [] || $state['continuation'] !== null) {
            return;
        }
        if ($state['version'] === RpgCombatRules::NPC_VERSION && count($state['group']) === 1 && $this->ended($state)) {
            return;
        }
        if ($state['queue'] === []) {
            $state['pending'] = null;
            if ($state['phase'] === 'round_effects') {
                $this->completeRound($state);
            } else {
                $this->nextGroup($state);
            }

            return;
        }
        $attack = array_shift($state['queue']);
        $state['pending'] = $attack;
        if ($attack['kind'] === 'npc_ability') {
            $state['pending'] = null;
            $this->resolveNpcEffect($state, $attack['interpretation']);

            return;
        }
        if ($attack['kind'] === 'psychic') {
            $this->psychicAttack($state, $attack);

            return;
        }
        if (in_array($attack['kind'], ['burn', 'psychic_damage', 'npc_acid'], true)) {
            $this->task($state, 'damage', $attack['side']);

            return;
        }
        $attack['roll'] = $this->roll($state, $attack['side'], $attack['modifiers'], 'Angriffsprobe: '.$attack['weapon']['name'], '46–49');
        $state['pending'] = $attack;
        if ($attack['kind'] === 'disarm') {
            $this->task($state, 'resistance', $attack['target']);
        } else {
            $this->task($state, 'defense', $attack['target']);
        }
    }

    private function defend(array &$state, array $task, array $input): void
    {
        $this->keys($input, ['defense', 'full_defense']);
        $this->ensure(is_string($input['defense'] ?? null) && is_bool($input['full_defense'] ?? false), 'Verteidigung auswählen.');
        $side = $task['side'];
        $actor = $this->reactionActor($state, $side);
        $attack = $state['pending'];
        $rules = [];
        if ($input['full_defense'] ?? false) {
            $this->ensure(! $actor['has_attacked'] && $actor['blocked_until'] < $state['round'], 'Volle Verteidigung ist nach eigener Attacke oder während einer Handlungssperre nicht möglich.');
            $rules[] = 'full_defense';
        }
        if ($actor['prone']) {
            $rules[] = 'prone';
        }
        if ($actor['wounds'] !== [] || CombatStats::bm($actor) !== 0) {
            $rules[] = 'modifiers';
        }
        CombatStats::defense($actor, $input['defense'], $state['rules'], $state['round'], $attack['mode']['kind'] === 'ranged', $attack['kind'] === 'knockdown');
        if ($this->defer($state, $rules, $task, $input)) {
            return;
        }
        if ($input['full_defense'] ?? false) {
            $this->ensure(($state['rules']['full_defense'] ?? 'reactive') === 'reactive' || $actor['full_defense'], 'Die Leitung erlaubt volle Verteidigung nur bei eigener Aktionswahl.');
            $state['actors'][$side]['full_defense'] = $actor['full_defense'] = true;
        }
        $modifiers = CombatStats::defense($actor, $input['defense'], $state['rules'], $state['round'], $attack['mode']['kind'] === 'ranged', $attack['kind'] === 'knockdown');
        $defense = CombatMath::result($this->roll($state, $side, $modifiers, $input['defense'] === 'parry' ? 'Parade' : 'Ausweichen', '46–47'), $attack['roll']['total']);
        if ($input['defense'] === 'parry') {
            $state['actors'][$side]['parried'] = true;
        } else {
            $state['actors'][$side]['dodges']++;
        }
        $this->event($state, 'defense', $side, $defense + ['message' => $defense['success'] ? 'Angriff abgewehrt.' : 'Verteidigung misslungen.', 'pages' => '47']);
        if ($defense['success']) {
            if ($attack['roll']['raw'] === 2) {
                $this->attackFumble($state);
            } else {
                $this->finishAttack($state);
            }

            return;
        }
        if ($defense['fumble']) {
            $state['actors'][$side]['prone'] = true;
            $state['actors'][$side]['blocked_until'] = $state['round'] + 1;
            $this->event($state, 'defense_fumble', $side, ['message' => 'Verteidigungspatzer: Handlungen dieser und nächster Runde entfallen; nur Ausweichen mit zusätzlich −2.', 'pages' => '48']);
        }
        $state['pending']['critical'] = CombatMath::criticalDamage($attack['roll'], $defense['total']);
        $state['pending']['swallow'] = ($attack['mode']['swallow'] ?? false) && $attack['roll']['total'] - $defense['total'] >= 4;
        if ($attack['kind'] === 'psychic') {
            $this->finishPsychic($state, true);

            return;
        }
        if ($attack['kind'] === 'knockdown') {
            $this->task($state, 'strength', $side);
        } else {
            $this->task($state, 'damage', $attack['side']);
        }
    }

    private function attackFumble(array &$state): void
    {
        $attack = $state['pending'];
        $side = $attack['side'];
        $state['pending']['not_fired'] = true;
        if ($attack['kind'] === 'psychic') {
            $this->finishPsychic($state, false);

            return;
        }
        if ($attack['mode']['kind'] === 'melee') {
            $this->task($state, 'fumble', $side);

            return;
        }
        $weapon = &$state['actors'][$side]['weapons'][$attack['input']['weapon']];
        $type = $attack['mode']['type'];
        $chance = $this->roll($state, $side, [], 'Folge des Fernkampfpatzers', '48', 1)['total'];
        if ($type === 'Schießpulverwaffe' && $chance <= 2) {
            $weapon['jammed'] = true;
        }
        if (in_array($weapon['id'], ['bogen', 'armbrust'], true) && $chance === 1) {
            $weapon['broken'] = true;
        }
        if (! $weapon['thrown']) {
            $weapon[$weapon['capacity'] > 0 ? 'loaded' : 'reserve'] += $attack['cost'];
        } else {
            $weapon['position'] = null;
            $state['actors'][$side]['held'][] = $weapon['instance'];
        }
        $this->event($state, 'attack_fumble', $side, ['message' => $weapon['broken'] ? 'Sehne gerissen; die Waffe ist unbrauchbar.'
            : ($weapon['jammed'] ? 'Ladehemmung.' : 'Schuss nicht ausgelöst.'), 'pages' => '48 / SL-09']);
        unset($weapon);
        $this->finishAttack($state);
    }

    private function fumble(array &$state, array $task, array $input): void
    {
        $this->keys($input, []);
        if ($this->defer($state, ['prone'], $task, $input)) {
            return;
        }
        $side = $task['side'];
        $actor = $state['actors'][$side];
        $result = CombatMath::result($this->roll($state, $side, ['GE × 3' => 3 * CombatStats::attribute($actor, 'ge'),
            'Verletzungen' => -CombatStats::vm($actor, $state['rules'])], 'Patzer abfangen', '28, 48'), 11);
        if (! $result['success']) {
            $weapon = $state['pending']['weapon'];
            if ($weapon['natural']) {
                $state['actors'][$side]['prone'] = true;
            } else {
                $this->drop($state, $side, $weapon['instance']);
            }
        }
        $this->event($state, 'fumble_result', $side, $result + ['message' => $result['success'] ? 'Patzer abgefangen.' : 'Waffe verloren oder gestürzt.', 'pages' => '48']);
        $this->finishAttack($state);
    }

    private function strength(array &$state, array $task, array $input): void
    {
        $this->keys($input, []);
        if ($this->defer($state, ['prone'], $task, $input)) {
            return;
        }
        $side = $task['side'];
        $actor = $this->reactionActor($state, $side);
        $attack = $state['pending'];
        $damageModifier = $attack['mode']['damage'] + CombatStats::attribute($state['actors'][$attack['side']], 'st') + ($attack['mode']['npc_damage_offset'] ?? 0);
        $result = CombatMath::result($this->roll($state, $side, ['ST × 3' => 3 * CombatStats::attribute($actor, 'st'),
            'Verletzungen' => -CombatStats::vm($actor, $state['rules'])], 'Niederwerfen widerstehen', '28, 49'), 6 + $damageModifier);
        if (! $result['success']) {
            $state['actors'][$side]['prone'] = true;
            foreach ($state['actors'][$side]['held'] as $id) {
                $this->drop($state, $side, $id);
            }
        }
        $this->event($state, 'knockdown', $side, $result + ['message' => $result['success'] ? 'Stehen geblieben.' : 'Niedergeworfen; kein weiterer Trefferschaden.', 'pages' => '49']);
        $this->finishAttack($state);
    }

    private function resistance(array &$state, array $task, array $input): void
    {
        $this->keys($input, []);
        $attack = $state['pending'];
        $side = $task['side'];
        $actor = $this->reactionActor($state, $side);
        $id = $actor['held'][0] ?? null;
        if ($id === null) {
            $this->event($state, 'disarm', $side, ['message' => 'Keine geführte Waffe mehr vorhanden.']);
            $this->finishAttack($state);

            return;
        }
        $weapon = $actor['weapons'][$id];
        $mode = $weapon['modes'][0];
        $defense = $this->roll($state, $side, [$mode['skill'] => CombatStats::skill($actor, $mode['skill']),
            'GE' => CombatStats::attribute($actor, 'ge'), 'Verteidiger' => 1,
            'Schwerere Waffe' => 2 * max(0, $mode['damage'] - $attack['mode']['damage']),
            'Schusswaffe' => $mode['kind'] === 'ranged' ? -2 : 0,
            'Verletzungen' => -CombatStats::vm($actor, $state['rules']), 'Rüstung' => CombatStats::bm($actor)], 'Entwaffnen widerstehen', '49');
        $this->finishOpposed($state, ['defense' => $defense, 'weapon' => $id]);
    }

    private function finishOpposed(array &$state, array $context): void
    {
        $attack = $state['pending'];
        $difference = $attack['roll']['total'] - $context['defense']['total'];
        if ($difference === 0 && $this->defer($state, ['tie'], ['type' => '_opposed', 'side' => $attack['side'], 'context' => $context], [])) {
            return;
        }
        $success = $difference > 0 || ($difference === 0 && ($state['rules']['tie'] ?? 'unchanged') === 'attacker');
        if ($attack['kind'] === 'psychic') {
            $this->finishPsychic($state, $success);
        } else {
            if ($success) {
                $this->drop($state, $attack['target'], $context['weapon']);
            }
            $this->event($state, 'disarm', $attack['side'], ['message' => $success ? 'Gegner entwaffnet.' : 'Entwaffnen misslungen.',
                'attack' => $attack['roll']['total'], 'defense' => $context['defense']['total'], 'margin' => $difference, 'pages' => '49']);
            $this->finishAttack($state);
        }
        unset($state['rules']['tie']);
    }

    private function damage(array &$state, array $task, array $input): void
    {
        $this->keys($input, []);
        $attack = $state['pending'];
        $target = $attack['target'];
        $actor = $state['actors'][$attack['side']];
        $defender = $this->reactionActor($state, $target);
        $snakke = ($defender['profile']['npc']['template_key'] ?? '') === 'snaekke';
        $ordinaryWeapon = ! in_array($attack['kind'], ['burn', 'psychic_damage', 'npc_acid'], true);
        if ($snakke && $ordinaryWeapon && ! isset($state['npc_interpretations'][$task['token']])
            && ! array_any($defender['effects'], fn ($e) => $e['type'] === 'protection')) {
            $this->requestNpcRuling($state, $task, $input, 'Besonderer Schutz gegen gewöhnliche Waffen', $target, $target);

            return;
        }
        if (! $snakke && CombatStats::disadvantage($defender, 'Verwundbarkeit') && $this->defer($state, ['vulnerability'], $task, $input)) {
            return;
        }
        if (isset($state['npc_interpretations'][$task['token']])) {
            $state['npc_protection'] = $state['npc_interpretations'][$task['token']]['modifier'];
            if ($state['npc_interpretations'][$task['token']]['entangle'] ?? false) {
                $id = $attack['input']['weapon'];
                $this->ensure(! $attack['weapon']['natural'] && $attack['mode']['kind'] === 'melee', 'Nur geführte Nahkampfwaffen können im Schleim steckenbleiben.');
                $state['actors'][$attack['side']]['weapons'][$id]['stuck'] = true;
                $state['actors'][$attack['side']]['held_by'] = $target;
                $state['actors'][$attack['side']]['held'] = array_values(array_diff($state['actors'][$attack['side']]['held'], [$id]));
                $this->event($state, 'npc_weapon_held', $attack['side'], ['message' => 'Waffe im Snäkke-Schleim festgehalten. Befreiung anfordern oder andere Waffe benutzen.', 'pages' => '60']);
            }
            unset($state['npc_interpretations'][$task['token']]);
        }
        $attack['vulnerable'] = $snakke ? $attack['kind'] === 'burn' : ($state['rules']['vulnerability'] ?? 'normal') === 'vulnerable';
        if (in_array($attack['kind'], ['burn', 'psychic_damage', 'npc_acid'], true)) {
            $modifiers = ['Kraft' => $attack['damage'], 'Verletzungen des Ziels' => CombatStats::vm($defender, $state['rules']),
                'RO des Ziels' => $attack['vulnerable'] ? 0 : -CombatStats::attribute($defender, 'ro'),
                'Schutz des Ziels' => -CombatStats::protection($defender, false), 'Taratzenfutter' => CombatStats::disadvantage($defender, 'Taratzenfutter') ? 1 : 0];
        } elseif ($attack['kind'] === 'object') {
            $object = $attack['input']['target'];
            $robustness = (int) ($state['rules']['object_material'] ?? '2');
            $modifiers = ['ST' => CombatStats::attribute($actor, 'st'), 'Waffe' => $attack['mode']['damage'],
                'Kritischer Treffer' => $attack['critical'], 'Objektrobustheit' => -$robustness];
        } else {
            $modifiers = CombatStats::damage($actor, $defender, $attack, $state['rules']);
        }
        if ($snakke && $ordinaryWeapon) {
            $modifiers['Snäkke-Schutz (SL-Auslegung)'] = -($state['npc_protection'] ?? 0);
            unset($state['npc_protection']);
        }
        $roll = $this->roll($state, $attack['side'], $modifiers, 'Schadenswurf', '47, 50', 1);
        unset($state['rules']['vulnerability']);
        if ($attack['kind'] === 'object') {
            $broken = $roll['total'] >= (($state['rules']['object_damage'] ?? 'destroyed') === 'severe' ? 5 : 7);
            if ($broken) {
                $state['actors'][$target][$attack['input']['target'].'_broken'] = true;
            }
            $this->event($state, 'object_damage', $target, $roll + ['message' => $broken ? 'Ausrüstungsgegenstand zerstört.' : 'Ausrüstungsgegenstand beschädigt; Schutz bleibt bestehen.', 'pages' => '50 / SL-16']);
            unset($state['rules']['object_material'], $state['rules']['object_damage']);
            $this->finishAttack($state);
        } else {
            $this->applyWound($state, $target, $roll);
        }
    }

    private function applyWound(array &$state, int $target, array $roll): void
    {
        $wound = CombatMath::wound($roll['total']);
        $rules = [];
        if ($wound >= 2 && $state['actors'][$target]['wounds'] !== []) {
            $rules[] = 'wounds';
        }
        if ($wound > 0 && CombatStats::advantage($state['actors'][$target], 'Regeneration')) {
            $rules[] = 'healing';
        }
        if ($this->defer($state, $rules, ['type' => '_wound', 'side' => $target, 'context' => compact('roll')], [])) {
            return;
        }
        if ($wound > 0) {
            $state['actors'][$target]['wounds'][] = $wound;
        }
        $actor = $state['actors'][$target];
        $vm = CombatStats::vm($actor, $state['rules']);
        $regeneration = CombatStats::advantage($actor, 'Regeneration');
        if ($regeneration && $vm > 0) {
            $state['actors'][$target]['heals_at'] = $state['seconds'] + max(3, (int) ceil(($vm * $vm * 604800) / (10 ** $regeneration)));
        }
        $label = ['Keine Verwundung.', 'Leichte Wunde.', 'Mittlere Wunde.', 'Schwere Wunde.', 'Bewusstlos und kampfunfähig.'][$wound];
        $this->event($state, 'wound', $target, ['message' => $label, 'damage' => $roll['total'], 'wound' => $wound, 'vm' => $vm, 'pages' => '47–48']);
        $this->finishAttack($state);
    }

    private function finishAttack(array &$state): void
    {
        $attack = $state['pending'];
        if ($attack['swallow'] ?? false) {
            $state['pending'] = null;
            if (! in_array(4, $state['actors'][$attack['target']]['wounds'], true) && ! isset($state['actors'][$attack['target']]['swallowed_by'])) {
                $ability = collect($state['actors'][$attack['side']]['profile']['npc']['abilities'])->first(fn ($a) => str_contains($a, 'Verschlingen'));
                $this->requestNpcRuling($state, ['type' => '_npc_effect', 'side' => $attack['side'], 'context' => []], [], $ability, $attack['side'], $attack['target']);

                return;
            }
        }
        if (($attack['weapon']['id'] ?? '') === 'driller' && ! ($attack['secondary'] ?? false) && ! ($attack['not_fired'] ?? false)
            && ($state['rules']['driller'] ?? 'self') === 'self' && $attack['distance'] <= 300) {
            $secondary = $attack;
            $secondary['target'] = $attack['side'];
            $secondary['mode']['damage'] = 0;
            $secondary['mode']['precision'] = -4;
            $secondary['modifiers']['Präzision'] = -4;
            $secondary['secondary'] = true;
            $secondary['cost'] = 0;
            unset($secondary['roll'], $secondary['critical']);
            array_unshift($state['queue'], $secondary);
        }
        $state['pending'] = null;
        $this->nextAttack($state);
    }

    private function reactionActor(array $state, int $side): array
    {
        $actor = $state['actors'][$side];
        if (count($state['group']) > 1 && $state['phase'] !== 'round_effects') {
            $actor['wounds'] = $state['group_actors'][$side]['wounds'];
        }

        return $actor;
    }

    private function drop(array &$state, int $side, string $id): void
    {
        $state['actors'][$side]['weapons'][$id]['position'] = $state['actors'][$side]['position'];
        $state['actors'][$side]['held'] = array_values(array_diff($state['actors'][$side]['held'], [$id]));
        $this->event($state, 'dropped', $side, ['message' => 'Waffe fallengelassen.', 'weapon' => $state['actors'][$side]['weapons'][$id]['name'], 'pages' => '48–49']);
    }
}
