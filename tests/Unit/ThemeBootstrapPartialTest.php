<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ThemeBootstrapPartialTest extends TestCase
{
    public function test_theme_bootstrap_partial_includes_shared_script(): void
    {
        $basePath = realpath(__DIR__ . '/../..');
        $partial = file_get_contents($basePath . '/resources/views/layouts/partials/theme-bootstrap.blade.php');
        $inlineScript = trim(file_get_contents($basePath . '/resources/js/theme/bootstrap-inline.js'));

        $this->assertStringContainsString('<script>', $partial);
        $this->assertStringContainsString('</script>', $partial);
        $this->assertStringContainsString($inlineScript, $partial);
    }
}
