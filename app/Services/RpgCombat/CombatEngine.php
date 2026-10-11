<?php

namespace App\Services\RpgCombat;

use App\Support\RpgCombatRules;
use InvalidArgumentException;

/** A persistent state machine: no database, wall clock, mail or browser state. */
final class CombatEngine
{
    use ResolvesCombatAttacks;
    use ResolvesNpcAbilities;
    use ResolvesPsychicCombat;

    private array $events = [];

    public function __construct(private CombatDice $dice) {}

    public function start(array $profiles, int $distance, int $roundLimit): array
    {
        $this->ensure(count($profiles) === 2 && $distance >= 100 && $roundLimit >= 1, 'Ungültige Kampfbedingungen.');
        $this->events = [];
        $version = isset($profiles[0]['npc']) || isset($profiles[1]['npc']) ? RpgCombatRules::NPC_VERSION : RpgCombatRules::VERSION;
        $state = ['version' => $version, 'round' => 1, 'seconds' => 0, 'limit' => $roundLimit,
            'phase' => 'preparing', 'actors' => [], 'rules' => [], 'tasks' => [], 'next_token' => 0,
            'groups' => [], 'group' => [], 'declarations' => [], 'queue' => [], 'pending' => null,
            'continuation' => null, 'end' => null, 'abort_offer' => null];
        foreach (array_values($profiles) as $index => $profile) {
            $side = $index + 1;
            $state['actors'][$side] = ['profile' => $profile, 'position' => $index * $distance,
                'weapons' => $profile['weapons'], 'items' => $profile['items'], 'held' => [], 'shield' => false,
                'armor_broken' => in_array('Primitiv', $profile['disadvantages'], true) && ($profile['armor']['education'] ?? 0) > 0, 'shield_broken' => false, 'technology' => [], 'initiative_skill' => 'Nahkampf',
                'initiative' => null, 'wounds' => [], 'pep' => $profile['psychic']['pep'], 'effects' => [],
                'prone' => false, 'blocked_until' => 0, 'parried' => false, 'dodges' => 0, 'full_defense' => false,
                'has_attacked' => false, 'moved' => 0, 'heals_at' => null, 'prepared' => false];
            $this->task($state, 'prepare', $side);
        }
        $this->event($state, 'started', null, ['message' => 'Übungskampf angenommen. Beide wählen ihre Startausrüstung.', 'pages' => '17, 40–41, 46']);

        return $this->output($state);
    }

    public function decide(array $state, int $token, array $input): array
    {
        $this->events = [];
        $this->ensure(in_array($state['version'], [RpgCombatRules::VERSION, RpgCombatRules::NPC_VERSION], true) && $state['end'] === null, 'Dieser Kampf kann nicht fortgesetzt werden.');
        $task = $state['tasks'][$token] ?? throw new InvalidArgumentException('Diese Entscheidung ist nicht mehr offen.');
        $this->ensure($task['type'] === 'ruling' || $state['continuation'] === null, 'Zunächst muss die Regelfrage entschieden werden.');
        unset($state['tasks'][$token]);
        $this->handle($state, $task, $input);

        return $this->output($state);
    }

    private function handle(array &$state, array $task, array $input): void
    {
        match ($task['type']) {
            'prepare' => $this->prepare($state, $task, $input),
            'initiative' => $this->initiative($state, $task, $input),
            'action' => $this->action($state, $task, $input),
            'defense' => $this->defend($state, $task, $input),
            'damage' => $this->damage($state, $task, $input),
            'fumble' => $this->fumble($state, $task, $input),
            'strength' => $this->strength($state, $task, $input),
            'resistance' => $this->resistance($state, $task, $input),
            'psychic_resistance' => $this->psychicResistance($state, $task, $input),
            'npc_resistance' => $this->npcResistance($state, $task, $input),
            'npc_order' => $this->npcOrder($state, $task, $input),
            'technology' => $this->technology($state, $task, $input),
            'ruling' => $this->rule($state, $task, $input),
            '_round' => $this->beginRound($state),
            '_wound' => $this->applyWound($state, $task['side'], $task['context']['roll']),
            '_opposed' => $this->finishOpposed($state, $task['context']),
            '_psychic_move' => $this->finishPsychic($state, true),
            default => throw new InvalidArgumentException('Unbekannter Kampfschritt.'),
        };
    }

