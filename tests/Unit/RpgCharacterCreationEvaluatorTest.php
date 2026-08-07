<?php

namespace Tests\Unit;

use App\Services\RpgCharacterCreationEvaluator;
use PHPUnit\Framework\TestCase;

class RpgCharacterCreationEvaluatorTest extends TestCase
{
    private function evaluate(array $overrides = []): array
    {
        $input = array_replace_recursive([
            'creation_level' => 3,
            'race' => 'Barbar',
            'culture' => 'Landbewohner',
            'gender' => 'maennlich',
            'barbar_attribute_bonus' => 'st',
            'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
            'extra_ap_attribute' => '',
            'advantage_compensation_attributes' => [],
            'negated_racial_disadvantages' => [],
            'advantage_effects' => [],
            'advantages' => ['Zäh'],
            'disadvantages' => [],
        ], $overrides);

        foreach (['advantage_compensation_attributes', 'negated_racial_disadvantages', 'advantage_effects', 'advantages', 'disadvantages'] as $key) {
            if (array_key_exists($key, $overrides)) {
                $input[$key] = $overrides[$key];
            }
        }

        return (new RpgCharacterCreationEvaluator)->evaluate($input);
    }

    public function test_all_five_creation_levels_apply_their_budgets_and_automatic_traits(): void
    {
        $expectations = [
            1 => [0, 0, [], ['Taratzenfutter']],
            2 => [1, 0, [], []],
            3 => [2, 1, ['Zäh'], []],
            4 => [3, 2, ['Zäh'], []],
            5 => [4, 3, ['Zäh'], []],
        ];

        foreach ($expectations as $level => [$attributePoints, $freeAdvantages, $automaticAdvantages, $automaticDisadvantages]) {
            $result = $this->evaluate([
                'creation_level' => $level,
                'attribute_adjustments' => ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
                'advantages' => [],
                'disadvantages' => [],
            ]);

            $this->assertTrue($result['valid'], "Figurenstärke {$level} wurde abgelehnt.");
            $this->assertSame($level, $result['creation_level']);
            $this->assertSame($attributePoints, $result['attribute_budget']['base']);
            $this->assertSame($freeAdvantages, $result['advantage_budget']['free']);
            $this->assertSame($automaticAdvantages, $result['automatic_advantages']);
            $this->assertSame($automaticDisadvantages, $result['automatic_disadvantages']);
            $this->assertEqualsCanonicalizing($automaticDisadvantages, $result['effective_disadvantages']);
        }
    }

    public function test_level_one_toughness_is_paid_and_automatic_taratzenfutter_does_not_compensate_it(): void
    {
        $withoutCompensation = $this->evaluate([
            'creation_level' => 1,
            'attribute_adjustments' => ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
            'advantages' => ['Zäh'],
            'advantage_effects' => [['name' => 'Zäh', 'target' => '', 'justification' => '']],
            'disadvantages' => [],
        ]);
        $withCompensation = $this->evaluate([
            'creation_level' => 1,
            'attribute_adjustments' => ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
            'advantages' => ['Zäh'],
            'advantage_effects' => [['name' => 'Zäh', 'target' => '', 'justification' => '']],
            'disadvantages' => ['Auffällig'],
        ]);

        $this->assertFalse($withoutCompensation['valid']);
        $this->assertSame(['Taratzenfutter'], $withoutCompensation['effective_disadvantages']);
        $this->assertSame(0, $withoutCompensation['advantage_budget']['available_compensations']);
        $this->assertTrue($withCompensation['valid']);
        $this->assertSame(1, $withCompensation['advantage_budget']['required_compensations']);
    }

    public function test_invalid_creation_level_is_rejected(): void
    {
        foreach ([0, 6, 'drei'] as $level) {
            $result = $this->evaluate(['creation_level' => $level]);

            $this->assertFalse($result['valid']);
            $this->assertArrayHasKey('creation_level', $result['errors']);
        }
    }

