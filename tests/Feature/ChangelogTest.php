<?php

namespace Tests\Feature;

use App\Livewire\Changelog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChangelogTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_on_page(): void
    {
        $response = $this->get('/changelog');

        $response->assertOk();
        $response->assertSeeLivewire(Changelog::class);
    }

    public function test_releases_are_loaded_from_changelog_json(): void
    {
        // changelog.json exists in public/ by default
        Livewire::test(Changelog::class)
            ->assertOk()
            ->assertDontSee('Keine Release-Notes');
    }

    public function test_renders_without_error_when_changelog_missing(): void
    {
        // Point config to a non-existent temp path instead of renaming real file
        config(['app.changelog_path' => sys_get_temp_dir() . '/non_existent_changelog.json']);

        Livewire::test(Changelog::class)
            ->assertOk();
    }
}