    private function prepare(array &$state, array $task, array $input): void
    {
        $this->keys($input, ['weapons', 'shield', 'skill']);
        $this->ensure(is_array($input['weapons'] ?? null) && is_bool($input['shield'] ?? null)
            && is_string($input['skill'] ?? null), 'Startwaffen, Schild und Kampfart wählen.');
        $side = $task['side'];
        $actor = $state['actors'][$side];
        $this->loadout($actor, $input['weapons'], $input['shield']);
        $this->ensure(in_array($input['skill'], ['Nahkampf', 'Fernkampf', 'Feuerwaffen', 'Psychisch'], true), 'Ungültige Initiativefertigkeit.');
        $available = ['Nahkampf'];
        foreach ($input['weapons'] as $id) {
            foreach ($actor['weapons'][$id]['modes'] as $mode) {
                $available[] = $mode['skill'];
            }
        }
        $this->ensure($input['skill'] === 'Psychisch' ? collect($actor['profile']['psychic']['powers'])->contains('usable', true)
            : in_array($input['skill'], $available, true), 'Die Initiativefertigkeit muss zur Startausrüstung passen.');
        $rules = $input['weapons'] !== [] || $input['shield'] ? ['hands'] : [];
        if ($input['skill'] === 'Psychisch') {
            $rules[] = 'psychic_initiative';
        }
        if ($this->defer($state, $rules, $task, $input)) {
            return;
        }
        $actor['held'] = array_values($input['weapons']);
        $actor['shield'] = $input['shield'];
        $skill = $input['skill'];
        if ($skill === 'Psychisch') {
            $skill = ($state['rules']['psychic_initiative'] ?? 'Nahkampf') === 'best'
                ? collect(['Nahkampf', 'Fernkampf', 'Feuerwaffen'])->sortByDesc(fn ($s) => CombatStats::skill($actor, $s))->first() : 'Nahkampf';
        }
        $actor['initiative_skill'] = $skill;
        $actor['prepared'] = true;
        $state['actors'][$side] = $actor;
        foreach ($actor['held'] as $id) {
            $weapon = $actor['weapons'][$id];
            if ($weapon['education'] - CombatStats::skill($actor, 'Bildung') > 2) {
                $this->task($state, 'technology', $side, ['weapon' => $id, 'education' => $weapon['education'], 'preparing' => true]);
            }
            if ($weapon['id'] === 'kettensaege') {
                $fuel = $this->dice->w66();
                $state['actors'][$side]['weapons'][$id]['fuel'] = $fuel['total'];
                $this->event($state, 'fuel', $side, $fuel + ['message' => 'Treibstoff der Kettensäge in Kampfrunden', 'pages' => '41']);
            }
        }
        if (! $actor['armor_broken'] && (($actor['profile']['armor']['education'] ?? 0) > 0)) {
            if (CombatStats::skill($actor, 'Bildung') < 3) {
                $this->task($state, 'technology', $side, ['weapon' => 'armor', 'education' => $actor['profile']['armor']['education'], 'preparing' => true]);
            }
        }
        $this->event($state, 'prepared', $side, ['message' => 'Startausrüstung festgelegt.', 'weapons' => $actor['held'], 'shield' => $actor['shield'], 'skill' => $skill, 'pages' => '40–41, 46']);
        $this->finishPreparation($state);
    }

    private function finishPreparation(array &$state): void
    {
        if ($state['tasks'] !== []) {
            return;
        }
        $state['phase'] = 'initiative';
        foreach ([1, 2] as $side) {
            $this->task($state, 'initiative', $side);
        }
    }

    private function technology(array &$state, array $task, array $input): void
    {
        $this->keys($input, []);
        $side = $task['side'];
        $actor = $state['actors'][$side];
        $result = CombatMath::result($this->roll($state, $side, ['IN × 3' => 3 * CombatStats::attribute($actor, 'in')], 'Benutzungsprobe', '31'), 6 + $task['context']['education']);
        $id = $task['context']['weapon'];
        $state['actors'][$side]['technology'][$id] = $result['success'];
        if (! $result['success']) {
            if ($id === 'armor') {
                $state['actors'][$side]['armor_broken'] = true;
            } else {
                $state['actors'][$side]['held'] = array_values(array_diff($actor['held'], [$id]));
            }
        }
        $this->event($state, 'technology', $side, $result + ['message' => $result['success'] ? 'Gegenstand kann verwendet werden.' : 'Gegenstand kann in diesem Duell nicht verwendet werden.', 'weapon' => $id, 'pages' => '31']);
        if ($task['context']['preparing']) {
            $this->finishPreparation($state);
        } else {
            $this->nextAttack($state);
        }
    }

