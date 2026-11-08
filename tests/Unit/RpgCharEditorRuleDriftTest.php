<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCharacterSheetService;
use App\Support\RpgCharEditorEquipment;
use App\Support\RpgCharEditorSpecialRules;
use App\Support\RpgCharEditorTraining;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class)->use(RpgCharEditorRuleDriftTestFixtures::class);

test('special rule config is the backend source for editor rules', function (): void {
    $config = RpgCharacterSheetService::specialRuleConfig();
    expect(array_keys($config))->toBe(['creation', 'advantages', 'disadvantages', 'advantageRules', 'disadvantageRules', 'advantageCosts', 'repeatableAdvantages', 'advantageDetailRequired', 'disadvantageDetailRequired', 'attributeRules', 'skillRules', 'trainingRules', 'equipmentRules', 'ruleCatalog'])
        ->and($config['creation'])->toBe(RpgCharEditorSpecialRules::ruleConfig()['creation'])
        ->and($config['creation']['defaultLevel'])->toBe(3)
        ->and($config['creation']['levels'])->toBe(array_values(RpgCharEditorSpecialRules::CREATION_LEVELS))
        ->and($config['attributeRules'])->toBe(RpgCharacterSheetService::attributeRuleConfig())
        ->and($config['skillRules'])->toBe(RpgCharacterSheetService::skillRuleConfig())
        ->and($config['advantages'])->toBe(RpgCharEditorSpecialRules::ruleConfig()['advantages'])
        ->and($config['disadvantages'])->toBe(RpgCharEditorSpecialRules::ruleConfig()['disadvantages'])
        ->and($config['advantageRules'])->toBe(RpgCharEditorSpecialRules::advantages())
        ->and($config['disadvantageRules'])->toBe(RpgCharEditorSpecialRules::disadvantages())
        ->and($config['advantageCosts']['Gestaltwandler'])->toBe(3)
        ->and($config['repeatableAdvantages'])->toBe(['Gesteigertes Attribut', 'Gesteigerter Sinn', 'Panzerung', 'Regeneration'])
        ->and($config['advantageRules']['Schnell']['requires_justification'])->toBeTrue()
        ->and($config['disadvantageRules']['Anfälligkeit gegen Wahnsinn']['requires_detail'])->toBeTrue()
        ->and($config['trainingRules'])->toBe(RpgCharEditorTraining::ruleConfig())
        ->and($config['equipmentRules'])->toBe(RpgCharEditorEquipment::ruleConfig());
});

test('frontend rule metadata covers backend special rules', function (): void {
    $source = $this->frontendSource();
    $config = RpgCharacterSheetService::specialRuleConfig();
    expect($source)->toContain('window.rpgCharEditorRules')
        ->toContain("objectFromSpecialRuleConfig('attributeRules'")
        ->toContain('attributeTooltip(id)')
        ->toContain("objectFromSpecialRuleConfig('skillRules'")
        ->toContain('skillTooltip(value)')
        ->toContain("listFromSpecialRuleConfig('advantages'")
        ->toContain("listFromSpecialRuleConfig('disadvantages'")
        ->toContain("objectFromSpecialRuleConfig('advantageCosts'")
        ->toContain("objectFromSpecialRuleConfig('advantageRules'")
        ->toContain("objectFromSpecialRuleConfig('disadvantageRules'")
        ->toContain("listFromSpecialRuleConfig('repeatableAdvantages'")
        ->toContain("listFromSpecialRuleConfig('advantageDetailRequired'")
        ->toContain("listFromSpecialRuleConfig('disadvantageDetailRequired'")
        ->toContain("objectFromSpecialRuleConfig('equipmentRules'")
        ->toContain("objectFromSpecialRuleConfig('trainingRules'")
        ->toContain('trainingRulesComplete()')
        ->toContain('equipmentComplete()');
    $viewSource = $this->charEditorViewSource();
    expect($viewSource)->toContain('equipmentLimit()')
        ->toContain('highTechEquipmentLimit()')->not->toContain("equipmentCount() + ' / 6")->not->toContain("highTechEquipmentCount() + ' / 4")->not->toContain('&auml;')->not->toContain('&middot;')
        ->and($this->frontendAttributeMetadataIds())->toBe(array_column($config['attributeRules']['attributes'], 'id'))
        ->and($this->frontendSkillMetadataNames())->toBe(array_column($config['skillRules']['skills'], 'name'))
        ->and($config['skillRules']['specialSkills'])->toBe([])
        ->and($source)->not->toContain('SPECIAL_SKILL_RULE_METADATA')
        ->and($this->frontendMetadataNames('ADVANTAGE_RULE_METADATA'))->toBe($config['advantages'])
        ->and($this->frontendMetadataNames('DISADVANTAGE_RULE_METADATA'))->toBe($config['disadvantages'])
        ->and($config['equipmentRules']['items'])->not->toBeEmpty()
        ->and($config['trainingRules']['trainings'])->toHaveCount(12)
        ->and($config['equipmentRules']['limits']['items'])->toBe(6);
});

