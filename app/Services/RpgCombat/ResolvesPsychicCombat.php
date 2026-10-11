<?php

namespace App\Services\RpgCombat;

trait ResolvesPsychicCombat
{
    private function validatePsychic(array $state, int $side, array $input, int $distance): array
    {
        $actor = $state['actors'][$side];
        $this->ensure(is_string($input['power'] ?? null), 'Talent auswählen.');
        $skill = CombatStats::power($actor, $input['power']);
        $this->ensure($skill > 0, 'Dieses Talent ist nicht nutzbar.');
        foreach (['duration', 'range', 'strength'] as $key) {
            $this->ensure(is_int($input[$key] ?? null), 'Parameter müssen ganze Zahlen sein.');
        }
        $parameters = CombatMath::psychicParameters($skill, $input['duration'], $input['range'], $input['strength']);
        $this->ensure($distance <= max(100, $parameters['range']), 'Ziel außerhalb der psychischen Reichweite.');
        if ($input['power'] === 'Pyrokinese') {
            $damage = $input['damage'] ?? 0;
            $max = ($state['rules']['pyrokinetic_rounding'] ?? 'floor') === 'ceil' ? (int) ceil($skill / 2) : intdiv($skill, 2);
            $this->ensure(is_int($damage) && $damage >= 0 && $damage <= $max && $input['duration'] >= 1, 'Pyrokinese benötigt mindestens eine Runde und zulässigen Schaden.');
            $parameters['cost'] = ($parameters['cost'] + $damage + 2) * 3;
        }
        if ($input['power'] === 'Telekinese') {
            $this->ensure(in_array($input['effect'] ?? null, ['projectile', 'move'], true), 'Telekinese als Geschoss oder Bewegung wählen.');
            $this->ensure($input['duration'] <= 3, 'Telekinese verwendet höchstens D = 3.');
            if ($input['effect'] === 'projectile') {
                $this->ensure($input['duration'] === 3, 'Telekinetische Geschosse benötigen D = 3 (sofort).');
            } else {
                $this->ensure(is_int($input['displacement'] ?? null) && abs($input['displacement']) <= $parameters['range'], 'Telekinetische Bewegung überschreitet R.');
            }
        }
        if ($input['power'] === 'Telepathie') {
            $this->ensure(in_array($input['effect'] ?? null, ['read', 'send'], true), 'Gedanken lesen oder senden wählen.');
            $this->ensure(CombatStats::attribute($state['actors'][3 - $side], 'in') >= -2, 'Ziel ist für Telepathie nicht empfänglich.');
        }
        $this->ensure($actor['pep'] >= $parameters['cost'], 'Nicht genügend PEP.');

        return $parameters;
    }

    private function psychicAttack(array &$state, array $attack): void
    {
        if ($attack['repeat'] ?? false) {
            $state['pending'] = $attack;
            $this->task($state, 'psychic_resistance', $attack['target']);

            return;
        }
        $side = $attack['side'];
        $input = $attack['input'];
        $actor = $state['actors'][$side];
        $distance = abs($actor['position'] - $state['actors'][3 - $side]['position']);
        try {
            $parameters = $this->validatePsychic($state, $side, $input, $distance);
        } catch (\InvalidArgumentException $exception) {
            $this->event($state, 'psychic_unavailable', $side, ['message' => $exception->getMessage().' Die angesagte Kraft entfällt.']);
            $this->finishAttack($state);

            return;
        }
        $state['actors'][$side]['pep'] -= $parameters['cost'];
        $skill = CombatStats::power($actor, $input['power']);
        $attack += ['target' => 3 - $side, 'parameters' => $parameters, 'skill' => $skill];
        $attack['roll'] = $this->roll($state, $side, [$input['power'] => $skill, 'WI' => CombatStats::attribute($actor, 'wi'),
            'Verletzungen' => ($state['rules']['modifiers'] ?? 'physical') === 'concentration' ? -CombatStats::vm($actor, $state['rules']) : 0], $input['power'], '36–37');
        $state['pending'] = $attack;
        $this->event($state, 'pep', $side, ['message' => 'PEP für '.$input['power'].' verbraucht.', 'cost' => $parameters['cost'], 'remaining' => $state['actors'][$side]['pep'], 'pages' => '36–37']);
        if ($input['power'] === 'Gedankenschild' || ($input['power'] === 'Empathie' && $input['strength'] <= 3)
            || ($input['power'] === 'Telepathie' && $input['effect'] === 'send')) {
            $this->finishPsychic($state, true);
        } elseif ($input['power'] === 'Telekinese' && $input['effect'] === 'projectile' && ($state['rules']['telekinesis'] ?? 'dodge') === 'dodge') {
            $state['pending']['mode'] = ['kind' => 'ranged'];
            $this->task($state, 'defense', 3 - $side);
        } else {
            $this->task($state, 'psychic_resistance', 3 - $side);
        }
    }

    private function psychicResistance(array &$state, array $task, array $input): void
    {
        $this->keys($input, []);
        $attack = $state['pending'];
        $mods = CombatStats::psychicDefense($this->reactionActor($state, $task['side']), $state['rules']);
        if ($attack['input']['power'] === 'Telepathie') {
            $mods['Telepathie [S]'] = -$attack['input']['strength'];
        }
        $defense = $this->roll($state, $task['side'], $mods, 'Psychischer Widerstand', '36–37');
        $this->finishOpposed($state, compact('defense'));
    }

