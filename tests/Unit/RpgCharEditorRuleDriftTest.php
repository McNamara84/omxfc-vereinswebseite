<?php

namespace Tests\Unit;

use App\Services\RpgCharacterSheetService;
use App\Support\RpgCharEditorEquipment;
use App\Support\RpgCharEditorSpecialRules;
use App\Support\RpgCharEditorTraining;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RpgCharEditorRuleDriftTest extends TestCase
{
    public function test_special_rule_config_is_the_backend_source_for_editor_rules(): void
    {
        $config = RpgCharacterSheetService::specialRuleConfig();

        $this->assertSame([
            'creation',
            'advantages',
            'disadvantages',
            'advantageRules',
            'disadvantageRules',
            'advantageCosts',
            'repeatableAdvantages',
            'advantageDetailRequired',
            'disadvantageDetailRequired',
            'attributeRules',
            'skillRules',
            'trainingRules',
            'equipmentRules',
        ], array_keys($config));
        $this->assertSame(RpgCharEditorSpecialRules::ruleConfig()['creation'], $config['creation']);
        $this->assertSame(RpgCharacterSheetService::attributeRuleConfig(), $config['attributeRules']);
        $this->assertSame(RpgCharacterSheetService::skillRuleConfig(), $config['skillRules']);
        $this->assertSame(RpgCharEditorSpecialRules::ruleConfig()['advantages'], $config['advantages']);
        $this->assertSame(RpgCharEditorSpecialRules::ruleConfig()['disadvantages'], $config['disadvantages']);
        $this->assertSame(RpgCharEditorSpecialRules::advantages(), $config['advantageRules']);
        $this->assertSame(RpgCharEditorSpecialRules::disadvantages(), $config['disadvantageRules']);
        $this->assertSame(3, $config['advantageCosts']['Gestaltwandler']);
        $this->assertSame(
            ['Gesteigertes Attribut', 'Gesteigerter Sinn', 'Panzerung', 'Regeneration'],
            $config['repeatableAdvantages'],
        );
        $this->assertTrue($config['advantageRules']['Schnell']['requires_justification']);
        $this->assertTrue($config['disadvantageRules']['Anfälligkeit gegen Wahnsinn']['requires_detail']);
        $this->assertSame(RpgCharEditorTraining::ruleConfig(), $config['trainingRules']);
        $this->assertSame(RpgCharEditorEquipment::ruleConfig(), $config['equipmentRules']);
    }

    public function test_frontend_rule_metadata_covers_backend_special_rules(): void
    {
        $source = $this->frontendSource();
        $config = RpgCharacterSheetService::specialRuleConfig();

        $this->assertStringContainsString('window.rpgCharEditorRules', $source);
        $this->assertStringContainsString("objectFromSpecialRuleConfig('attributeRules'", $source);
        $this->assertStringContainsString('attributeTooltip(id)', $source);
        $this->assertStringContainsString("objectFromSpecialRuleConfig('skillRules'", $source);
        $this->assertStringContainsString('skillTooltip(value)', $source);
        $this->assertStringContainsString("listFromSpecialRuleConfig('advantages'", $source);
        $this->assertStringContainsString("listFromSpecialRuleConfig('disadvantages'", $source);
        $this->assertStringContainsString("objectFromSpecialRuleConfig('advantageCosts'", $source);
        $this->assertStringContainsString("objectFromSpecialRuleConfig('advantageRules'", $source);
        $this->assertStringContainsString("objectFromSpecialRuleConfig('disadvantageRules'", $source);
        $this->assertStringContainsString("listFromSpecialRuleConfig('repeatableAdvantages'", $source);
        $this->assertStringContainsString("listFromSpecialRuleConfig('advantageDetailRequired'", $source);
        $this->assertStringContainsString("listFromSpecialRuleConfig('disadvantageDetailRequired'", $source);
        $this->assertStringContainsString("objectFromSpecialRuleConfig('equipmentRules'", $source);
        $this->assertStringContainsString("objectFromSpecialRuleConfig('trainingRules'", $source);
        $this->assertStringContainsString('trainingRulesComplete()', $source);
        $this->assertStringContainsString('equipmentComplete()', $source);
        $viewSource = $this->charEditorViewSource();
        $this->assertStringContainsString('equipmentLimit()', $viewSource);
        $this->assertStringContainsString('highTechEquipmentLimit()', $viewSource);
        $this->assertStringNotContainsString("equipmentCount() + ' / 6", $viewSource);
        $this->assertStringNotContainsString("highTechEquipmentCount() + ' / 4", $viewSource);
        $this->assertStringNotContainsString('&auml;', $viewSource);
        $this->assertStringNotContainsString('&middot;', $viewSource);
        $this->assertSame(
            array_column($config['attributeRules']['attributes'], 'id'),
            $this->frontendAttributeMetadataIds(),
        );
        $this->assertSame(
            array_column($config['skillRules']['skills'], 'name'),
            $this->frontendSkillMetadataNames(),
        );
        $this->assertSame([], $config['skillRules']['specialSkills']);
        $this->assertStringNotContainsString('SPECIAL_SKILL_RULE_METADATA', $source);
        $this->assertSame($config['advantages'], $this->frontendMetadataNames('ADVANTAGE_RULE_METADATA'));
        $this->assertSame($config['disadvantages'], $this->frontendMetadataNames('DISADVANTAGE_RULE_METADATA'));
        $this->assertNotEmpty($config['equipmentRules']['items']);
        $this->assertCount(10, $config['trainingRules']['trainings']);
        $this->assertSame(6, $config['equipmentRules']['limits']['items']);
    }

    public function test_w66_ranges_targets_and_detail_rules_match_the_rulebook_tables(): void
    {
        $advantages = RpgCharEditorSpecialRules::advantages();
        $disadvantages = RpgCharEditorSpecialRules::disadvantages();

        $this->assertSame([
            'Anführer' => '11-12',
            'Gestaltwandler' => '13',
            'Gesteigertes Attribut' => '14-24',
            'Gesteigerter Sinn' => '25-26',
            'High-Tech-Ausrüstung' => '31-32',
            'Kampfreflexe' => '33-34',
            'Kaltblütig' => '35-36',
            'Kiemen' => '41',
            'Kind zweier Welten' => '42',
            'Nachtsicht' => '43-44',
            'Natürliche Waffen' => '45',
            'Panzerung' => '46',
            'Psychische Kraft' => '51',
            'Psychisches Reservoir' => '52',
            'Regeneration' => '53',
            'Scharfschütze' => '54',
            'Schnell' => '55-56',
            'Sprachbegabt' => '61',
            'Tiergefährte' => '62-64',
            'Zäh' => '65-66',
        ], array_map(static fn (array $rule): string => $rule['w66'], $advantages));
        $this->assertSame([
            'Abergläubisch' => '11-16',
            'Abhängige' => '21',
            'Anfälligkeit gegen Wahnsinn' => '22',
            'Auffällig' => '23-24',
            'Blutdurst' => '25',
            'Ehrenkodex' => '26-36',
            'Feind' => '41-44',
            'Gejagt' => '45-46',
            'Lichtscheu' => '51',
            'Primitiv' => '52-53',
            'Taratzenfutter' => '54-63',
            'Tödliche Immunschwäche' => '64',
            'Verpflichtung' => '65',
            'Verwundbarkeit' => '66',
        ], array_map(static fn (array $rule): string => $rule['w66'], $disadvantages));

        $this->assertSame(
            ['Gesteigertes Attribut', 'Gesteigerter Sinn', 'Nachtsicht', 'Natürliche Waffen', 'Panzerung', 'Regeneration', 'Schnell'],
            array_keys(array_filter($advantages, static fn (array $rule): bool => $rule['requires_justification'])),
        );
        $this->assertSame(RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS, $advantages['Gesteigertes Attribut']['targets']);
        $this->assertSame(RpgCharEditorSpecialRules::SENSE_TARGETS, $advantages['Gesteigerter Sinn']['targets']);
        $this->assertSame(RpgCharEditorSpecialRules::PSYCHIC_POWER_TARGETS, $advantages['Psychische Kraft']['targets']);
        $this->assertSame(3, $advantages['Gestaltwandler']['cost']);
        $this->assertSame(0, $advantages['Zäh']['cost']);
        $this->assertStringContainsString('dauerhaften und loyalen Begleiter', $advantages['Tiergefährte']['description']);
        $this->assertStringContainsString('SL übernimmt den Charakter', $disadvantages['Anfälligkeit gegen Wahnsinn']['description']);
        $this->assertStringContainsString('-4 auf alle Verkleiden-Proben', $disadvantages['Auffällig']['description']);
        $this->assertStringContainsString('kontinuierlich bedroht', $disadvantages['Feind']['description']);
        $this->assertStringContainsString('kaum frei in Städten und Dörfern', $disadvantages['Gejagt']['description']);
        $this->assertStringContainsString('nennenswerten Teil der eigenen Zeit', $disadvantages['Verpflichtung']['description']);
    }

    public function test_skill_help_rows_use_stable_keys_and_non_toggle_clicks(): void
    {
        $source = $this->charEditorViewSource();

        $this->assertStringContainsString(':key="skill.uid"', $source);
        $this->assertStringNotContainsString(':key="index"', $source);
        $this->assertStringContainsString('@click.stop="skillHelpOpen = true"', $source);
        $this->assertStringNotContainsString('@click="skillHelpOpen = !skillHelpOpen"', $source);
    }

    public function test_legacy_natural_weapon_skill_is_not_advertised_as_a_skill_rule(): void
    {
        $this->assertNotContains('Natürliche Waffen', array_column(RpgCharacterSheetService::skillRuleConfig()['skills'], 'name'));
        $this->assertSame([], RpgCharacterSheetService::skillRuleConfig()['specialSkills']);
        $this->assertFalse(RpgCharacterSheetService::skillRuleConfig()['skills'][array_search(
            'Sprachen',
            array_column(RpgCharacterSheetService::skillRuleConfig()['skills'], 'name'),
            true,
        )]['specializable'] ?? false);
        $this->assertFalse($this->invokeControllerMethod('isSpecializableBaseSkill', ['Natürliche Waffen']));
    }

    private function controllerConstant(string $name): array
    {
        $constant = (new ReflectionClass(RpgCharacterSheetService::class))->getReflectionConstant($name);

        $this->assertNotNull($constant, "Controller constant {$name} is missing.");

        return $constant->getValue();
    }

    private function invokeControllerMethod(string $methodName, array $arguments): mixed
    {
        $method = (new ReflectionClass(RpgCharacterSheetService::class))->getMethod($methodName);

        // PHP 8.1+ allows invoking non-public methods; PHP 8.5 deprecates setAccessible().
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        return $method->invokeArgs(new RpgCharacterSheetService, $arguments);
    }

    private function frontendMetadataNames(string $constantName): array
    {
        $this->assertMatchesRegularExpression(
            '/const '.preg_quote($constantName, '/').' = \{(.*?)\};/s',
            $this->frontendSource(),
            "Frontend metadata constant {$constantName} is missing.",
        );

        preg_match('/const '.preg_quote($constantName, '/').' = \{(.*?)\};/s', $this->frontendSource(), $matches);

        $names = [];

        foreach (preg_split('/\R/', $matches[1]) ?: [] as $line) {
            if (preg_match('/^\s*["\']([^"\']+)["\']:\s*\{/', $line, $nameMatches)) {
                $names[] = stripcslashes($nameMatches[1]);
            }
        }

        return $names;
    }

    private function frontendSkillMetadataNames(): array
    {
        $pattern = '/const\s+SKILL_RULE_METADATA\s*=\s*\{(.*?)\}\s*;/s';
        $source = $this->frontendSource();

        $this->assertMatchesRegularExpression(
            $pattern,
            $source,
            'Frontend skill metadata constant is missing.',
        );

        preg_match($pattern, $source, $matches);

        preg_match_all('/^\s*(?:["\']([^"\']+)["\']|([^\s:]+))\s*:\s*\{/mu', $matches[1], $nameMatches);

        return array_values(array_filter(array_map(
            fn (?string $quoted, ?string $unquoted): string => $quoted !== '' ? (string) $quoted : (string) $unquoted,
            $nameMatches[1],
            $nameMatches[2],
        )));
    }

    private function frontendAttributeMetadataIds(): array
    {
        $pattern = '/const\s+ATTRIBUTE_RULE_METADATA\s*=\s*\{(.*?)\}\s*;/s';
        $source = $this->frontendSource();

        $this->assertMatchesRegularExpression(
            $pattern,
            $source,
            'Frontend attribute metadata constant is missing.',
        );

        preg_match($pattern, $source, $matches);

        preg_match_all('/(?:^|,)\s*["\']?([a-z]{2})["\']?\s*:\s*\{/', $matches[1], $idMatches);

        return $idMatches[1];
    }

    private function frontendSource(): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/js/alpine/char-editor.js');

        $this->assertIsString($source);

        return $source;
    }

    private function charEditorViewSource(): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/rpg/char-editor.blade.php');

        $this->assertIsString($source);

        return $source;
    }
}
