<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
class ThemeBootstrapPartialTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_bootstrap_partial_includes_shared_script(): void
    {
        $rendered = Blade::render("@include('layouts.partials.theme-bootstrap')");
        $inlineScript = trim(file_get_contents(resource_path('js/theme/bootstrap-inline.js')));

        $this->assertStringContainsString('<script>', $rendered);
        $this->assertStringContainsString('</script>', $rendered);
        $this->assertStringContainsString($inlineScript, $rendered);
    }
}