    private function initiative(array &$state, array $task, array $input): void
    {
        $this->keys($input, []);
        $side = $task['side'];
        $actor = $state['actors'][$side];
        $result = $this->roll($state, $side, [$actor['initiative_skill'] => CombatStats::skill($actor, $actor['initiative_skill']),
            'WA' => CombatStats::attribute($actor, 'wa'), 'Schnell' => CombatStats::advantage($actor, 'Schnell') ? 1 : 0,
            'Zwei Waffen' => count($actor['held']) === 2 ? -1 : 0], 'Initiative', '46, 49', 1);
        $state['actors'][$side]['initiative'] = $result['total'];
        if ($state['tasks'] === []) {
            $this->beginRound($state);
        }
    }

    private function beginRound(array &$state): void
    {
        if ($this->ended($state)) {
            return;
        }
        $tied = $state['actors'][1]['initiative'] === $state['actors'][2]['initiative'];
        if ($tied && $this->defer($state, ['initiative_tie'], ['type' => '_round', 'side' => 0, 'context' => []], [])) {
            return;
        }
        $state['phase'] = 'action';
        foreach ([1, 2] as $side) {
            $actor = &$state['actors'][$side];
            $actor['parried'] = false;
            $actor['dodges'] = 0;
            $actor['full_defense'] = false;
            $actor['has_attacked'] = false;
            $actor['moved'] = 0;
            unset($actor);
        }
        $first = $state['actors'][1]['initiative'] >= $state['actors'][2]['initiative'] ? 1 : 2;
        $state['groups'] = $tied ? [[1, 2]] : [[$first], [3 - $first]];
        $this->event($state, 'round', null, ['message' => 'Kampfrunde '.$state['round'].' beginnt.', 'simultaneous' => $tied, 'pages' => '46']);
        $this->nextGroup($state);
    }

    private function nextGroup(array &$state): void
    {
        if ($this->ended($state)) {
            return;
        }
        if ($state['groups'] === []) {
            $this->endRound($state);

            return;
        }
        $state['group'] = array_shift($state['groups']);
        $state['group_actors'] = $state['actors'];
        $state['declarations'] = [];
        foreach ($state['group'] as $side) {
            if ($state['actors'][$side]['blocked_until'] >= $state['round']) {
                $this->event($state, 'skipped', $side, ['message' => 'Handlung entfällt nach Verteidigungspatzer.', 'pages' => '48']);
            } elseif (array_any($state['actors'][$side]['effects'], fn ($e) => $e['type'] === 'paralyzed' && $e['expires'] > $state['seconds'])) {
                $this->event($state, 'skipped', $side, ['message' => 'Handlung entfällt durch Paralyse.', 'pages' => '57']);
            } else {
                $this->task($state, 'action', $side);
            }
        }
        if ($state['tasks'] === []) {
            $this->nextGroup($state);
        }
    }

    private function action(array &$state, array $task, array $input): void
    {
        $this->keys($input, ['kind', 'weapon', 'mode', 'attribute', 'aim', 'fire', 'move', 'shield', 'weapons',
            'power', 'duration', 'range', 'strength', 'damage', 'target', 'object', 'weight', 'description', 'effect', 'displacement', 'ability']);
        $this->ensure(is_string($input['kind'] ?? null), 'Eine Handlung auswählen.');
        $side = $task['side'];
        // Validate before asking the leader; invalid requests cannot create arbitration queues.
        $this->validateAction($state, $side, $input);
        $rules = $this->actionRules($state, $side, $input);
        if ($this->defer($state, $rules, $task, $input)) {
            return;
        }
        if ($input['kind'] === 'npc_ability') {
            if (! isset($state['npc_interpretations'][$task['token']])) {
                $source = $input['ability'] === 'Befreiung' ? ($state['actors'][$side]['swallowed_by'] ?? $state['actors'][$side]['held_by']) : $side;
                $target = $input['ability'] === 'Befreiung' || in_array(self::npcAbilityEffects($input['ability'])[0], ['movement', 'disguised', 'perception', 'protection'], true) ? $side : 3 - $side;
                $this->requestNpcRuling($state, $task, $input, $input['ability'], $source, $target);

                return;
            }
            $input['interpretation'] = $state['npc_interpretations'][$task['token']];
            unset($state['npc_interpretations'][$task['token']]);
        }
        $state['declarations'][$side] = $input;
        $this->event($state, 'declared', $side, ['message' => 'Handlung verbindlich festgelegt. Sie wird nach allen gleichzeitigen Ansagen aufgedeckt.']);
        if ($state['tasks'] !== []) {
            return;
        }
        // Positions are calculated from the same start state, independent of request arrival.
        foreach ($state['declarations'] as $actorSide => $declaration) {
            $move = $declaration['move'] ?? 0;
            $state['actors'][$actorSide]['position'] += $move;
            $state['actors'][$actorSide]['moved'] = abs($move);
            $actor = &$state['actors'][$actorSide];
            $direction = $move <=> 0;
            $previous = $actor['runup'] ?? [];
            $actor['runup'] = $declaration['kind'] === 'run' && $direction !== 0
                ? ['distance' => abs($move) + (($previous['round'] ?? 0) === $state['round'] - 1 && ($previous['direction'] ?? 0) === $direction ? $previous['distance'] : 0), 'direction' => $direction, 'round' => $state['round']]
                : [];
            unset($actor);
            $this->event($state, 'action', (int) $actorSide, ['message' => self::actionLabels()[$declaration['kind']], 'selection' => $declaration, 'pages' => '46–50']);
        }
        $this->carrySwallowedActors($state);
        foreach ($state['group'] as $actorSide) {
            if (isset($state['declarations'][$actorSide])) {
                $this->executeAction($state, $actorSide, $state['declarations'][$actorSide]);
            }
        }
        $this->nextAttack($state);
    }

