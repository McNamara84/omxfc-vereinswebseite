<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class)->use(RpgCharacterCreationEvaluatorTestFixtures::class);

test('all five creation levels apply their budgets and automatic traits', function (): void {
    $expectations = [1 => [0, 0, [], ['Taratzenfutter']], 2 => [1, 0, [], []], 3 => [2, 1, ['Zäh'], []], 4 => [3, 2, ['Zäh'], []], 5 => [4, 3, ['Zäh'], []]];
    foreach ($expectations as $level => [$attributePoints, $freeAdvantages, $automaticAdvantages, $automaticDisadvantages]) {
        $adjustments = ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0];
        foreach (array_slice(array_keys($adjustments), 0, $attributePoints) as $attribute) {
            $adjustments[$attribute] = 1;
        }
        $result = $this->evaluate(['creation_level' => $level, 'attribute_adjustments' => $adjustments, 'advantages' => [], 'disadvantages' => []]);
        expect($result['valid'])->toBeTrue()
            ->and($result['creation_level'])->toBe($level)
            ->and($result['attribute_budget']['base'])->toBe($attributePoints)
            ->and($result['advantage_budget']['free'])->toBe($freeAdvantages)
            ->and($result['automatic_advantages'])->toBe($automaticAdvantages)
            ->and($result['automatic_disadvantages'])->toBe($automaticDisadvantages)
            ->and($result['effective_disadvantages'])->toEqualCanonicalizing($automaticDisadvantages);
    }
});

test('unspent attribute points are rejected for the selected level', function (): void {
    $result = $this->evaluate(['creation_level' => 4]);
    expect($result['valid'])->toBeFalse()
        ->and($result['attribute_budget']['remaining'])->toBe(1)
        ->and($result['errors']['attributes'])->toContain('Die verfügbaren Attributspunkte müssen vollständig verteilt werden.');
});

test('level one toughness is paid and automatic taratzenfutter does not compensate it', function (): void {
    $withoutCompensation = $this->evaluate(['creation_level' => 1, 'attribute_adjustments' => ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'advantages' => ['Zäh'], 'advantage_effects' => [['name' => 'Zäh', 'target' => '', 'justification' => '']], 'disadvantages' => []]);
    $withCompensation = $this->evaluate(['creation_level' => 1, 'attribute_adjustments' => ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'advantages' => ['Zäh'], 'advantage_effects' => [['name' => 'Zäh', 'target' => '', 'justification' => '']], 'disadvantages' => ['Auffällig']]);
    expect($withoutCompensation['valid'])->toBeFalse()
        ->and($withoutCompensation['effective_disadvantages'])->toBe(['Taratzenfutter'])
        ->and($withoutCompensation['advantage_budget']['available_compensations'])->toBe(0)
        ->and($withCompensation['valid'])->toBeTrue()
        ->and($withCompensation['advantage_budget']['required_compensations'])->toBe(1);
});

test('invalid creation level is rejected', function (): void {
    foreach ([0, 6, 'drei'] as $level) {
        $result = $this->evaluate(['creation_level' => $level]);
        expect($result['valid'])->toBeFalse()
            ->and($result['errors'])->toHaveKey('creation_level');
    }
});

test('one advantage unit is free without a disadvantage', function (): void {
    $result = $this->evaluate(['advantages' => ['Zäh', 'Schnell'], 'advantage_effects' => [['name' => 'Schnell', 'target' => '', 'justification' => 'Nanotechnologie']]]);
    expect($result['valid'])->toBeTrue()
        ->and($result['advantage_budget']['used'])->toBe(1)
        ->and($result['advantage_budget']['required_compensations'])->toBe(0);
});

test('each additional unit needs only a voluntary compensation', function (): void {
    $invalid = $this->evaluate(['advantages' => ['Zäh', 'Kampfreflexe', 'Kaltblütig'], 'advantage_effects' => [['name' => 'Kampfreflexe', 'target' => '', 'justification' => ''], ['name' => 'Kaltblütig', 'target' => '', 'justification' => '']]]);
    $valid = $this->evaluate(['advantages' => ['Zäh', 'Kampfreflexe', 'Kaltblütig'], 'disadvantages' => ['Auffällig'], 'advantage_effects' => [['name' => 'Kampfreflexe', 'target' => '', 'justification' => ''], ['name' => 'Kaltblütig', 'target' => '', 'justification' => '']]]);
    expect($invalid['valid'])->toBeFalse()
        ->and($invalid['errors'])->toHaveKey('disadvantages')
        ->and($valid['valid'])->toBeTrue();
});

