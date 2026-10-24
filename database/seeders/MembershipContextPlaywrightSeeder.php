<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class MembershipContextPlaywrightSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Membership context fixtures require the testing environment.');
        }
        $members = Team::membersTeam() ?? throw new LogicException('Seed the Playwright suite first.');
        $user = User::firstWhere('email', 'membership-context@example.com') ?? User::factory()->create([
            'name' => 'Kontext Testperson',
            'vorname' => 'Kontext',
            'email' => 'membership-context@example.com',
            'password' => Hash::make('password'),
            'current_team_id' => $members->id,
            'lat' => 48.0,
            'lon' => 11.0,
        ]);
        $user->forceFill(['current_team_id' => $members->id])->save();
        $members->users()->syncWithoutDetaching([$user->id => ['role' => Role::Admin->value]]);
        $team = Team::find(901901) ?? Team::factory()->create(['id' => 901901, 'name' => 'AG Kontext-Test', 'personal_team' => false]);
        $team->users()->syncWithoutDetaching([$user->id => ['role' => Role::Mitwirkender->value]]);
    }
}
