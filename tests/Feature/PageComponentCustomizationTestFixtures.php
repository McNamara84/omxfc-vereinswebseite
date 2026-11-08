<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

trait PageComponentCustomizationTestFixtures
{
    use RefreshDatabase;

    private function assertAllowsExtraClasses(string $component, string $extra, string $default): void
    {
        $html = Blade::render("<{$component} class=\"{$extra}\">Content</{$component}>");
        expect($html)->toContain($extra)
            ->toContain($default);
    }
}
