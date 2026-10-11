<?php

namespace App\Services\RpgCombat;

/** Structured adjudication of book abilities whose numerical effects are not specified. */
trait ResolvesNpcAbilities
{
    public static function npcAbilityEffects(string $ability): array
    {
        return match (true) {
            $ability === 'Schrei' => ['paralyzed'],
            $ability === 'Säuresekret' => ['acid', 'blind'],
            str_contains($ability, 'Flug'), str_contains($ability, 'Sprung'), $ability === 'Tunnelbewegung' => ['movement'],
            $ability === 'Gestaltwandel' => ['disguised'],
            str_contains($ability, 'Verschlingen') => ['swallowed'],
            $ability === 'Befreiung' => ['escape'],
            $ability === 'Besonderer Schutz gegen gewöhnliche Waffen' => ['protection'],
            default => ['perception'],
        };
    }

    private function requestNpcRuling(array &$state, array $task, array $input, string $ability, int $source, int $target): void
    {
        $this->ensure($state['continuation'] === null, 'Eine andere Regelfrage ist noch offen.');
        $state['continuation'] = ['task' => $task, 'input' => $input, 'phase' => $state['phase']];
        $state['phase'] = 'awaiting_ruling';
        $this->task($state, 'ruling', 0, ['rules' => [], 'npc_ability' => $ability, 'source' => $source, 'target' => $target,
            'effects' => self::npcAbilityEffects($ability), 'page' => $state['actors'][$source]['profile']['npc']['page'] ?? 50,
            'request' => ['type' => $task['type'], 'side' => $task['side'], 'selection' => $input, 'context' => $task['context']]]);
        $this->event($state, 'ruling_requested', null, ['message' => 'Die AG-Leitung legt die fehlenden Regeln für '.$ability.' begründet fest.', 'pages' => '50, 57–61']);
    }

    private function npcRuling(array &$state, array $task, array $input): void
    {
        $this->keys($input, ['effect', 'duration', 'modifier', 'displacement', 'resistance_attribute', 'difficulty', 'reason', 'range', 'entangle']);
        $context = $task['context'];
        $this->ensure(in_array($input['effect'] ?? null, $context['effects'], true), 'Diese Wirkung passt nicht zur Fähigkeit.');
        foreach (['duration' => [1, 100], 'modifier' => [0, 6], 'displacement' => [-100000, 100000], 'difficulty' => [2, 24], 'range' => [0, 100000]] as $key => [$min, $max]) {
            $this->ensure(is_int($input[$key] ?? null) && $input[$key] >= $min && $input[$key] <= $max, 'Ungültiger Parameter: '.$key);
        }
        $this->ensure(in_array($input['resistance_attribute'] ?? null, ['none', 'st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'], true), 'Ungültige Widerstandsprobe.');
        $this->ensure(! isset($input['entangle']) || (is_bool($input['entangle']) && $context['npc_ability'] === 'Besonderer Schutz gegen gewöhnliche Waffen'), 'Festhalten ist nur für Snäkke-Waffenschutz zulässig.');
        // resolveNpcEffect checks range after the action's move and all simultaneous moves.
        $this->ensure(is_string($input['reason'] ?? null) && mb_strlen(trim($input['reason'])) >= 3 && mb_strlen($input['reason']) <= 2000, 'Die ergänzende Simulatorregel begründen.');
        if (str_contains($context['npc_ability'], 'Sprung')) {
            $this->ensure(abs($input['displacement']) >= 2000 && abs($input['displacement']) <= 3000, 'Frekkeuscher springen laut Regelwerk 20–30 Meter.');
        }
        $effect = $input + ['ability' => $context['npc_ability'], 'source' => $context['source'], 'target' => $context['target']];
        // The leader chooses an interpretation, never a dice result or arbitrary profile data.
        $this->event($state, 'npc_ruling', null, ['message' => $input['reason'], 'interpretation' => $effect, 'pages' => $context['page'], 'supplemental' => true]);
        $next = $state['continuation'];
        $state['continuation'] = null;
        $state['phase'] = $next['phase'];
        if ($next['task']['type'] === '_npc_effect') {
            $this->resolveNpcEffect($state, $effect);
        } else {
            $state['npc_interpretations'][$next['task']['token']] = $effect;
            $this->handle($state, $next['task'], $next['input']);
        }
    }

    private function resolveNpcEffect(array &$state, array $effect): void
    {
        if (abs($state['actors'][$effect['source']]['position'] - $state['actors'][$effect['target']]['position']) > $effect['range']) {
            $this->event($state, 'npc_ability_unavailable', $effect['source'], ['message' => 'Ziel nach gleichzeitiger Bewegung außerhalb der festgelegten Reichweite.']);
            $this->nextAttack($state);

            return;
        }
        $state['pending_npc_effect'] = $effect;
        if ($effect['resistance_attribute'] !== 'none') {
            $this->task($state, 'npc_resistance', $effect['target'], ['attribute' => $effect['resistance_attribute'], 'difficulty' => $effect['difficulty']]);
        } else {
            $this->applyNpcEffect($state, $effect);
            $this->nextAttack($state);
        }
    }

