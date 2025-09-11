<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PageComponentCustomizationTest extends TestCase
{
    use RefreshDatabase;

    private function assertAllowsExtraClasses(string $component, string $extra, string $default): void
    {
        $html = Blade::render("<{$component} class=\"{$extra}\">Content</{$component}>");

        $this->assertStringContainsString($extra, $html);
        $this->assertStringContainsString($default, $html);
    }

    public function test_public_page_component_allows_extra_classes(): void
    {
        $this->assertAllowsExtraClasses('x-public-page', 'text-red-500', 'max-w-6xl');
    }

    public function test_member_page_component_allows_extra_classes(): void
    {
        $this->assertAllowsExtraClasses('x-member-page', 'p-2', 'max-w-7xl');
    }
}

