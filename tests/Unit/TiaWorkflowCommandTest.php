<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TiaWorkflowCommandTest extends TestCase
{
    public function test_tia_baseline_workflow_uses_the_compatible_canonical_composer_script(): void
    {
        $root = dirname(__DIR__, 2);
        $workflow = file_get_contents($root.'/.github/workflows/tia-baseline.yml');
        $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsString($workflow);
        $this->assertStringContainsString('run: composer test:tia:fresh', $workflow);

        $command = $composer['scripts']['test:tia:fresh'] ?? null;

        $this->assertIsString($command);
        $this->assertStringContainsString('--tia', $command);
        $this->assertStringContainsString('--fresh', $command);
        $this->assertStringNotContainsString('--random-order-seed', $command);
        $this->assertStringNotContainsString('--order-by=random', $command);
        $this->assertStringNotContainsString('tests/', $command);

        $replayCommand = $composer['scripts']['test:tia'] ?? null;

        $this->assertIsString($replayCommand);
        $this->assertStringContainsString('--tia', $replayCommand);
        $this->assertStringNotContainsString('--fresh', $replayCommand);
        $this->assertStringNotContainsString('tests/', $replayCommand);
    }
}
