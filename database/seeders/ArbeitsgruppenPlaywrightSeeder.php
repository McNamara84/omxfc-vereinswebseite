<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ArbeitsgruppenPlaywrightSeeder extends Seeder
{
        private const PLAYWRIGHT_LOGO_PATH = 'ag-logos/arbeitsgruppen-playwright-logo.svg';

        private const PLAYWRIGHT_LOGO_SVG = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 160" role="img" aria-labelledby="title desc">
    <title id="title">AG Fanhoerbuecher Logo</title>
    <desc id="desc">Testlogo fuer Playwright-Pruefungen der Arbeitsgruppen-Seite.</desc>
    <rect width="320" height="160" rx="28" fill="#7c1128"/>
    <rect x="12" y="12" width="296" height="136" rx="22" fill="#f7efe5" opacity="0.96"/>
    <path d="M74 44c-7.732 0-14 6.268-14 14v44c0 7.732 6.268 14 14 14h21.5c24.024 0 43.5-19.476 43.5-43.5S119.524 29 95.5 29H74Zm16 18h6.5c14.083 0 25.5 11.417 25.5 25.5S110.583 113 96.5 113H90V62Z" fill="#7c1128"/>
    <path d="M163 47h18v28h29V47h18v69h-18V91h-29v25h-18V47Z" fill="#0f172a"/>
    <path d="M248 47h18l26 69h-18.7l-4.6-13h-28.1l-4.6 13H217L248 47Zm15.2 42-8.5-24.1L246.1 89h17.1Z" fill="#c24d2c"/>
    <circle cx="269" cy="58" r="6" fill="#7c1128"/>
</svg>
SVG;

    public function run(): void
    {
        $membersTeam = Team::membersTeam();

        if (! $membersTeam) {
            $this->command?->warn('Team "Mitglieder" not found. Run TodoPlaywrightSeeder first.');

            return;
        }

        Storage::disk('public')->put(self::PLAYWRIGHT_LOGO_PATH, self::PLAYWRIGHT_LOGO_SVG);

        $leader = User::factory()->create([
            'name' => 'Martin Gobrecht',
            'email' => 'martin.gobrecht@example.com',
            'vorname' => 'Martin',
            'nachname' => 'Gobrecht',
            'current_team_id' => $membersTeam->id,
        ]);

        $membersTeam->users()->syncWithoutDetaching([
            $leader->id => ['role' => Role::Mitglied->value],
        ]);

        $team = Team::query()->create([
            'name' => 'AG Fanhoerbuecher',
            'user_id' => $leader->id,
            'personal_team' => false,
            'description' => 'EARDRAX: Die AG macht inszenierte Lesungen fuer YouTube zuganglich und sucht weitere Mitwirkende.',
            'meeting_schedule' => 'Nach Bedarf und Projektphase',
            'email' => 'ag-hoerbuecher@maddrax-fanclub.de',
            'logo_path' => self::PLAYWRIGHT_LOGO_PATH,
        ]);

        $team->users()->syncWithoutDetaching([
            $leader->id => ['role' => Role::Mitwirkender->value],
        ]);
    }
}