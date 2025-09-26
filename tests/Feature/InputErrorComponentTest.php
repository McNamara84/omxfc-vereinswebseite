<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class InputErrorComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_without_error_bag(): void
    {
        $html = Blade::render('<x-input-error for="vorname" />');

        $this->assertStringContainsString('data-error-for="vorname"', $html);
        $this->assertStringContainsString('role="status"', $html);
    }
}
