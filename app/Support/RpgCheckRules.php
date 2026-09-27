<?php

namespace App\Support;

use App\Services\RpgCharacterSheetService;

final class RpgCheckRules
{
    public const VERSION = 'maddrax-2007-checks-v1';

    public const DIFFICULTIES = ['Leicht' => 7, 'Mittel' => 10, 'Schwer' => 11, 'Extrem' => 12, 'Legendär' => 14];

    public static function attributes(): array
    {
        return array_column(RpgCharacterSheetService::attributeRuleConfig()['attributes'], 'label', 'id');
    }

    public static function skills(array $payload = []): array
    {
        $names = new RpgCharacterSheetService;
        $skills = [...array_column(RpgCharacterSheetService::skillRuleConfig()['skills'], 'name'),
            ...array_column($payload['skills'] ?? [], 'name')];

        return array_values(array_diff(array_unique(array_map($names->canonicalSkillName(...), $skills)),
            RpgCharEditorSpecialRules::PSYCHIC_POWER_TARGETS));
    }
}