test('w66 ranges targets and detail rules match the rulebook tables', function (): void {
    $advantages = RpgCharEditorSpecialRules::advantages();
    $disadvantages = RpgCharEditorSpecialRules::disadvantages();
    expect(array_map(static fn (array $rule): string => $rule['w66'], $advantages))->toBe(['Anführer' => '11-12', 'Gestaltwandler' => '13', 'Gesteigertes Attribut' => '14-24', 'Gesteigerter Sinn' => '25-26', 'High-Tech-Ausrüstung' => '31-32', 'Kampfreflexe' => '33-34', 'Kaltblütig' => '35-36', 'Kiemen' => '41', 'Kind zweier Welten' => '42', 'Nachtsicht' => '43-44', 'Natürliche Waffen' => '45', 'Panzerung' => '46', 'Psychische Kraft' => '51', 'Psychisches Reservoir' => '52', 'Regeneration' => '53', 'Scharfschütze' => '54', 'Schnell' => '55-56', 'Sprachbegabt' => '61', 'Tiergefährte' => '62-64', 'Zäh' => '65-66'])
        ->and(array_map(static fn (array $rule): string => $rule['w66'], $disadvantages))->toBe(['Abergläubisch' => '11-16', 'Abhängige' => '21', 'Anfälligkeit gegen Wahnsinn' => '22', 'Auffällig' => '23-24', 'Blutdurst' => '25', 'Ehrenkodex' => '26-36', 'Feind' => '41-44', 'Gejagt' => '45-46', 'Lichtscheu' => '51', 'Primitiv' => '52-53', 'Taratzenfutter' => '54-63', 'Tödliche Immunschwäche' => '64', 'Verpflichtung' => '65', 'Verwundbarkeit' => '66'])
        ->and(array_keys(array_filter($advantages, static fn (array $rule): bool => $rule['requires_justification'])))->toBe(['Gesteigertes Attribut', 'Gesteigerter Sinn', 'Nachtsicht', 'Natürliche Waffen', 'Panzerung', 'Regeneration', 'Schnell'])
        ->and($advantages['Gesteigertes Attribut']['targets'])->toBe(RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS)
        ->and($advantages['Gesteigerter Sinn']['targets'])->toBe(RpgCharEditorSpecialRules::SENSE_TARGETS)
        ->and($advantages['Psychische Kraft']['targets'])->toBe(RpgCharEditorSpecialRules::PSYCHIC_POWER_TARGETS)
        ->and($advantages['Gestaltwandler']['cost'])->toBe(3)
        ->and($advantages['Zäh']['cost'])->toBe(1)
        ->and($advantages['Tiergefährte']['description'])->toContain('dauerhaften und loyalen Begleiter')
        ->and($disadvantages['Anfälligkeit gegen Wahnsinn']['description'])->toContain('SL übernimmt den Charakter')
        ->and($disadvantages['Auffällig']['description'])->toContain('-4 auf alle Verkleiden-Proben')
        ->and($disadvantages['Feind']['description'])->toContain('kontinuierlich bedroht')
        ->and($disadvantages['Gejagt']['description'])->toContain('kaum frei in Städten und Dörfern')
        ->and($disadvantages['Verpflichtung']['description'])->toContain('nennenswerten Teil der eigenen Zeit');
});

test('all creation levels match the rulebook table', function (): void {
    expect(array_map(static fn (array $rule): array => [$rule['level'], $rule['attributePoints'], $rule['skillPoints'], $rule['skillMax'], $rule['freeAdvantageUnits'], $rule['automaticAdvantages'], $rule['automaticDisadvantages']], RpgCharEditorSpecialRules::CREATION_LEVELS))->toBe([1 => [1, 0, 10, 3, 0, [], ['Taratzenfutter']], 2 => [2, 1, 15, 3, 0, [], []], 3 => [3, 2, 20, 4, 1, ['Zäh'], []], 4 => [4, 3, 40, 5, 2, ['Zäh'], []], 5 => [5, 4, 60, 6, 3, ['Zäh'], []]]);
});

test('skill help rows use stable keys and non toggle clicks', function (): void {
    $source = $this->charEditorViewSource();
    expect($source)->toContain(':key="skill.uid"')->not->toContain(':key="index"')
        ->toContain('@click.stop="skillHelpOpen = true"')->not->toContain('@click="skillHelpOpen = !skillHelpOpen"');
});

test('dynamic text inputs have accessible names beyond their placeholders', function (): void {
    $source = $this->charEditorViewSource();
    expect($source)->toMatch('/<input(?=[^>]*x-model="languageDraft")(?=[^>]*aria-label="Sprache oder Dialekt")[^>]*>/s')
        ->toContain(':aria-label="@js($advantage) + \': \' + advantageDetailPlaceholder(@js($advantage))"');
});

test('legacy natural weapon skill is not advertised as a skill rule', function (): void {
    expect(array_column(RpgCharacterSheetService::skillRuleConfig()['skills'], 'name'))->not->toContain('Natürliche Waffen')
        ->and(RpgCharacterSheetService::skillRuleConfig()['specialSkills'])->toBe([])
        ->and(RpgCharacterSheetService::skillRuleConfig()['skills'][array_search('Sprachen', array_column(RpgCharacterSheetService::skillRuleConfig()['skills'], 'name'), true)]['specializable'] ?? false)->toBeFalse()
        ->and($this->invokeControllerMethod('isSpecializableBaseSkill', ['Natürliche Waffen']))->toBeFalse();
});
