<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCharacterCreationEvaluator;

trait RpgCharacterCreationEvaluatorTestFixtures
{
    private function evaluate(array $overrides = []): array
    {
        $input = array_replace_recursive(['creation_level' => 3, 'race' => 'Barbar', 'culture' => 'Landbewohner', 'gender' => 'maennlich', 'barbar_attribute_bonus' => 'st', 'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'extra_ap_attribute' => '', 'advantage_compensation_attributes' => [], 'negated_racial_disadvantages' => [], 'advantage_effects' => [], 'advantages' => ['Zäh'], 'disadvantages' => []], $overrides);
        foreach (['advantage_compensation_attributes', 'negated_racial_disadvantages', 'advantage_effects', 'advantages', 'disadvantages'] as $key) {
            if (array_key_exists($key, $overrides)) {
                $input[$key] = $overrides[$key];
            }
        }

        return (new RpgCharacterCreationEvaluator)->evaluate($input);
    }
}