    private function finishPsychic(array &$state, bool $success): void
    {
        $attack = $state['pending'];
        $side = $attack['side'];
        $target = $attack['target'];
        $input = $attack['input'];
        $parameters = $attack['parameters'];
        if ($attack['repeat'] ?? false) {
            if (! $success) {
                $state['actors'][$target]['effects'] = array_values(array_filter($state['actors'][$target]['effects'], fn ($effect) => $effect['type'] !== 'control'));
            }
            $this->event($state, 'control_resistance', $target, ['message' => $success ? 'Beherrschung hält an.' : 'Beherrschung abgeschüttelt.', 'pages' => '37']);
            $this->finishAttack($state);

            return;
        }
        if ($success && $input['power'] === 'Telekinese' && $input['effect'] === 'move') {
            if ($this->defer($state, ['telekinetic_mass'], ['type' => '_psychic_move', 'side' => $side, 'context' => []], [])) {
                return;
            }
            $success = $state['rules']['telekinetic_mass'] === 'sufficient';
            unset($state['rules']['telekinetic_mass']);
        }
        $this->event($state, 'psychic_result', $side, ['message' => $input['power'].($success ? ' wirkt.' : ' wurde abgewehrt oder ist wirkungslos.'), 'parameters' => $parameters, 'pages' => '36–37']);
        if ($success) {
            $duration = $parameters['duration'];
            switch ($input['power']) {
                case 'Pyrokinese':
                    $this->effect($state, $target, ['type' => 'burn', 'source' => $side, 'damage' => $input['damage'] ?? 0, 'remaining' => $input['duration'], 'expires' => PHP_INT_MAX]);
                    break;
                case 'Beherrschung':
                    if ($duration > 0) {
                        $this->effect($state, $target, ['type' => 'control', 'controller' => $side, 'attack' => $attack, 'next_resistance' => $state['seconds'] + 3600, 'expires' => $state['seconds'] + $duration]);
                    }
                    break;
                case 'Gedankenschild':
                    if ($duration > 0) {
                        $this->effect($state, $target, ['type' => 'shield', 'value' => $attack['skill'], 'expires' => $state['seconds'] + $duration]);
                    }
                    break;
                case 'Telekinese':
                    if ($input['effect'] === 'projectile') {
                        array_unshift($state['queue'], ['kind' => 'psychic_damage', 'side' => $side, 'target' => $target, 'damage' => $input['strength'] - 3]);
                    } else {
                        $delay = [0, 10, 60, 360][3 - $input['duration']];
                        if ($delay === 0) {
                            $state['actors'][$target]['position'] += $input['displacement'];
                        } else {
                            $this->effect($state, $target, ['type' => 'displacement', 'value' => $input['displacement'], 'expires' => $state['seconds'] + $delay]);
                        }
                    }
                    break;
                default:
                    $this->event($state, 'information', $side, ['message' => 'Information übermittelt; im offenen Übungsduell entsteht daraus kein pauschaler Kampfbonus.', 'pages' => '37']);
            }
        }
        $this->finishAttack($state);
    }

    private function effect(array &$state, int $side, array $effect): void
    {
        $state['actors'][$side]['effects'] = array_values(array_filter($state['actors'][$side]['effects'], fn ($old) => $old['type'] !== $effect['type']));
        $state['actors'][$side]['effects'][] = $effect;
    }

    private function roundEffects(array &$state): void
    {
        foreach ([1, 2] as $side) {
            $actor = &$state['actors'][$side];
            foreach ($actor['weapons'] as &$weapon) {
                if ($weapon['fuel'] !== null && in_array($weapon['instance'], $actor['held'], true)) {
                    $weapon['fuel'] = max(0, $weapon['fuel'] - 1);
                }
                if ($weapon['id'] === 'energiegewehr' && $state['seconds'] % 60 === 0) {
                    $weapon['loaded'] = min($weapon['capacity'], $weapon['loaded'] + 1);
                }
            }
            unset($weapon);
            foreach ($actor['effects'] as $index => &$effect) {
                if ($effect['type'] === 'burn') {
                    $state['queue'][] = ['kind' => 'burn', 'side' => $effect['source'], 'target' => $side, 'damage' => $effect['damage']];
                    $effect['remaining']--;
                    if ($effect['remaining'] <= 0) {
                        $effect['expires'] = $state['seconds'];
                    }
                }
                if ($effect['expires'] <= $state['seconds']) {
                    if ($effect['type'] === 'displacement') {
                        $actor['position'] += $effect['value'];
                    }
                    $this->event($state, 'effect_expired', $side, ['message' => 'Zeitwirkung beendet: '.$effect['type'], 'pages' => '36–37']);
                    unset($actor['effects'][$index]);
                    if ($effect['type'] === 'swallowed') {
                        unset($actor['swallowed_by']);
                    }
                } elseif ($effect['type'] === 'control' && $effect['next_resistance'] <= $state['seconds']) {
                    $repeat = $effect['attack'];
                    $repeat['repeat'] = true;
                    $state['queue'][] = $repeat;
                    $effect['next_resistance'] += 3600;
                }
            }
            unset($effect);
            $actor['effects'] = array_values($actor['effects']);
            if ($actor['heals_at'] !== null && $actor['heals_at'] <= $state['seconds']) {
                $actor['wounds'] = [];
                $actor['heals_at'] = null;
                $this->event($state, 'regeneration', $side, ['message' => 'Wunden nach Ablauf der Heilfrist regeneriert.', 'pages' => '25, 50']);
            }
            unset($actor);
        }
    }
}
