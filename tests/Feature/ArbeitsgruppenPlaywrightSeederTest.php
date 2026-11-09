<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Team;
use Database\Seeders\ArbeitsgruppenPlaywrightSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

afterEach(function (): void {
    Mockery::close();
});

test('seeder warns when members team is missing', function (): void {
    $membersTeam = Team::membersTeam();
    if (! $membersTeam instanceof Team) {
        $this->fail('Mitglieder-Team sollte durch die Seed-Daten vorhanden sein.');
    }
    Team::query()->whereKey($membersTeam->getKey())->delete();
    Cache::forget(Team::MEMBERS_TEAM_CACHE_KEY);
    Cache::forget(Team::MEMBERS_TEAM_ID_CACHE_KEY);
    $command = Mockery::mock(Command::class);
    $command->shouldReceive('warn')->once()->with('Team "Mitglieder" not found. Run TodoPlaywrightSeeder first.');
    $seeder = new ArbeitsgruppenPlaywrightSeeder;
    $seeder->setCommand($command);
    $seeder->run();
    $this->assertDatabaseMissing('teams', ['name' => 'AG Fanhoerbuecher']);
});

test('seeder creates public ag with logo contract for playwright', function (): void {
    Storage::fake('public');
    $seeder = new ArbeitsgruppenPlaywrightSeeder;
    $seeder->run();
    $this->assertDatabaseHas('teams', ['name' => 'AG Fanhoerbuecher', 'personal_team' => false, 'logo_path' => 'ag-logos/arbeitsgruppen-playwright-logo.svg']);
    Storage::disk('public')->assertExists('ag-logos/arbeitsgruppen-playwright-logo.svg');
    expect(Storage::disk('public')->get('ag-logos/arbeitsgruppen-playwright-logo.svg'))->toContain('<svg');
});
