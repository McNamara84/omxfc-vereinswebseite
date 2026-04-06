<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SkeletonTableComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_skeleton_table_renders_with_default_props(): void
    {
        $html = Blade::render('<x-skeleton-table />');

        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('skeleton', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('<tbody>', $html);
    }

    public function test_skeleton_table_renders_correct_number_of_columns(): void
    {
        $html = Blade::render('<x-skeleton-table :columns="3" :rows="1" />');

        // 3 header columns + 3 body columns = 6 total <td>/<th> cells
        $this->assertEquals(3, substr_count($html, '<th>'));
        $this->assertEquals(3, substr_count($html, '<td>'));
    }

    public function test_skeleton_table_renders_correct_number_of_rows(): void
    {
        $html = Blade::render('<x-skeleton-table :columns="1" :rows="4" />');

        // 1 header <tr> + 4 body <tr> = 5 total
        $this->assertEquals(5, substr_count($html, '<tr>'));
    }

    public function test_skeleton_table_renders_avatar_in_first_column(): void
    {
        $html = Blade::render('<x-skeleton-table :columns="2" :rows="1" :hasAvatar="true" />');

        $this->assertStringContainsString('rounded-full', $html);
    }

    public function test_skeleton_table_does_not_render_avatar_by_default(): void
    {
        $html = Blade::render('<x-skeleton-table :columns="2" :rows="1" />');

        $this->assertStringNotContainsString('rounded-full', $html);
    }

    public function test_skeleton_table_uses_variable_widths(): void
    {
        $html = Blade::render('<x-skeleton-table :columns="5" :rows="2" />');

        // All 5 width variants should appear
        $this->assertStringContainsString('w-16', $html);
        $this->assertStringContainsString('w-20', $html);
        $this->assertStringContainsString('w-24', $html);
        $this->assertStringContainsString('w-28', $html);
        $this->assertStringContainsString('w-32', $html);
    }

    public function test_skeleton_table_is_hidden_from_screen_readers(): void
    {
        $html = Blade::render('<x-skeleton-table />');

        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_skeleton_table_with_single_column_and_row(): void
    {
        $html = Blade::render('<x-skeleton-table :columns="1" :rows="1" />');

        $this->assertEquals(1, substr_count($html, '<th>'));
        $this->assertEquals(1, substr_count($html, '<td>'));
        $this->assertEquals(2, substr_count($html, '<tr>')); // 1 header + 1 body
    }
}
