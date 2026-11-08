<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Changelog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('component renders on page', function (): void {
    $response = $this->get('/changelog');
    $response->assertOk();
    $response->assertSeeLivewire(Changelog::class);
});

test('releases are loaded from changelog json', function (): void {
    // changelog.json exists in public/ by default
    Livewire::test(Changelog::class)->assertOk()->assertDontSee('Keine Release-Notes');
});

test('version 1 16 1 explains the cycle assignment fix', function (): void {
    Livewire::test(Changelog::class)->assertSee('1.16.1')->assertSee('zuverlässig dem richtigen Zyklus zugeordnet');
});

test('renders without error when changelog missing', function (): void {
    // Point config to a non-existent temp path instead of renaming real file
    config(['app.changelog_path' => sys_get_temp_dir().'/non_existent_changelog.json']);
    Livewire::test(Changelog::class)->assertOk();
});
