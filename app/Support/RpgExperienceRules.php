<?php

namespace App\Support;

final class RpgExperienceRules
{
    public const VERSION = '2007-ep-1';

    public const ATTRIBUTE_COST = 30;

    public static function skillCost(int $target, bool $psychic = false): int
    {
        return ($target <= 3 ? 6 : ($target <= 5 ? 12 : 18)) * ($psychic ? 2 : 1);
    }

    public static function advantageCost(string $name): int
    {
        return 20 * (RpgCharEditorSpecialRules::advantages()[$name]['cost'] ?? 1);
    }
}
