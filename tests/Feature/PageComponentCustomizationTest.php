<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PageComponentCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_component_allows_extra_classes(): void
    {
        $html = Blade::render('<x-public-page class="text-red-500">Test</x-public-page>');

        $this->assertStringContainsString('text-red-500', $html);
        $this->assertStringContainsString('max-w-6xl', $html);
    }

    public function test_member_page_component_allows_extra_classes(): void
    {
        $html = Blade::render('<x-member-page class="p-2">Content</x-member-page>');

        $this->assertStringContainsString('p-2', $html);
        $this->assertStringContainsString('max-w-7xl', $html);
    }
}

