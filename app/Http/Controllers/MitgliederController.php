<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MitgliederController extends Controller
{
    public function index()
    {
        // Bestehender Code bleibt unverändert...
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

        // Rollenrangfolge festlegen (höhere Zahl = höherer Rang)
        $roleRanks = [
            'Mitglied' => 1,
            'Ehrenmitglied' => 2,
            'Kassenwart' => 3,
            'Vorstand' => 4,
            'Admin' => 5
        ];

        // Aktuellen Rang des Users ermitteln
        $currentUserRank = $roleRanks[$userRole] ?? 0;

        return view('mitglieder.index', [
            'members' => $members,
            'canViewDetails' => $canViewDetails,
            'currentUser' => $user,
            'currentUserRank' => $currentUserRank,
            'roleRanks' => $roleRanks
        ]);
    }
    public function removeMember(User $user)
    {
        $currentUser = Auth::user();
        $team = $currentUser->currentTeam;

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers
        $currentUserRole = $team->users()
            ->where('user_id', $currentUser->id)
            ->first()
            ->membership
            ->role;

        // Rolle des zu entfernenden Nutzers
        $memberRole = $team->users()
            ->where('user_id', $user->id)
            ->first()
            ->membership
            ->role;

        // Rollenrangfolge festlegen (höhere Zahl = höherer Rang)
        $roleRanks = [
            'Mitglied' => 1,
            'Ehrenmitglied' => 2,
            'Kassenwart' => 3,
            'Vorstand' => 4,
            'Admin' => 5
        ];

        // Prüfen, ob der aktuelle Nutzer die Berechtigung hat
        if (($roleRanks[$currentUserRole] ?? 0) <= ($roleRanks[$memberRole] ?? 0)) {
            return back()->with('error', 'Du hast keine Berechtigung, dieses Mitglied zu entfernen.');
        }

        // Prüfen, ob das Mitglied sich selbst entfernen will
        if ($currentUser->id === $user->id) {
            return back()->with('error', 'Du kannst deine eigene Mitgliedschaft nicht beenden.');
        }

        // Mitglied aus Team entfernen
        $team->users()->detach($user->id);

        // Nutzer löschen
        $user->delete();

        return back()->with('status', 'Die Mitgliedschaft wurde erfolgreich beendet.');
    }
}
