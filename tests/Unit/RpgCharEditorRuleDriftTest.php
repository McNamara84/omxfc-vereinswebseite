<?php

namespace Tests\Unit;

use App\Http\Controllers\RpgCharEditorController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RpgCharEditorRuleDriftTest extends TestCase
{
    public function test_special_rule_config_is_the_backend_source_for_editor_rules(): void
    {
        $config = RpgCharEditorController::specialRuleConfig();

        $this->assertSame([
            'attributeRules',
            'skillRules',
            'advantages',
            'disadvantages',
            'advantageCosts',
            'repeatableAdvantages',
            'advantageDetailRequired',
            'disadvantageDetailRequired',
        ], array_keys($config));
        $this->assertSame(RpgCharEditorController::attributeRuleConfig(), $config['attributeRules']);
        $this->assertSame(RpgCharEditorController::skillRuleConfig(), $config['skillRules']);
        $this->assertSame($this->controllerConstant('ADVANTAGE_VALUES'), $config['advantages']);
        $this->assertSame($this->controllerConstant('DISADVANTAGE_VALUES'), $config['disadvantages']);
        $this->assertSame($this->controllerConstant('ADVANTAGE_COSTS'), $config['advantageCosts']);
        $this->assertSame($this->controllerConstant('REPEATABLE_ADVANTAGES'), $config['repeatableAdvantages']);
        $this->assertSame($this->controllerConstant('ADVANTAGE_DETAIL_REQUIRED'), $config['advantageDetailRequired']);
        $this->assertSame($this->controllerConstant('DISADVANTAGE_DETAIL_REQUIRED'), $config['disadvantageDetailRequired']);
    }

    public function test_frontend_rule_metadata_covers_backend_special_rules(): void
    {
        $source = $this->frontendSource();
        $config = RpgCharEditorController::specialRuleConfig();

        $this->assertStringContainsString('window.rpgCharEditorRules', $source);
        $this->assertStringContainsString("objectFromSpecialRuleConfig('attributeRules'", $source);
        $this->assertStringContainsString('attributeTooltip(id)', $source);
        $this->assertStringContainsString("objectFromSpecialRuleConfig('skillRules'", $source);
        $this->assertStringContainsString('skillTooltip(value)', $source);
        $this->assertStringContainsString("listFromSpecialRuleConfig('advantages'", $source);
        $this->assertStringContainsString("listFromSpecialRuleConfig('disadvantages'", $source);
        $this->assertStringContainsString("objectFromSpecialRuleConfig('advantageCosts'", $source);
        $this->assertStringContainsString("listFromSpecialRuleConfig('repeatableAdvantages'", $source);
        $this->assertStringContainsString("listFromSpecialRuleConfig('advantageDetailRequired'", $source);
        $this->assertStringContainsString("listFromSpecialRuleConfig('disadvantageDetailRequired'", $source);

        $this->assertSame(
            array_column($config['attributeRules']['attributes'], 'id'),
            $this->frontendAttributeMetadataIds(),
        );
        $this->assertSame(
            array_column($config['skillRules']['skills'], 'name'),
            $this->frontendSkillMetadataNames(),
        );
        $this->assertSame(
            array_column($config['skillRules']['specialSkills'], 'name'),
            $this->frontendMetadataNames('SPECIAL_SKILL_RULE_METADATA'),
        );
        $this->assertSame($config['advantages'], $this->frontendMetadataNames('ADVANTAGE_RULE_METADATA'));
        $this->assertSame($config['disadvantages'], $this->frontendMetadataNames('DISADVANTAGE_RULE_METADATA'));
    }

    private function controllerConstant(string $name): array
    {
        $constant = (new ReflectionClass(RpgCharEditorController::class))->getReflectionConstant($name);

        $this->assertNotNull($constant, "Controller constant {$name} is missing.");

        return $constant->getValue();
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
}