    private function npcResistance(array &$state, array $task, array $input): void
    {
        $this->keys($input, []);
        $effect = $state['pending_npc_effect'];
        $actor = $state['actors'][$task['side']];
        $attribute = $task['context']['attribute'];
        $roll = $this->roll($state, $task['side'], [strtoupper($attribute).' × 3' => 3 * CombatStats::attribute($actor, $attribute)], 'Widerstand: '.$effect['ability'], '50, 57–61');
        $result = CombatMath::result($roll, $task['context']['difficulty']);
        // Escaping succeeds with the probe; an offensive ability succeeds if resistance fails.
        if ($result['success'] === ($effect['effect'] === 'escape')) {
            $this->applyNpcEffect($state, $effect);
        }
        $state['pending_npc_effect'] = null;
        $this->nextAttack($state);
    }

    private function applyNpcEffect(array &$state, array $effect): void
    {
        $target = $effect['target'];
        $actor = &$state['actors'][$target];
        if ($effect['effect'] === 'escape') {
            unset($actor['swallowed_by'], $actor['held_by']);
            foreach ($actor['weapons'] as &$weapon) {
                unset($weapon['stuck']);
            }
            unset($weapon);
            $actor['effects'] = array_values(array_filter($actor['effects'], fn ($e) => $e['type'] !== 'swallowed'));
        } elseif ($effect['effect'] === 'acid') {
            $this->queueNpcAcid($state, $effect['source'], $target, $effect['modifier']);
        } elseif ($effect['effect'] === 'movement') {
            $actor['position'] += $effect['displacement'];
        } else {
            $ongoing = ['type' => $effect['effect'], 'value' => $effect['modifier'], 'expires' => $state['seconds'] + 3 * $effect['duration'], 'source' => $effect['source']];
            if ($effect['effect'] === 'protection') {
                $ongoing['scope'] = 'ordinary_weapons';
            }
            $actor['effects'][] = $ongoing;
            if ($effect['effect'] === 'swallowed') {
                $actor['swallowed_by'] = $effect['source'];
                $actor['position'] = $state['actors'][$effect['source']]['position'];
                if ($state['actors'][$effect['source']]['profile']['npc']['template_key'] === 'gejagudoo') {
                    $this->queueNpcAcid($state, $effect['source'], $target, 1);
                }
            }
        }
        unset($actor);
        $this->carrySwallowedActors($state);
        $this->event($state, 'npc_effect', $target, ['message' => $effect['ability'].': '.$effect['effect'], 'parameters' => $effect, 'pages' => '50, 57–61']);
        $state['pending_npc_effect'] = null;
    }

    private function carrySwallowedActors(array &$state): void
    {
        foreach ([1, 2] as $side) {
            if ($source = $state['actors'][$side]['swallowed_by'] ?? null) {
                $state['actors'][$side]['position'] = $state['actors'][$source]['position'];
            }
        }
    }

    private function queueNpcAcid(array &$state, int $source, int $target, int $damage): void
    {
        $state['queue'][] = ['kind' => 'npc_acid', 'side' => $source, 'target' => $target, 'damage' => $damage];
    }

    private function npcRoundEffects(array &$state): void
    {
        foreach ([1, 2] as $side) {
            $actor = &$state['actors'][$side];
            $swallowed = array_values(array_filter($actor['effects'], fn ($e) => $e['type'] === 'swallowed' && $e['expires'] > $state['seconds']));
            if ($swallowed === []) {
                unset($actor['swallowed_by']);
            } elseif (($source = $actor['swallowed_by'] ?? null) && $state['actors'][$source]['profile']['npc']['template_key'] === 'snaekke') {
                $this->queueNpcAcid($state, $source, $side, 2);
            }
            unset($actor);
        }
    }

    private function npcOrder(array &$state, array $task, array $input): void
    {
        $this->keys($input, ['description']);
        $this->ensure(is_string($input['description'] ?? null) && mb_strlen(trim($input['description'])) >= 3 && mb_strlen($input['description']) <= 1000, 'Den Befehl für den beherrschten NSC beschreiben.');
        $this->task($state, 'action', $task['side'], ['ordered' => true, 'order' => $input['description']]);
        $this->event($state, 'npc_order', null, ['message' => 'Befehl für den beherrschten NSC übermittelt. Die AG-Leitung setzt ihn um.']);
    }
}