test('gestaltwandler uses all three units and is valid with two compensations', function (): void {
    $result = $this->evaluate(['advantages' => ['Zäh', 'Gestaltwandler'], 'disadvantages' => ['Auffällig', 'Blutdurst'], 'advantage_effects' => [['name' => 'Gestaltwandler', 'target' => '', 'justification' => '']]]);
    expect($result['valid'])->toBeTrue()
        ->and($result['advantage_budget']['used'])->toBe(3)
        ->and($result['advantage_budget']['required_compensations'])->toBe(2);
});

test('a fourth advantage unit is never available', function (): void {
    $result = $this->evaluate(['advantages' => ['Zäh', 'Anführer', 'Kampfreflexe', 'Kaltblütig', 'Kiemen'], 'disadvantages' => ['Auffällig', 'Blutdurst', 'Lichtscheu'], 'advantage_effects' => array_map(static fn (string $name): array => ['name' => $name, 'target' => '', 'justification' => ''], ['Anführer', 'Kampfreflexe', 'Kaltblütig', 'Kiemen'])]);
    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('advantages');
});

test('racial disadvantages do not compensate but can be negated', function (): void {
    $guul = $this->evaluate(['race' => 'Guul', 'barbar_attribute_bonus' => '', 'advantages' => ['Zäh', 'Natürliche Waffen', 'Kampfreflexe', 'Kaltblütig'], 'disadvantages' => ['Primitiv', 'Gejagt'], 'advantage_effects' => [['name' => 'Kampfreflexe', 'target' => '', 'justification' => ''], ['name' => 'Kaltblütig', 'target' => '', 'justification' => '']]]);
    $wulfane = $this->evaluate(['race' => 'Wulfane', 'barbar_attribute_bonus' => '', 'negated_racial_disadvantages' => ['Ehrenkodex'], 'disadvantages' => []]);
    expect($guul['valid'])->toBeFalse()
        ->and($guul['advantage_budget']['available_compensations'])->toBe(0)
        ->and($wulfane['valid'])->toBeTrue()
        ->and($wulfane['effective_disadvantages'])->toBe([])
        ->and($wulfane['advantage_budget']['used'])->toBe(1);
});

