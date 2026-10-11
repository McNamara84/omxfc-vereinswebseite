<?php

namespace App\Services\RpgCombat;

use App\Models\RpgCharacter;
use App\Models\RpgNpc;
use App\Support\CombatHash;
use App\Support\RpgCharEditorEquipment;
use App\Support\RpgCharEditorSpecialRules;
use App\Support\RpgCombatRules;
use App\Support\RpgNpcCatalog;
use Illuminate\Validation\ValidationException;

final class NpcCombatSnapshotFactory
{
    public function make(RpgNpc $npc): array
    {
        if (! hash_equals($npc->profile_hash, CombatHash::make($npc->profile))) {
            throw ValidationException::withMessages(['npc' => 'Der gespeicherte NSC-Vorlagenstand ist beschädigt.']);
        }

        return array_replace($npc->profile, ['name' => $npc->displayName(), 'revision' => $npc->revision]);
    }

    public function build(array $row, array $configuration): array
    {
        $baseAttributes = $row['attributes'];
        $baseSkills = $row['skills'];
        if ($row['key'] === 'daamure') {
            $baseSkills[$configuration['profession']] = 3;
        }
        if ($row['key'] === 'bandit') {
            $id = $configuration['weapon'];
            $row['items'] = [$id];
            $row['combat'] = [$id => match ($id) {
                'schwert' => [2, 1], 'messer-dolch' => [2, 0], 'zwille' => [2, 0], 'bogen' => [1, 1],
            }];
        }
        $skills = $baseSkills;
        foreach ($configuration['skill_increases'] ?? [] as $name => $increase) {
            $skills[$name] = ($skills[$name] ?? 0) + $increase;
        }
        $attributes = $baseAttributes;
        if ($row['key'] === 'daamure') {
            $attributes = array_replace($attributes, array_diff_key(RpgNpcCatalog::ranks()[$configuration['rank']], ['fp' => true]));
        }
        $equipment = ['items' => array_map(fn ($id) => ['id' => $id, 'quantity' => 1], $row['items'])];
        foreach ($row['items'] as $id) {
            $kind = RpgCharEditorEquipment::itemMap()[$id]['combat']['kind'] ?? null;
            if (in_array($kind, ['armor', 'shield'], true)) {
                $equipment['active_'.$kind.'_id'] = $id;
            }
        }
        $character = new RpgCharacter(['character_name' => $row['name'], 'payload' => [
            'attributes' => $attributes, 'skills' => array_map(fn ($name, $value) => compact('name', 'value'), array_keys($skills), $skills),
            'advantages' => array_values(array_intersect($row['advantages'], array_keys(RpgCharEditorSpecialRules::advantages()))),
            'disadvantages' => array_values(array_intersect($row['disadvantages'], array_keys(RpgCharEditorSpecialRules::disadvantages()))),
            'advantage_counts' => $row['advantage_counts'], 'equipment' => $equipment,
            'advantage_effects' => [],
        ]]);
        $character->revision = 0;
        $profile = app(CombatSnapshotFactory::class)->make($character);
        $profile['advantages'] = $row['advantages'];
        $profile['disadvantages'] = $row['disadvantages'];
        if ($row['key'] === 'aruula') {
            // Page 24 explicitly identifies Lauschen as Telepathie; page 61 fixes FW 2.
            $profile['psychic'] = ['powers' => [['name' => 'Telepathie', 'label' => 'Lauschen (Telepathie)', 'value' => 2, 'usable' => true]], 'pep' => 2];
        }
        if ($row['key'] === 'snaekke') {
            $profile['disadvantage_details']['Verwundbarkeit'] = 'Feuer';
        }
        if ($row['group'] === 'Kreaturen' || $row['key'] === 'taratze') {
            $profile['weapons'] = [];
        }
        foreach ($row['body'] as $index => $body) {
            $instance = 'body:'.$index;
            $profile['weapons'][$instance] = [
                'id' => $instance, 'instance' => $instance, 'name' => $body['name'], 'hands' => 0, 'size' => 'medium', 'education' => 0,
                'capacity' => 0, 'loaded' => 0, 'reserve' => 0, 'natural' => true, 'thrown' => false, 'jammed' => false,
                'broken' => false, 'position' => null, 'fuel' => null, 'progress' => 0,
                'modes' => [['kind' => 'melee', 'skill' => 'Nahkampf', 'attributes' => $body['attributes'], 'damage' => $body['damage'] - $baseAttributes['st'],
                    'precision' => 0, 'type' => '', 'fireRate' => 'E', 'rangeIncrement' => '', 'maxRange' => '',
                    'npc_attack' => $body['attack'], 'npc_damage' => $body['damage'], 'attack_count' => $body['count'], 'runup' => $body['runup'],
                    'swallow' => in_array($row['key'], ['gejagudoo', 'snaekke'], true)]],
            ];
        }
        foreach ($profile['weapons'] as &$weapon) {
            foreach ($weapon['modes'] as &$mode) {
                $published = $row['combat'][$weapon['id']] ?? null;
                if ($published && $mode['kind'] === $weapon['modes'][0]['kind']) {
                    [$mode['npc_attack'], $mode['npc_damage']] = $published;
                }
                if (isset($mode['npc_attack'])) {
                    foreach ($mode['attributes'] as $attribute) {
                        $raw = ($baseSkills[$mode['skill']] ?? 0) + $baseAttributes[$attribute]
                            + ($mode['kind'] === 'ranged' ? $mode['precision'] : ($attribute === 'ge' ? ($profile['armor']['movementModifier'] ?? 0) : 0))
                            - max(0, $weapon['education'] - ($baseSkills['Bildung'] ?? 0));
                        $mode['npc_attack_offsets'][$attribute] = $mode['npc_attack'] - $raw;
                    }
                    $damageAttribute = $mode['kind'] === 'ranged' ? 'wa' : 'st';
                    $mode['npc_damage_offset'] = $mode['npc_damage'] - $baseAttributes[$damageAttribute] - $mode['damage'];
                }
            }
            unset($mode);
        }
        unset($weapon);
        $bm = $profile['armor']['movementModifier'] ?? 0;
        $shield = $profile['shield'] ? 1 : 0;
        $cold = in_array('Kaltblütig', $row['advantages'], true) ? 1 : 0;
        $reflexes = in_array('Kampfreflexe', $row['advantages'], true) ? 2 : 0;
        $profile['npc'] = [
            'template_key' => $row['key'], 'template_name' => $row['key'] === 'maddrax' ? 'Maddrax' : $row['name'],
            'version' => RpgNpcCatalog::VERSION, 'page' => $row['page'], 'rulebook_sha256' => RpgCombatRules::RULEBOOK_SHA256,
            'configuration' => $configuration, 'abilities' => $row['abilities'], 'notes' => $row['notes'],
            'parry_allowed' => $row['group'] !== 'Kreaturen' && $row['key'] !== 'taratze',
            'published_defense' => ['parry' => $row['parry'], 'dodge' => $row['dodge']],
            'defense_offsets' => [
                'dodge' => $row['dodge'] - (($baseSkills['Athletik'] ?? 0) + $baseAttributes['ge'] + $bm + $shield + $cold + $reflexes),
                'parry' => $row['parry'] === null ? 0 : $row['parry'] - (($baseSkills['Nahkampf'] ?? 0) + $baseAttributes['ge'] + $bm + $shield + $cold),
            ],
        ];

        return $profile;
    }
}
