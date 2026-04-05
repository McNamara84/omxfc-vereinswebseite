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
        // Temporarily rename changelog.json to simulate missing file
        $path = public_path('changelog.json');
        $backupPath = public_path('changelog.json.bak');
        $exists = file_exists($path);

        if ($exists) {
            rename($path, $backupPath);
        }

        try {
            Livewire::test(Changelog::class)
                ->assertOk();
        } finally {
            if ($exists) {
                rename($backupPath, $path);
            }
        }
    }
}
