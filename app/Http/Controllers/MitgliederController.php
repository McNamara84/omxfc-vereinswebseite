<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MitgliederController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // Nur Nutzer mit Rollen außer "Anwärter" anzeigen
        $members = $team->users()
            ->wherePivotNotIn('role', ['Anwärter'])
            ->get();

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers
        $userRole = $team->users()
            ->where('user_id', $user->id)
            ->first()
            ->membership
            ->role;

        // Prüft, ob der aktuelle Benutzer erweiterte Rechte hat
        $allowedRoles = ['Kassenwart', 'Vorstand', 'Admin'];
        $canViewDetails = in_array($userRole, $allowedRoles);

        return view('mitglieder.index', [
            'members' => $members,
            'canViewDetails' => $canViewDetails
        ]);
    }
}
