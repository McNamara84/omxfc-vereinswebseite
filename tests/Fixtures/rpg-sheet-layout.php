<?php

use App\Support\RpgCharEditorRuleCatalog;

return [
    'character' => ['character_name' => 'Marsianische Testfigur', 'player_name' => 'Regeltest', 'race' => 'Marsianer', 'culture' => 'Marsianische Städter', 'gender' => 'divers', 'description' => str_repeat('Technisch versiert und weit gereist. ', 7)],
    'rules' => ['creation_level' => 3, 'sources' => RpgCharEditorRuleCatalog::snapshots(['expansion-1' => true])],
    'attributes' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
    'skills' => [['name' => 'Bildung', 'value' => 3], ['name' => 'Techniker', 'value' => 3]],
    'advantages' => ['Zäh', 'Scharfschütze', 'Regeneration', 'Schnell', 'Beidhändig', 'Kaltblütig'],
    'disadvantages' => ['Lichtscheu'],
    'equipment' => [
        'clothing' => ['name' => 'Kleidung, Wanderer'],
        'items' => [
            ['id' => 'bogen', 'name' => 'Bogen', 'quantity' => 1],
            ['id' => 'schwert', 'name' => 'Schwert', 'quantity' => 1],
            ['id' => 'verstaerktes-leder', 'name' => 'Verstärktes Leder', 'quantity' => 1],
            ['id' => 'holzschild', 'name' => 'Holzschild', 'quantity' => 1],
            ['id' => 'seil', 'name' => 'Seil', 'quantity' => 1],
            ['id' => 'rucksack', 'name' => 'Rucksack', 'quantity' => 1],
        ],
        'active_armor_id' => 'verstaerktes-leder',
        'active_shield_id' => 'holzschild',
        'ammunition' => [['source' => 'Bogen', 'quantity' => 30, 'unit' => 'Pfeile']],
        'notes' => str_repeat('Wasser auffüllen, Vorräte prüfen und beschädigte Ausrüstung reparieren. ', 3),
    ],
];