    private function actionRules(array $state, int $side, array $input): array
    {
        $actor = $state['actors'][$side];
        $rules = [];
        if ($actor['wounds'] !== [] || CombatStats::bm($actor) !== 0) {
            $rules[] = 'modifiers';
        }
        if ($actor['prone']) {
            $rules[] = 'prone';
        }
        $rules = [...$rules, ...match ($input['kind']) {
            'full_defense' => ['full_defense'], 'reload', 'unjam' => ['reload'],
            'heal' => ['healing'], 'creative' => ['context'], 'object' => ['object_material', 'object_damage'],
            'psychic' => ['psychic_resistance', 'duration', ...match ($input['power'] ?? '') {
                'Pyrokinese' => ['pyrokinetic_rounding'], 'Telekinese' => ['telekinesis'], default => [],
            }],
            default => [],
        }];
        if (($actor['weapons'][$input['weapon'] ?? '']['id'] ?? '') === 'natural' && ! isset($actor['profile']['npc'])) {
            $rules[] = 'natural_weapons';
        }
        if (($actor['weapons'][$input['weapon'] ?? '']['id'] ?? '') === 'driller') {
            $rules[] = 'driller';
        }

        return $rules;
    }

    public static function actionLabels(): array
    {
        return ['attack' => 'Angreifen', 'disarm' => 'Entwaffnen', 'knockdown' => 'Niederwerfen',
            'full_defense' => 'Volle Verteidigung', 'move' => 'Bewegen', 'run' => 'Rennen', 'stand' => 'Aufstehen',
            'switch' => 'Waffen wechseln', 'pickup' => 'Waffe aufnehmen', 'reload' => 'Nachladen', 'unjam' => 'Ladehemmung beseitigen',
            'heal' => 'Heilgel verwenden', 'psychic' => 'Psychische Kraft einsetzen', 'creative' => 'Kreative Aktion',
            'object' => 'Ausrüstung angreifen', 'wait' => 'Abwarten', 'npc_ability' => 'NSC-Sonderfähigkeit / Befreiung'];
    }

