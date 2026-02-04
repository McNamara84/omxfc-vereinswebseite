<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Jetstream\Jetstream;

class DefaultAdminAndTeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::create([
            'name' => 'Holger Ehrmann',
            'email' => 'info@maddraxikon.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'), // Standard-Passwort; später ändern!
            'vorname' => 'Holger',
            'nachname' => 'Ehrmann',
            'strasse' => 'Musterstraße',
            'hausnummer' => '123',
            'plz' => '12345',
            'stadt' => 'Musterstadt',
            'land' => 'Deutschland',
            'telefon' => '0123456789',
            'verein_gefunden' => 'Sonstiges',
            'mitgliedsbeitrag' => 36.00,
        ]);

        $team = Jetstream::newTeamModel()->forceFill([
            'name' => 'Mitglieder',
            'user_id' => $adminUser->id,
            'personal_team' => false,
        ]);
        $team->save();

        $team->users()->attach($adminUser, ['role' => 'Admin']);

        $adminUser->forceFill([
            'current_team_id' => $team->id,
        ])->save();
    }
}
