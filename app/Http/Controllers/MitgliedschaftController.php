<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Mail\MitgliedAntragEingereicht;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Jetstream;

class MitgliedschaftController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'vorname' => 'required|string|max:255',
            'nachname' => 'required|string|max:255',
            'strasse' => 'required|string|max:255',
            'hausnummer' => 'required|string|max:10',
            'plz' => 'required|string|max:10',
            'stadt' => 'required|string|max:255',
            'land' => 'required|string',
            'mail' => 'required|email|unique:users,email',
            'passwort' => 'required|confirmed|min:6',
            'mitgliedsbeitrag' => 'required|numeric|min:12|max:120',
            'telefon' => 'nullable|string|max:20',
            'verein_gefunden' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'name' => $request->vorname.' '.$request->nachname,
            'email' => $request->mail,
            'password' => Hash::make($request->passwort),
            'current_team_id' => 1,
            'vorname' => $request->vorname,
            'nachname' => $request->nachname,
            'strasse' => $request->strasse,
            'hausnummer' => $request->hausnummer,
            'plz' => $request->plz,
            'stadt' => $request->stadt,
            'land' => $request->land,
            'telefon' => $request->telefon,
            'verein_gefunden' => $request->verein_gefunden,
            'mitgliedsbeitrag' => $request->mitgliedsbeitrag,
        ]);

        // Sicherstellen, dass das Team korrekt angelegt wird:
        $team = Jetstream::newTeamModel()->firstOrCreate(
            ['name' => 'Mitglieder'],
            ['user_id' => $user->id, 'personal_team' => false]
        );

        // Den User dem Team mit Rolle "Anwärter" zuweisen:
        $team->users()->attach($user, ['role' => Role::Anwaerter->value]);

        // Mailversand
        Mail::to($user->email)->queue(new MitgliedAntragEingereicht($user));

        return response()->json([
            'success' => true,
            'message' => 'Mitgliedschaftsantrag erfolgreich eingereicht.',
        ]);
    }
}