    private function validateAction(array $state, int $side, array $input): void
    {
        $actor = $state['actors'][$side];
        $this->ensure(isset(self::actionLabels()[$input['kind']]), 'Unbekannte Handlung.');
        foreach (['weapon', 'attribute', 'fire', 'power', 'target', 'effect'] as $key) {
            $this->ensure(! isset($input[$key]) || is_string($input[$key]), 'Ungültige Auswahl: '.$key);
        }
        $this->ensure(! $actor['full_defense'] || ! in_array($input['kind'], ['attack', 'disarm', 'knockdown', 'object', 'psychic'], true), 'Volle Verteidigung schließt Angriffe in dieser Runde aus.');
        $move = $input['move'] ?? 0;
        $this->ensure(! isset($actor['swallowed_by']) || ($move === 0 && in_array($input['kind'], ['npc_ability', 'wait'], true)), 'Verschlungen: Befreiung anfordern oder abwarten.');
        $this->ensure(is_int($move) && abs($move) <= CombatStats::movement($actor) * ($input['kind'] === 'run' ? 4 : 1)
            && (! $actor['prone'] || $move === 0), 'Unzulässige Bewegung.');
        $distance = abs($actor['position'] + $move - $state['actors'][3 - $side]['position']);
        if (in_array($input['kind'], ['attack', 'disarm', 'knockdown', 'object'], true)) {
            $this->ensure(is_string($input['weapon'] ?? null) && is_int($input['mode'] ?? 0), 'Waffe und Angriffsart auswählen.');
            $weapon = CombatStats::weapon($actor, $input['weapon']);
            $mode = CombatStats::mode($weapon, $input['mode'] ?? 0);
            if (($mode['runup'] ?? 0) > 0) {
                $runup = $actor['runup'] ?? [];
                $direction = ($state['actors'][3 - $side]['position'] - $actor['position']) <=> 0;
                $this->ensure(($runup['round'] ?? 0) === $state['round'] - 1 && ($runup['direction'] ?? 0) === $direction
                    && ($move === 0 || ($move <=> 0) === $direction) && ($runup['distance'] ?? 0) + abs($move) >= $mode['runup'], 'Dieser Angriff benötigt mindestens sechs Meter tatsächlichen, geradlinigen Anlauf.');
            }
            $this->ensure(! $weapon['jammed'] && ($weapon['fuel'] === null || $weapon['fuel'] > 0), 'Die Waffe ist blockiert oder ohne Treibstoff.');
            CombatStats::attack($actor, $weapon, $mode, $state['rules'], $distance, $input);
            if ($input['kind'] !== 'attack') {
                $this->ensure($mode['kind'] === 'melee' && ($input['aim'] ?? 0) === 0, 'Dieses Manöver benötigt einen Nahkampfangriff ohne gezielten Schlag.');
            }
            if ($mode['kind'] === 'ranged') {
                $cost = CombatMath::fireMode($input['fire'] ?? 'E', $mode['fireRate'])['ammunition'];
                $this->ensure(($weapon['capacity'] > 0 ? $weapon['loaded'] : ($weapon['thrown'] ? 1 : $weapon['reserve'])) >= $cost, 'Nicht genügend geladene Munition.');
            }
            if ($input['kind'] === 'disarm') {
                $this->ensure($state['actors'][3 - $side]['held'] !== [], 'Der Gegner führt keine entwaffnungsfähige Waffe.');
            }
            if ($input['kind'] === 'object') {
                $this->ensure(in_array($input['target'] ?? null, ['shield', 'armor'], true)
                    && $state['actors'][3 - $side]['profile'][$input['target']] !== null, 'Das gewählte Ausrüstungsziel ist nicht vorhanden.');
            }
        } elseif ($input['kind'] === 'switch') {
            $this->ensure(is_array($input['weapons'] ?? null) && is_bool($input['shield'] ?? null), 'Neue Handbelegung auswählen.');
            $this->loadout($actor, $input['weapons'], $input['shield']);
        } elseif (in_array($input['kind'], ['reload', 'unjam', 'pickup'], true)) {
            $this->ensure(is_string($input['weapon'] ?? null) && isset($actor['weapons'][$input['weapon']]), 'Waffe auswählen.');
            $weapon = $actor['weapons'][$input['weapon']];
            if ($input['kind'] === 'pickup') {
                $this->ensure($weapon['position'] !== null && abs($weapon['position'] - $actor['position'] - $move) <= 100, 'Die Waffe liegt nicht in Reichweite.');
            } else {
                CombatStats::weapon($actor, $input['weapon']);
                $this->ensure($input['kind'] === 'unjam' ? $weapon['jammed'] : ($weapon['capacity'] > $weapon['loaded'] && $weapon['reserve'] > 0), 'Diese Waffenaktion ist nicht möglich.');
            }
        } elseif ($input['kind'] === 'stand') {
            $this->ensure($actor['prone'], 'Der Charakter steht bereits.');
        } elseif ($input['kind'] === 'heal') {
            $this->ensure(($actor['items']['heilgel'] ?? 0) > 0 && $actor['wounds'] !== [], 'Kein verwendbares Heilgel oder keine Wunde.');
        } elseif ($input['kind'] === 'psychic') {
            $this->validatePsychic($state, $side, $input, $distance);
        } elseif ($input['kind'] === 'creative') {
            $this->ensure(is_string($input['description'] ?? null) && mb_strlen(trim($input['description'])) >= 3
                && mb_strlen($input['description']) <= 1000, 'Die kreative Handlung kurz beschreiben.');
        } elseif ($input['kind'] === 'npc_ability') {
            $abilities = $actor['profile']['npc']['abilities'] ?? [];
            if (isset($actor['swallowed_by'])) {
                $abilities = ['Befreiung'];
            } elseif (isset($actor['held_by'])) {
                $abilities[] = 'Befreiung';
            }
            $this->ensure(is_string($input['ability'] ?? null) && in_array($input['ability'], $abilities, true)
                && ! str_contains($input['ability'], 'Verschlingen'), 'Diese Sonderfähigkeit ist nicht verfügbar. Verschlingen wird durch einen erfolgreichen Biss ausgelöst.');
        }
    }

