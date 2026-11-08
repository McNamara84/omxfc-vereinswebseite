<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCharacterSheetService;
use ReflectionClass;

trait RpgCharEditorRuleDriftTestFixtures
{
    private function controllerConstant(string $name): array
    {
        $constant = (new ReflectionClass(RpgCharacterSheetService::class))->getReflectionConstant($name);
        expect($constant)->not->toBeNull();

        return $constant->getValue();
    }

    private function invokeControllerMethod(string $methodName, array $arguments): mixed
    {
        $method = (new ReflectionClass(RpgCharacterSheetService::class))->getMethod($methodName);

        return $method->invokeArgs(new RpgCharacterSheetService, $arguments);
    }

    private function frontendMetadataNames(string $constantName): array
    {
        expect($this->frontendSource())->toMatch('/const '.preg_quote($constantName, '/').' = \{(.*?)\};/s');
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
        expect($source)->toMatch($pattern);
        preg_match($pattern, $source, $matches);
        preg_match_all('/^\s*(?:["\']([^"\']+)["\']|([^\s:]+))\s*:\s*\{/mu', $matches[1], $nameMatches);

        return array_values(array_filter(array_map(fn (?string $quoted, ?string $unquoted): string => $quoted !== '' ? (string) $quoted : (string) $unquoted, $nameMatches[1], $nameMatches[2])));
    }

    private function frontendAttributeMetadataIds(): array
    {
        $pattern = '/const\s+ATTRIBUTE_RULE_METADATA\s*=\s*\{(.*?)\}\s*;/s';
        $source = $this->frontendSource();
        expect($source)->toMatch($pattern);
        preg_match($pattern, $source, $matches);
        preg_match_all('/(?:^|,)\s*["\']?([a-z]{2})["\']?\s*:\s*\{/', $matches[1], $idMatches);

        return $idMatches[1];
    }

    private function frontendSource(): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/js/alpine/char-editor.js');
        expect($source)->toBeString();

        return $source;
    }

    private function charEditorViewSource(): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/rpg/char-editor.blade.php');
        expect($source)->toBeString();

        return $source;
    }
}
