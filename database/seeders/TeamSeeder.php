<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\User;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure there is at least one user to own the team
        $owner = User::first() ?? User::factory()->create();

        Team::firstOrCreate(
            ['name' => 'Mitglieder'],
            [
                'user_id' => $owner->id,
                'personal_team' => false,
            ]
        );
    }
}