    private function executeAction(array &$state, int $side, array $input): void
    {
        $actor = &$state['actors'][$side];
        switch ($input['kind']) {
            case 'attack': case 'disarm': case 'knockdown': case 'object':
                $actor['has_attacked'] = true;
                $this->queueAttack($state, $side, $input);
                $mode = $actor['weapons'][$input['weapon']]['modes'][$input['mode'] ?? 0];
                if ($input['kind'] === 'attack') {
                    for ($index = 1; $index < ($mode['attack_count'] ?? 1); $index++) {
                        $this->queueAttack($state, $side, $input);
                    }
                }
                if ($input['kind'] === 'attack' && count($actor['held']) === 2) {
                    $other = array_values(array_diff($actor['held'], [$input['weapon']]))[0] ?? null;
                    if ($other !== null) {
                        $second = array_replace($input, ['weapon' => $other, 'mode' => 0, 'aim' => 0, 'fire' => 'E']);
                        unset($second['attribute']);
                        $this->queueAttack($state, $side, $second);
                    }
                }
                break;
            case 'full_defense':
                $actor['full_defense'] = true;
                break;
            case 'stand':
                $actor['prone'] = false;
                break;
            case 'switch':
                $actor['held'] = array_values($input['weapons']);
                $actor['shield'] = $input['shield'];
                foreach ($actor['held'] as $id) {
                    $weapon = $actor['weapons'][$id];
                    if ($weapon['id'] === 'kettensaege' && $weapon['fuel'] === null) {
                        $fuel = $this->dice->w66();
                        $actor['weapons'][$id]['fuel'] = $fuel['total'];
                        $this->event($state, 'fuel', $side, $fuel + ['message' => 'Kettensäge gestartet: Treibstoff in Kampfrunden.', 'pages' => '41']);
                    }
                    if ($weapon['education'] - CombatStats::skill($actor, 'Bildung') > 2 && ! array_key_exists($id, $actor['technology'])) {
                        $this->task($state, 'technology', $side, ['weapon' => $id, 'education' => $weapon['education'], 'preparing' => false]);
                    }
                }
                break;
            case 'pickup':
                $actor['weapons'][$input['weapon']]['position'] = null;
                break;
            case 'reload': case 'unjam':
                $weapon = &$actor['weapons'][$input['weapon']];
                if (($weapon['progress_kind'] ?? null) !== $input['kind'] || ($weapon['progress_round'] ?? 0) !== $state['round'] - 1) {
                    $weapon['progress'] = 0;
                }
                $weapon['progress']++;
                $weapon['progress_kind'] = $input['kind'];
                $weapon['progress_round'] = $state['round'];
                if ($weapon['progress'] >= (($state['rules']['reload'] ?? 'one') === 'two' ? 2 : 1)) {
                    if ($input['kind'] === 'reload') {
                        $amount = min($weapon['capacity'] - $weapon['loaded'], $weapon['reserve']);
                        $weapon['loaded'] += $amount;
                        $weapon['reserve'] -= $amount;
                    } else {
                        $weapon['jammed'] = false;
                    }
                    $weapon['progress'] = 0;
                }
                unset($weapon);
                break;
            case 'heal':
                $actor['items']['heilgel']--;
                $actor['wounds'] = CombatMath::heal($actor['wounds']);
                $this->event($state, 'healed', $side, ['message' => 'Heilgel reduziert die schwerste Wunde um eine Stufe.', 'wounds' => $actor['wounds'], 'pages' => '43 / SL-17']);
                break;
            case 'psychic':
                $actor['has_attacked'] = true;
                $state['queue'][] = ['kind' => 'psychic', 'side' => $side, 'input' => $input];
                break;
            case 'npc_ability':
                $state['queue'][] = ['kind' => 'npc_ability', 'side' => $side, 'interpretation' => $input['interpretation']];
                break;
            case 'creative':
                $bonus = match ($state['rules']['context'] ?? 'none') {
                    'plus_one' => 1, 'plus_two' => 2, default => 0
                };
                $this->event($state, 'creative', $side, ['message' => $input['description'], 'modifier' => $bonus, 'pages' => '45 / SL-16']);
                if ($bonus > 0) {
                    $actor['effects'][] = ['type' => 'creative', 'value' => $bonus, 'expires' => $state['seconds'] + 6];
                }
                unset($state['rules']['context']);
                break;
        }
        unset($actor);
    }

