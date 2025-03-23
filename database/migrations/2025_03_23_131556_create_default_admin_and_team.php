<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Jetstream\Jetstream;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Admin-Account anlegen
        $adminUser = User::create([
            'name' => 'Holger Ehrmann',
            'email' => 'info@maddraxikon.com',
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
            'mitgliedsbeitrag' => 36.00
        ]);

        // Team „Mitglieder“ erstellen
        $team = Jetstream::newTeamModel()->forceFill([
            'name' => 'Mitglieder',
            'user_id' => $adminUser->id,
            'personal_team' => false,
        ]);
        $team->save();

        // Admin dem Team mit Rolle „Admin“ hinzufügen
        $team->users()->attach($adminUser, ['role' => 'Admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $teamModel = Jetstream::newTeamModel();
        $team = $teamModel->where('name', 'Mitglieder')->first();
        
        if ($team) {
            $team->users()->detach();
            $team->delete();
        }

        User::where('email', 'info@maddraxikon.com')->delete();
    }
};
