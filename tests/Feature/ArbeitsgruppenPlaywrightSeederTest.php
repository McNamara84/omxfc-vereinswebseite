<?php

namespace Tests\Feature;

use App\Models\Team;
use Database\Seeders\ArbeitsgruppenPlaywrightSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ArbeitsgruppenPlaywrightSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_seeder_warns_when_members_team_is_missing(): void
    {
        $membersTeam = Team::membersTeam();

        if (! $membersTeam instanceof Team) {
            $this->fail('Mitglieder-Team sollte durch die Seed-Daten vorhanden sein.');
        }

        Team::query()->whereKey($membersTeam->getKey())->delete();
        Cache::forget(Team::MEMBERS_TEAM_CACHE_KEY);
        Cache::forget(Team::MEMBERS_TEAM_ID_CACHE_KEY);

        $command = Mockery::mock(Command::class);
        $command->shouldReceive('warn')
            ->once()
            ->with('Team "Mitglieder" not found. Run TodoPlaywrightSeeder first.');

        $seeder = new ArbeitsgruppenPlaywrightSeeder;
        $seeder->setCommand($command);
        $seeder->run();

        $this->assertDatabaseMissing('teams', [
            'name' => 'AG Fanhoerbuecher',
        ]);
    }

    public function test_seeder_creates_public_ag_with_logo_contract_for_playwright(): void
    {
        Storage::fake('public');

        $seeder = new ArbeitsgruppenPlaywrightSeeder;
        $seeder->run();

        $this->assertDatabaseHas('teams', [
            'name' => 'AG Fanhoerbuecher',
            'personal_team' => false,
            'logo_path' => 'ag-logos/arbeitsgruppen-playwright-logo.svg',
        ]);

        Storage::disk('public')->assertExists('ag-logos/arbeitsgruppen-playwright-logo.svg');
        $this->assertStringContainsString(
            '<svg',
            Storage::disk('public')->get('ag-logos/arbeitsgruppen-playwright-logo.svg')
        );
    }
}