    private function loadout(array $actor, array $weapons, bool $shield): void
    {
        $this->ensure(array_all($weapons, fn ($value) => is_string($value)), 'Waffeninstanzen müssen Zeichenketten sein.');
        $this->ensure(array_is_list($weapons) && count($weapons) <= 2 && count(array_unique($weapons)) === count($weapons), 'Höchstens zwei unterschiedliche Waffeninstanzen wählen.');
        $hands = $shield ? 1 : 0;
        $this->ensure(! $shield || ($actor['profile']['shield'] !== null && ! $actor['shield_broken']), 'Kein verwendbarer Schild.');
        foreach ($weapons as $id) {
            $this->ensure(is_string($id) && isset($actor['weapons'][$id]), 'Unbekannte Waffe.');
            $weapon = $actor['weapons'][$id];
            $this->ensure(! $weapon['natural'] && ! $weapon['broken'] && ! ($weapon['stuck'] ?? false) && $weapon['position'] === null, 'Diese Waffe kann nicht bereitgehalten werden.');
            $this->ensure(! (CombatStats::disadvantage($actor, 'Primitiv') && $weapon['education'] > 0), 'Primitiv verhindert technische Waffen.');
            $this->ensure(! array_key_exists($id, $actor['technology']) || $actor['technology'][$id], 'Die Benutzungsprobe für diese Waffe ist gescheitert.');
            $hands += $weapon['hands'];
            if (count($weapons) === 2) {
                $this->ensure($weapon['size'] !== 'large' && CombatStats::skill($actor, $weapon['modes'][0]['skill']) >= 3, 'Zweiwaffenkampf benötigt passende Fertigkeit 3 und höchstens mittelgroße Waffen.');
            }
        }
        $this->ensure($hands <= 2, 'Waffen und Schild benötigen mehr als zwei Hände.');
    }

    private function endRound(array &$state): void
    {
        $state['phase'] = 'round_effects';
        $this->npcRoundEffects($state);
        $state['seconds'] += RpgCombatRules::ROUND_SECONDS;
        $this->roundEffects($state);
        $this->nextAttack($state);
    }

    private function completeRound(array &$state): void
    {
        if ($this->ended($state)) {
            return;
        }
        if ($state['round'] >= $state['limit']) {
            $this->finish($state, null, 'round_limit');

            return;
        }
        $state['round']++;
        if ($state['actors'][1]['initiative'] === $state['actors'][2]['initiative'] && ($state['rules']['initiative_tie'] ?? 'next_round') === 'next_round') {
            $state['phase'] = 'initiative';
            foreach ([1, 2] as $side) {
                $this->task($state, 'initiative', $side);
            }
        } else {
            $this->beginRound($state);
        }
    }

    private function ended(array &$state): bool
    {
        if ($state['end'] !== null) {
            return true;
        }
        $out = [];
        foreach ([1, 2] as $side) {
            if (in_array(4, $state['actors'][$side]['wounds'], true)) {
                $out[] = $side;
            }
        }
        if ($out !== []) {
            $this->finish($state, count($out) === 2 ? null : 3 - $out[0], count($out) === 2 ? 'mutual_incapacity' : 'incapacity');

            return true;
        }

        return false;
    }

    private function finish(array &$state, ?int $winner, string $reason): void
    {
        $state['end'] = compact('winner', 'reason');
        $state['tasks'] = [];
        $state['phase'] = 'completed';
        $this->event($state, 'completed', null, ['message' => 'Der Übungskampf ist beendet.', 'winner' => $winner, 'reason' => $reason]);
    }