    public function test_one_advantage_unit_is_free_without_a_disadvantage(): void
    {
        $result = $this->evaluate([
            'advantages' => ['Zäh', 'Schnell'],
            'advantage_effects' => [[
                'name' => 'Schnell',
                'target' => '',
                'justification' => 'Nanotechnologie',
            ]],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(1, $result['advantage_budget']['used']);
        $this->assertSame(0, $result['advantage_budget']['required_compensations']);
    }

    public function test_each_additional_unit_needs_only_a_voluntary_compensation(): void
    {
        $invalid = $this->evaluate([
            'advantages' => ['Zäh', 'Kampfreflexe', 'Kaltblütig'],
            'advantage_effects' => [
                ['name' => 'Kampfreflexe', 'target' => '', 'justification' => ''],
                ['name' => 'Kaltblütig', 'target' => '', 'justification' => ''],
            ],
        ]);
        $valid = $this->evaluate([
            'advantages' => ['Zäh', 'Kampfreflexe', 'Kaltblütig'],
            'disadvantages' => ['Auffällig'],
            'advantage_effects' => [
                ['name' => 'Kampfreflexe', 'target' => '', 'justification' => ''],
                ['name' => 'Kaltblütig', 'target' => '', 'justification' => ''],
            ],
        ]);

        $this->assertFalse($invalid['valid']);
        $this->assertArrayHasKey('disadvantages', $invalid['errors']);
        $this->assertTrue($valid['valid']);
    }

    public function test_gestaltwandler_uses_all_three_units_and_is_valid_with_two_compensations(): void
    {
        $result = $this->evaluate([
            'advantages' => ['Zäh', 'Gestaltwandler'],
            'disadvantages' => ['Auffällig', 'Blutdurst'],
            'advantage_effects' => [['name' => 'Gestaltwandler', 'target' => '', 'justification' => '']],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(3, $result['advantage_budget']['used']);
        $this->assertSame(2, $result['advantage_budget']['required_compensations']);
    }

    public function test_a_fourth_advantage_unit_is_never_available(): void
    {
        $result = $this->evaluate([
            'advantages' => ['Zäh', 'Anführer', 'Kampfreflexe', 'Kaltblütig', 'Kiemen'],
            'disadvantages' => ['Auffällig', 'Blutdurst', 'Lichtscheu'],
            'advantage_effects' => array_map(
                static fn (string $name): array => ['name' => $name, 'target' => '', 'justification' => ''],
                ['Anführer', 'Kampfreflexe', 'Kaltblütig', 'Kiemen'],
            ),
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('advantages', $result['errors']);
    }

    public function test_racial_disadvantages_do_not_compensate_but_can_be_negated(): void
    {
        $guul = $this->evaluate([
            'race' => 'Guul',
            'barbar_attribute_bonus' => '',
            'advantages' => ['Zäh', 'Natürliche Waffen', 'Kampfreflexe', 'Kaltblütig'],
            'disadvantages' => ['Primitiv', 'Gejagt'],
            'advantage_effects' => [
                ['name' => 'Kampfreflexe', 'target' => '', 'justification' => ''],
                ['name' => 'Kaltblütig', 'target' => '', 'justification' => ''],
            ],
        ]);
        $wulfane = $this->evaluate([
            'race' => 'Wulfane',
            'barbar_attribute_bonus' => '',
            'negated_racial_disadvantages' => ['Ehrenkodex'],
            'disadvantages' => [],
        ]);

        $this->assertFalse($guul['valid']);
        $this->assertSame(0, $guul['advantage_budget']['available_compensations']);
        $this->assertTrue($wulfane['valid']);
        $this->assertSame([], $wulfane['effective_disadvantages']);
        $this->assertSame(1, $wulfane['advantage_budget']['used']);
    }

    public function test_extra_ap_and_advantage_compensation_require_distinct_voluntary_reductions(): void
    {
        $valid = $this->evaluate([
            'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 1, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => -1],
            'extra_ap_attribute' => 'au',
        ]);
        $doubleUsed = $this->evaluate([
            'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 1, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => -1],
            'extra_ap_attribute' => 'au',
            'advantage_compensation_attributes' => ['au'],
        ]);
        $racialPenalty = $this->evaluate([
            'race' => 'Techno',
            'culture' => 'Bunkermensch',
            'barbar_attribute_bonus' => '',
            'advantages' => ['Zäh', 'High-Tech-Ausrüstung', 'Kampfreflexe', 'Kaltblütig'],
            'disadvantages' => ['Tödliche Immunschwäche'],
            'advantage_compensation_attributes' => ['st'],
            'advantage_effects' => [
                ['name' => 'Kampfreflexe', 'target' => '', 'justification' => ''],
                ['name' => 'Kaltblütig', 'target' => '', 'justification' => ''],
            ],
        ]);

        $this->assertTrue($valid['valid']);
        $this->assertSame(3, $valid['attribute_budget']['used']);
        $this->assertFalse($doubleUsed['valid']);
        $this->assertArrayHasKey('attribute_adjustments', $doubleUsed['errors']);
        $this->assertFalse($racialPenalty['valid']);
        $this->assertArrayHasKey('advantage_compensation_attributes', $racialPenalty['errors']);
    }

    public function test_enhanced_attribute_changes_final_value_and_enforces_unique_targets_and_cap(): void
    {
        $valid = $this->evaluate([
            'advantages' => ['Zäh', 'Gesteigertes Attribut'],
            'advantage_effects' => [[
                'name' => 'Gesteigertes Attribut',
                'target' => 'wi',
                'justification' => 'Mutation',
            ]],
        ]);
        $duplicate = $this->evaluate([
            'advantages' => ['Zäh', 'Gesteigertes Attribut'],
            'disadvantages' => ['Auffällig'],
            'advantage_effects' => [
                ['name' => 'Gesteigertes Attribut', 'target' => 'wi', 'justification' => 'Mutation'],
                ['name' => 'Gesteigertes Attribut', 'target' => 'wi', 'justification' => 'Implantat'],
            ],
        ]);
        $overCap = $this->evaluate([
            'advantages' => ['Zäh', 'Gesteigertes Attribut'],
            'advantage_effects' => [[
                'name' => 'Gesteigertes Attribut',
                'target' => 'st',
                'justification' => 'Mutation',
            ]],
        ]);

        $this->assertTrue($valid['valid']);
        $this->assertSame(1, $valid['attributes']['final']['wi']);
        $this->assertFalse($duplicate['valid']);
        $this->assertFalse($overCap['valid']);
        $this->assertArrayHasKey('attributes', $overCap['errors']);
    }

    public function test_repeatable_effects_and_required_justifications_are_validated(): void
    {
        $valid = $this->evaluate([
            'advantages' => ['Zäh', 'Regeneration'],
            'disadvantages' => ['Auffällig'],
            'advantage_effects' => [
                ['name' => 'Regeneration', 'target' => '', 'justification' => 'Naniten'],
                ['name' => 'Regeneration', 'target' => '', 'justification' => 'Mutation'],
            ],
        ]);
        $missingReason = $this->evaluate([
            'advantages' => ['Zäh', 'Schnell'],
            'advantage_effects' => [['name' => 'Schnell', 'target' => '', 'justification' => '']],
        ]);

        $this->assertTrue($valid['valid']);
        $this->assertSame(2, $valid['advantage_budget']['used']);
        $this->assertFalse($missingReason['valid']);
        $this->assertArrayHasKey('advantage_effects', $missingReason['errors']);
    }

    public function test_female_volk_der_dreizehn_inseln_pays_for_telepathy(): void
    {
        $missing = $this->evaluate([
            'culture' => 'Volk der 13 Inseln',
            'gender' => 'weiblich',
        ]);
        $valid = $this->evaluate([
            'culture' => 'Volk der 13 Inseln',
            'gender' => 'weiblich',
            'advantages' => ['Zäh', 'Psychische Kraft'],
            'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => 'Telepathie', 'justification' => '']],
        ]);

        $this->assertFalse($missing['valid']);
        $this->assertTrue($valid['valid']);
        $this->assertSame(1, $valid['advantage_budget']['used']);
    }

    public function test_two_distinct_attribute_reductions_can_compensate_two_additional_units(): void
    {
        $result = $this->evaluate([
            'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => -1, 'wi' => -1, 'wa' => 0, 'in' => 0, 'au' => 0],
            'advantage_compensation_attributes' => ['ro', 'wi'],
            'advantages' => ['Zäh', 'Anführer', 'Kampfreflexe', 'Kaltblütig'],
            'advantage_effects' => [
                ['name' => 'Anführer', 'target' => '', 'justification' => ''],
                ['name' => 'Kampfreflexe', 'target' => '', 'justification' => ''],
                ['name' => 'Kaltblütig', 'target' => '', 'justification' => ''],
            ],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(2, $result['advantage_budget']['required_compensations']);
        $this->assertSame(2, $result['advantage_budget']['available_compensations']);
    }

    public function test_enhanced_attribute_may_offset_the_reduction_used_as_compensation(): void
    {
        $result = $this->evaluate([
            'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => -1, 'wa' => 0, 'in' => 0, 'au' => 0],
            'advantage_compensation_attributes' => ['wi'],
            'advantages' => ['Zäh', 'Gesteigertes Attribut', 'Kaltblütig'],
            'advantage_effects' => [
                ['name' => 'Gesteigertes Attribut', 'target' => 'wi', 'justification' => 'Mutation'],
                ['name' => 'Kaltblütig', 'target' => '', 'justification' => ''],
            ],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(-1, $result['attributes']['creation_adjustments']['wi']);
        $this->assertSame(1, $result['attributes']['advantage_bonuses']['wi']);
        $this->assertSame(0, $result['attributes']['final']['wi']);
    }

    public function test_unique_target_and_stack_repeat_modes_are_enforced(): void
    {
        $twoSenses = $this->evaluate([
            'advantages' => ['Zäh', 'Gesteigerter Sinn'],
            'disadvantages' => ['Auffällig'],
            'advantage_effects' => [
                ['name' => 'Gesteigerter Sinn', 'target' => 'Sehen', 'justification' => 'Implantat'],
                ['name' => 'Gesteigerter Sinn', 'target' => 'Hören', 'justification' => 'Mutation'],
            ],
        ]);
        $duplicateSense = $this->evaluate([
            'advantages' => ['Zäh', 'Gesteigerter Sinn'],
            'disadvantages' => ['Auffällig'],
            'advantage_effects' => [
                ['name' => 'Gesteigerter Sinn', 'target' => 'Sehen', 'justification' => 'Implantat'],
                ['name' => 'Gesteigerter Sinn', 'target' => 'Sehen', 'justification' => 'Mutation'],
            ],
        ]);
        $threeArmorLayers = $this->evaluate([
            'advantages' => ['Zäh', 'Panzerung'],
            'disadvantages' => ['Auffällig', 'Blutdurst'],
            'advantage_effects' => [
                ['name' => 'Panzerung', 'target' => '', 'justification' => 'Hornplatten'],
                ['name' => 'Panzerung', 'target' => '', 'justification' => 'Schuppen'],
                ['name' => 'Panzerung', 'target' => '', 'justification' => 'Naniten'],
            ],
        ]);

        $this->assertTrue($twoSenses['valid']);
        $this->assertFalse($duplicateSense['valid']);
        $this->assertArrayHasKey('advantage_effects', $duplicateSense['errors']);
        $this->assertTrue($threeArmorLayers['valid']);
        $this->assertSame(3, $threeArmorLayers['advantage_budget']['used']);
    }

    public function test_psychic_power_requires_one_of_the_six_rulebook_targets(): void
    {
        foreach (['Beherrschung', 'Empathie', 'Gedankenschild', 'Pyrokinese', 'Telepathie', 'Telekinese'] as $power) {
            $result = $this->evaluate([
                'advantages' => ['Zäh', 'Psychische Kraft'],
                'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => $power, 'justification' => '']],
            ]);

            $this->assertTrue($result['valid'], "Die psychische Kraft {$power} wurde abgelehnt.");
        }

        $invalid = $this->evaluate([
            'advantages' => ['Zäh', 'Psychische Kraft'],
            'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => 'Zeitreise', 'justification' => '']],
        ]);

        $this->assertFalse($invalid['valid']);
        $this->assertArrayHasKey('advantage_effects', $invalid['errors']);
    }

    public function test_effect_instances_reject_unselected_names_and_targets_for_targetless_rules(): void
    {
        $unselected = $this->evaluate([
            'advantage_effects' => [['name' => 'Kiemen', 'target' => '', 'justification' => '']],
        ]);
        $unexpectedTarget = $this->evaluate([
            'advantages' => ['Zäh', 'Anführer'],
            'advantage_effects' => [['name' => 'Anführer', 'target' => 'ST', 'justification' => '']],
        ]);

        $this->assertFalse($unselected['valid']);
        $this->assertArrayHasKey('advantage_effects', $unselected['errors']);
        $this->assertFalse($unexpectedTarget['valid']);
        $this->assertArrayHasKey('advantage_effects', $unexpectedTarget['errors']);
    }

    public function test_all_racial_advantages_and_disadvantages_are_derived_without_budget_compensation(): void
    {
        $cases = [
            'Guul' => [
                'advantages' => ['Natürliche Waffen'],
                'disadvantages' => ['Primitiv', 'Gejagt'],
                'final' => ['au' => -1],
            ],
            'Hydrit' => [
                'advantages' => ['Kiemen', 'Natürliche Waffen'],
                'disadvantages' => ['Anfälligkeit gegen Wahnsinn'],
                'final' => [],
            ],
            'Nosfera' => [
                'advantages' => ['Nachtsicht'],
                'disadvantages' => ['Blutdurst', 'Lichtscheu', 'Gejagt'],
                'final' => ['ge' => 1, 'au' => -1],
            ],
            'Taratze' => [
                'advantages' => [],
                'disadvantages' => ['Auffällig', 'Primitiv', 'Gejagt'],
                'final' => ['st' => 1, 'wa' => 1, 'in' => -1, 'au' => -1],
            ],
            'Wulfane' => [
                'advantages' => [],
                'disadvantages' => ['Ehrenkodex'],
                'final' => ['ro' => 1, 'au' => -1],
            ],
            'Techno' => [
                'advantages' => ['High-Tech-Ausrüstung'],
                'disadvantages' => ['Tödliche Immunschwäche'],
                'final' => ['st' => -1, 'ro' => -1, 'in' => 1],
            ],
            'Präkristofluu' => [
                'advantages' => ['High-Tech-Ausrüstung'],
                'disadvantages' => [],
                'final' => [],
            ],
        ];

        foreach ($cases as $race => $expected) {
            $result = $this->evaluate([
                'race' => $race,
                'culture' => 'Landbewohner',
                'barbar_attribute_bonus' => '',
                'attribute_adjustments' => ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
                'advantages' => ['Zäh'],
                'disadvantages' => $expected['disadvantages'],
            ]);

            $this->assertTrue($result['valid'], "Die automatischen Regeln für {$race} sind nicht gültig.");
            $this->assertSame(['Zäh', ...$expected['advantages']], $result['effective_advantages']);
            $this->assertSame($expected['disadvantages'], $result['effective_disadvantages']);
            $this->assertSame(0, $result['advantage_budget']['available_compensations']);
            foreach ($expected['final'] as $attribute => $value) {
                $this->assertSame($value, $result['attributes']['final'][$attribute]);
            }
        }
    }

    public function test_only_actual_racial_disadvantages_can_be_negated_and_not_remain_active(): void
    {
        $notRacial = $this->evaluate([
            'race' => 'Wulfane',
            'barbar_attribute_bonus' => '',
            'disadvantages' => ['Ehrenkodex'],
            'negated_racial_disadvantages' => ['Auffällig'],
        ]);
        $bothActiveAndNegated = $this->evaluate([
            'race' => 'Wulfane',
            'barbar_attribute_bonus' => '',
            'disadvantages' => ['Ehrenkodex'],
            'negated_racial_disadvantages' => ['Ehrenkodex'],
        ]);

        $this->assertFalse($notRacial['valid']);
        $this->assertArrayHasKey('negated_racial_disadvantages', $notRacial['errors']);
        $this->assertFalse($bothActiveAndNegated['valid']);
        $this->assertArrayHasKey('negated_racial_disadvantages', $bothActiveAndNegated['errors']);
    }

    public function test_malformed_and_incomplete_evaluator_inputs_are_rejected_defensively(): void
    {
        $cases = [
            'attribute budget overflow' => [
                ['attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 1, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0]],
                'attributes',
            ],
            'unknown advantage' => [
                ['advantages' => ['Zäh', 'Laserblick']],
                'advantages',
            ],
            'missing racial disadvantage' => [
                ['race' => 'Guul', 'barbar_attribute_bonus' => '', 'disadvantages' => []],
                'disadvantages',
            ],
            'invalid adjustment' => [
                ['attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 2, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0]],
                'attribute_adjustments',
            ],
            'missing barbar target' => [
                ['barbar_attribute_bonus' => ''],
                'barbar_attribute_bonus',
            ],
            'non-list effects' => [
                ['advantage_effects' => 'Schnell'],
                'advantage_effects',
            ],
            'non-structured effect' => [
                ['advantage_effects' => ['Schnell']],
                'advantage_effects',
            ],
            'invalid extra AP source' => [
                ['extra_ap_attribute' => 'st'],
                'extra_ap_attribute',
            ],
            'too many compensation attributes' => [
                [
                    'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => -1, 'wi' => -1, 'wa' => -1, 'in' => 0, 'au' => 0],
                    'advantage_compensation_attributes' => ['ro', 'wi', 'wa'],
                ],
                'advantage_compensation_attributes',
            ],
            'missing animal companion detail' => [
                [
                    'advantages' => ['Zäh', 'Tiergefährte'],
                    'advantage_effects' => [['name' => 'Tiergefährte', 'target' => '', 'justification' => '']],
                ],
                'advantage_effects',
            ],
            'missing selected advantage effect' => [
                ['advantages' => ['Zäh', 'Anführer']],
                'advantage_effects',
            ],
            'duplicate non-repeatable effect' => [
                [
                    'advantages' => ['Zäh', 'Anführer'],
                    'advantage_effects' => [
                        ['name' => 'Anführer', 'target' => '', 'justification' => ''],
                        ['name' => 'Anführer', 'target' => '', 'justification' => ''],
                    ],
                ],
                'advantage_effects',
            ],
            'non-list selections' => [
                ['advantages' => 'Zäh', 'disadvantages' => null],
                null,
            ],
        ];

        foreach ($cases as $label => [$overrides, $expectedError]) {
            $result = $this->evaluate($overrides);

            if ($expectedError === null) {
                $this->assertTrue($result['valid'], "Die robuste Normalisierung für {$label} ist fehlgeschlagen.");

                continue;
            }

            $this->assertFalse($result['valid'], "Der ungültige Fall {$label} wurde akzeptiert.");
            $this->assertArrayHasKey($expectedError, $result['errors'], "Für {$label} fehlt der erwartete Fehler.");
        }
    }
}