test('extra ap and advantage compensation require distinct voluntary reductions', function (): void {
    $valid = $this->evaluate(['attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 1, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => -1], 'extra_ap_attribute' => 'au']);
    $doubleUsed = $this->evaluate(['attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 1, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => -1], 'extra_ap_attribute' => 'au', 'advantage_compensation_attributes' => ['au']]);
    $racialPenalty = $this->evaluate(['race' => 'Techno', 'culture' => 'Bunkermensch', 'barbar_attribute_bonus' => '', 'advantages' => ['Zäh', 'High-Tech-Ausrüstung', 'Kampfreflexe', 'Kaltblütig'], 'disadvantages' => ['Tödliche Immunschwäche'], 'advantage_compensation_attributes' => ['st'], 'advantage_effects' => [['name' => 'Kampfreflexe', 'target' => '', 'justification' => ''], ['name' => 'Kaltblütig', 'target' => '', 'justification' => '']]]);
    expect($valid['valid'])->toBeTrue()
        ->and($valid['attribute_budget']['used'])->toBe(3)
        ->and($doubleUsed['valid'])->toBeFalse()
        ->and($doubleUsed['errors'])->toHaveKey('attribute_adjustments')
        ->and($racialPenalty['valid'])->toBeFalse()
        ->and($racialPenalty['errors'])->toHaveKey('advantage_compensation_attributes');
});

test('enhanced attribute changes final value and enforces unique targets and cap', function (): void {
    $valid = $this->evaluate(['advantages' => ['Zäh', 'Gesteigertes Attribut'], 'advantage_effects' => [['name' => 'Gesteigertes Attribut', 'target' => 'wi', 'justification' => 'Mutation']]]);
    $duplicate = $this->evaluate(['advantages' => ['Zäh', 'Gesteigertes Attribut'], 'disadvantages' => ['Auffällig'], 'advantage_effects' => [['name' => 'Gesteigertes Attribut', 'target' => 'wi', 'justification' => 'Mutation'], ['name' => 'Gesteigertes Attribut', 'target' => 'wi', 'justification' => 'Implantat']]]);
    $overCap = $this->evaluate(['advantages' => ['Zäh', 'Gesteigertes Attribut'], 'advantage_effects' => [['name' => 'Gesteigertes Attribut', 'target' => 'st', 'justification' => 'Mutation']]]);
    expect($valid['valid'])->toBeTrue()
        ->and($valid['attributes']['final']['wi'])->toBe(1)
        ->and($duplicate['valid'])->toBeFalse()
        ->and($overCap['valid'])->toBeFalse()
        ->and($overCap['errors'])->toHaveKey('attributes');
});

test('repeatable effects and required justifications are validated', function (): void {
    $valid = $this->evaluate(['advantages' => ['Zäh', 'Regeneration'], 'disadvantages' => ['Auffällig'], 'advantage_effects' => [['name' => 'Regeneration', 'target' => '', 'justification' => 'Naniten'], ['name' => 'Regeneration', 'target' => '', 'justification' => 'Mutation']]]);
    $missingReason = $this->evaluate(['advantages' => ['Zäh', 'Schnell'], 'advantage_effects' => [['name' => 'Schnell', 'target' => '', 'justification' => '']]]);
    expect($valid['valid'])->toBeTrue()
        ->and($valid['advantage_budget']['used'])->toBe(2)
        ->and($missingReason['valid'])->toBeFalse()
        ->and($missingReason['errors'])->toHaveKey('advantage_effects');
});

test('female volk der dreizehn inseln pays for telepathy', function (): void {
    $missing = $this->evaluate(['culture' => 'Volk der 13 Inseln', 'gender' => 'weiblich']);
    $valid = $this->evaluate(['culture' => 'Volk der 13 Inseln', 'gender' => 'weiblich', 'advantages' => ['Zäh', 'Psychische Kraft'], 'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => 'Telepathie', 'justification' => '']]]);
    expect($missing['valid'])->toBeFalse()
        ->and($valid['valid'])->toBeTrue()
        ->and($valid['advantage_budget']['used'])->toBe(1);
});

test('two distinct attribute reductions can compensate two additional units', function (): void {
    $result = $this->evaluate(['attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => -1, 'wi' => -1, 'wa' => 0, 'in' => 0, 'au' => 0], 'advantage_compensation_attributes' => ['ro', 'wi'], 'advantages' => ['Zäh', 'Anführer', 'Kampfreflexe', 'Kaltblütig'], 'advantage_effects' => [['name' => 'Anführer', 'target' => '', 'justification' => ''], ['name' => 'Kampfreflexe', 'target' => '', 'justification' => ''], ['name' => 'Kaltblütig', 'target' => '', 'justification' => '']]]);
    expect($result['valid'])->toBeTrue()
        ->and($result['advantage_budget']['required_compensations'])->toBe(2)
        ->and($result['advantage_budget']['available_compensations'])->toBe(2);
});

test('enhanced attribute may offset the reduction used as compensation', function (): void {
    $result = $this->evaluate(['attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => -1, 'wa' => 0, 'in' => 0, 'au' => 0], 'advantage_compensation_attributes' => ['wi'], 'advantages' => ['Zäh', 'Gesteigertes Attribut', 'Kaltblütig'], 'advantage_effects' => [['name' => 'Gesteigertes Attribut', 'target' => 'wi', 'justification' => 'Mutation'], ['name' => 'Kaltblütig', 'target' => '', 'justification' => '']]]);
    expect($result['valid'])->toBeTrue()
        ->and($result['attributes']['creation_adjustments']['wi'])->toBe(-1)
        ->and($result['attributes']['advantage_bonuses']['wi'])->toBe(1)
        ->and($result['attributes']['final']['wi'])->toBe(0);
});

test('unique target and stack repeat modes are enforced', function (): void {
    $twoSenses = $this->evaluate(['advantages' => ['Zäh', 'Gesteigerter Sinn'], 'disadvantages' => ['Auffällig'], 'advantage_effects' => [['name' => 'Gesteigerter Sinn', 'target' => 'Sehen', 'justification' => 'Implantat'], ['name' => 'Gesteigerter Sinn', 'target' => 'Hören', 'justification' => 'Mutation']]]);
    $duplicateSense = $this->evaluate(['advantages' => ['Zäh', 'Gesteigerter Sinn'], 'disadvantages' => ['Auffällig'], 'advantage_effects' => [['name' => 'Gesteigerter Sinn', 'target' => 'Sehen', 'justification' => 'Implantat'], ['name' => 'Gesteigerter Sinn', 'target' => 'Sehen', 'justification' => 'Mutation']]]);
    $threeArmorLayers = $this->evaluate(['advantages' => ['Zäh', 'Panzerung'], 'disadvantages' => ['Auffällig', 'Blutdurst'], 'advantage_effects' => [['name' => 'Panzerung', 'target' => '', 'justification' => 'Hornplatten'], ['name' => 'Panzerung', 'target' => '', 'justification' => 'Schuppen'], ['name' => 'Panzerung', 'target' => '', 'justification' => 'Naniten']]]);
    expect($twoSenses['valid'])->toBeTrue()
        ->and($duplicateSense['valid'])->toBeFalse()
        ->and($duplicateSense['errors'])->toHaveKey('advantage_effects')
        ->and($threeArmorLayers['valid'])->toBeTrue()
        ->and($threeArmorLayers['advantage_budget']['used'])->toBe(3);
});

test('psychic power requires one of the six rulebook targets', function (): void {
    foreach (['Beherrschung', 'Empathie', 'Gedankenschild', 'Pyrokinese', 'Telepathie', 'Telekinese'] as $power) {
        $result = $this->evaluate(['advantages' => ['Zäh', 'Psychische Kraft'], 'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => $power, 'justification' => '']]]);
        expect($result['valid'])->toBeTrue();
    }
    $invalid = $this->evaluate(['advantages' => ['Zäh', 'Psychische Kraft'], 'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => 'Zeitreise', 'justification' => '']]]);
    expect($invalid['valid'])->toBeFalse()
        ->and($invalid['errors'])->toHaveKey('advantage_effects');
});

test('effect instances reject unselected names and targets for targetless rules', function (): void {
    $unselected = $this->evaluate(['advantage_effects' => [['name' => 'Kiemen', 'target' => '', 'justification' => '']]]);
    $unexpectedTarget = $this->evaluate(['advantages' => ['Zäh', 'Anführer'], 'advantage_effects' => [['name' => 'Anführer', 'target' => 'ST', 'justification' => '']]]);
    expect($unselected['valid'])->toBeFalse()
        ->and($unselected['errors'])->toHaveKey('advantage_effects')
        ->and($unexpectedTarget['valid'])->toBeFalse()
        ->and($unexpectedTarget['errors'])->toHaveKey('advantage_effects');
});

test('all racial advantages and disadvantages are derived without budget compensation', function (): void {
    $cases = ['Guul' => ['advantages' => ['Natürliche Waffen'], 'disadvantages' => ['Primitiv', 'Gejagt'], 'final' => ['au' => -1]], 'Hydrit' => ['advantages' => ['Kiemen', 'Natürliche Waffen'], 'disadvantages' => ['Anfälligkeit gegen Wahnsinn'], 'final' => []], 'Nosfera' => ['advantages' => ['Nachtsicht'], 'disadvantages' => ['Blutdurst', 'Lichtscheu', 'Gejagt'], 'final' => ['ge' => 2, 'au' => -1]], 'Taratze' => ['advantages' => [], 'disadvantages' => ['Auffällig', 'Primitiv', 'Gejagt'], 'final' => ['st' => 1, 'wa' => 1, 'in' => -1, 'au' => -1]], 'Wulfane' => ['advantages' => [], 'disadvantages' => ['Ehrenkodex'], 'final' => ['ro' => 1, 'au' => -1]], 'Techno' => ['advantages' => ['High-Tech-Ausrüstung'], 'disadvantages' => ['Tödliche Immunschwäche'], 'final' => ['st' => -1, 'ro' => -1, 'in' => 1]], 'Präkristofluu' => ['advantages' => ['High-Tech-Ausrüstung'], 'disadvantages' => [], 'final' => []]];
    foreach ($cases as $race => $expected) {
        $result = $this->evaluate(['race' => $race, 'culture' => 'Landbewohner', 'barbar_attribute_bonus' => '', 'attribute_adjustments' => ['st' => 0, 'ge' => 1, 'ro' => 0, 'wi' => 1, 'wa' => 0, 'in' => 0, 'au' => 0], 'advantages' => ['Zäh'], 'disadvantages' => $expected['disadvantages']]);
        expect($result['valid'])->toBeTrue()
            ->and($result['effective_advantages'])->toBe(['Zäh', ...$expected['advantages']])
            ->and($result['effective_disadvantages'])->toBe($expected['disadvantages'])
            ->and($result['advantage_budget']['available_compensations'])->toBe(0);
        foreach ($expected['final'] as $attribute => $value) {
            expect($result['attributes']['final'][$attribute])->toBe($value);
        }
    }
});

test('only actual racial disadvantages can be negated and not remain active', function (): void {
    $notRacial = $this->evaluate(['race' => 'Wulfane', 'barbar_attribute_bonus' => '', 'disadvantages' => ['Ehrenkodex'], 'negated_racial_disadvantages' => ['Auffällig']]);
    $bothActiveAndNegated = $this->evaluate(['race' => 'Wulfane', 'barbar_attribute_bonus' => '', 'disadvantages' => ['Ehrenkodex'], 'negated_racial_disadvantages' => ['Ehrenkodex']]);
    expect($notRacial['valid'])->toBeFalse()
        ->and($notRacial['errors'])->toHaveKey('negated_racial_disadvantages')
        ->and($bothActiveAndNegated['valid'])->toBeFalse()
        ->and($bothActiveAndNegated['errors'])->toHaveKey('negated_racial_disadvantages');
});

test('malformed and incomplete evaluator inputs are rejected defensively', function (): void {
    $cases = ['attribute budget overflow' => [['attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 1, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0]], 'attributes'], 'unknown advantage' => [['advantages' => ['Zäh', 'Laserblick']], 'advantages'], 'missing racial disadvantage' => [['race' => 'Guul', 'barbar_attribute_bonus' => '', 'disadvantages' => []], 'disadvantages'], 'invalid adjustment' => [['attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 2, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0]], 'attribute_adjustments'], 'missing barbar target' => [['barbar_attribute_bonus' => ''], 'barbar_attribute_bonus'], 'non-list effects' => [['advantage_effects' => 'Schnell'], 'advantage_effects'], 'non-structured effect' => [['advantage_effects' => ['Schnell']], 'advantage_effects'], 'invalid extra AP source' => [['extra_ap_attribute' => 'st'], 'extra_ap_attribute'], 'too many compensation attributes' => [['attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => -1, 'wi' => -1, 'wa' => -1, 'in' => 0, 'au' => 0], 'advantage_compensation_attributes' => ['ro', 'wi', 'wa']], 'advantage_compensation_attributes'], 'missing animal companion detail' => [['advantages' => ['Zäh', 'Tiergefährte'], 'advantage_effects' => [['name' => 'Tiergefährte', 'target' => '', 'justification' => '']]], 'advantage_effects'], 'missing selected advantage effect' => [['advantages' => ['Zäh', 'Anführer']], 'advantage_effects'], 'duplicate non-repeatable effect' => [['advantages' => ['Zäh', 'Anführer'], 'advantage_effects' => [['name' => 'Anführer', 'target' => '', 'justification' => ''], ['name' => 'Anführer', 'target' => '', 'justification' => '']]], 'advantage_effects'], 'non-list selections' => [['advantages' => 'Zäh', 'disadvantages' => null], null]];
    foreach ($cases as $label => [$overrides, $expectedError]) {
        $result = $this->evaluate($overrides);
        if ($expectedError === null) {
            expect($result['valid'])->toBeTrue();

            continue;
        }
        expect($result['valid'])->toBeFalse()
            ->and($result['errors'])->toHaveKey($expectedError);
    }
});