    private function defer(array &$state, array $keys, array $task, array $input): bool
    {
        $missing = array_values(array_diff(array_unique($keys), array_keys($state['rules'])));
        if ($missing === []) {
            return false;
        }
        $this->ensure($state['continuation'] === null, 'Eine andere Regelfrage ist noch offen.');
        $state['continuation'] = ['task' => $task, 'input' => $input, 'phase' => $state['phase']];
        $state['phase'] = 'awaiting_ruling';
        $this->task($state, 'ruling', 0, ['rules' => $missing, 'request' => ['type' => $task['type'], 'side' => $task['side'], 'selection' => $input, 'context' => $task['context']]]);
        $this->event($state, 'ruling_requested', null, ['message' => 'Die AG-Leitung entscheidet die offene Regelauslegung.', 'rules' => $missing]);

        return true;
    }

    private function rule(array &$state, array $task, array $input): void
    {
        if (isset($task['context']['npc_ability']) && ! ($input['abort'] ?? false)) {
            $this->npcRuling($state, $task, $input);

            return;
        }
        if (isset($task['context']['npc_ability']) && ($input['abort'] ?? false) === true) {
            $input = array_intersect_key($input, array_flip(['reason', 'abort']));
        }
        $this->keys($input, ['choices', 'reason', 'abort']);
        if (($input['abort'] ?? false) === true) {
            $this->ensure(is_string($input['reason'] ?? null) && mb_strlen(trim($input['reason'])) >= 3 && mb_strlen($input['reason']) <= 2000, 'Den neutralen Abbruch begründen.');
            $this->event($state, 'ruling', null, ['message' => $input['reason']]);
            $state['continuation'] = null;
            $this->finish($state, null, 'judicial_abort');

            return;
        }
        $this->ensure(is_array($input['choices'] ?? null) && is_string($input['reason'] ?? null)
            && mb_strlen(trim($input['reason'])) >= 3 && mb_strlen($input['reason']) <= 2000, 'Auslegung und Begründung angeben.');
        $this->ensure(count($input['choices']) === count($task['context']['rules']), 'Alle angeforderten Regelfälle entscheiden.');
        foreach ($task['context']['rules'] as $key) {
            $choice = $input['choices'][$key] ?? '';
            $this->ensure(is_string($choice) && array_key_exists($choice, RpgCombatRules::rulings()[$key]['options']), 'Unzulässige Regelauslegung.');
            $state['rules'][$key] = $choice;
        }
        $this->event($state, 'ruling', null, ['message' => $input['reason'], 'choices' => $input['choices']]);
        $next = $state['continuation'];
        $state['continuation'] = null;
        $state['phase'] = $next['phase'];
        try {
            $this->handle($state, $next['task'], $next['input']);
        } catch (InvalidArgumentException $exception) {
            // An adjudication can invalidate a choice, but must never undo the ruling.
            $this->event($state, 'choice_invalidated', $next['task']['side'], ['message' => $exception->getMessage()]);
            $this->task($state, $next['task']['type'], $next['task']['side'], $next['task']['context']);
        }
    }

    private function task(array &$state, string $type, int $side, array $context = []): void
    {
        $token = ++$state['next_token'];
        $controller = $side;
        if ($side && ! in_array($type, ['prepare', 'initiative', 'psychic_resistance'], true)) {
            foreach ($state['actors'][$side]['effects'] as $effect) {
                if ($effect['type'] === 'control' && $effect['expires'] > $state['seconds']) {
                    $controller = $effect['controller'];
                }
            }
        }
        if ($type === 'action' && isset($state['actors'][$side]['profile']['npc']) && $controller !== $side && ! ($context['ordered'] ?? false)) {
            $type = 'npc_order';
        }
        $state['tasks'][$token] = compact('token', 'type', 'side', 'controller', 'context');
    }

    private function event(array $state, string $kind, ?int $side, array $data): void
    {
        $this->events[] = ['round' => $state['round'], 'kind' => $kind, 'side' => $side, 'data' => $data];
    }

    private function roll(array $state, int $side, array $modifiers, string $label, string $pages, int $count = 2): array
    {
        $roll = CombatMath::roll($this->dice->roll($count), $modifiers);
        $this->event($state, 'roll', $side, $roll + ['message' => $label, 'pages' => $pages]);

        return $roll;
    }

    private function output(array $state): array
    {
        return ['state' => $state, 'events' => $this->events];
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new InvalidArgumentException($message);
        }
    }

    private function keys(array $input, array $allowed): void
    {
        $this->ensure(array_diff(array_keys($input), $allowed) === [], 'Unerlaubte Eingaben. Würfel und Ergebnisse berechnet der Server.');
    }
}
