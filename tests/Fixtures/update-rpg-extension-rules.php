<?php

use App\Support\RpgCharEditorRuleCatalog;
use App\Support\RpgCharEditorTraining;

require __DIR__.'/../../vendor/autoload.php';

$config = [
    'ruleCatalog' => RpgCharEditorRuleCatalog::ruleConfig(),
    'trainingRules' => RpgCharEditorTraining::ruleConfig(),
];

file_put_contents(__DIR__.'/rpg-extension-rules.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
