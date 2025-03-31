<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;


class ProfileViewController extends Controller
{
    public function show(User $user)
    {
        $currentUser = Auth::user();
        $team = $currentUser->currentTeam;

        // Stelle sicher, dass der Benutzer nicht versucht, eigenes Profil anzuzeigen
        if ($currentUser->id === $user->id) {
            return redirect()->route('profile.show');
        }

        // Stelle sicher, dass der anzuzeigende Benutzer im gleichen Team ist
        $membershipInTeam = $team->users()
            ->where('user_id', $user->id)
            ->first();

        if (!$membershipInTeam) {
            return redirect()->route('dashboard')->with('error', 'Profil nicht gefunden.');
        }

        // Korrekte Ermittlung der Rolle des anzuzeigenden Nutzers
        $memberRole = $membershipInTeam->membership->role;

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers
        $currentUserMembership = $team->users()
            ->where('user_id', $currentUser->id)
            ->first();

        if (!$currentUserMembership) {
            return redirect()->route('dashboard')->with('error', 'Teamzugehörigkeit nicht gefunden.');
        }

        $currentUserRole = $currentUserMembership->membership->role;

        // Prüfe, ob der Benutzer erweiterte Rechte hat (für detaillierte Ansicht)
        $allowedRoles = ['Kassenwart', 'Vorstand', 'Admin'];
        $canViewDetails = in_array($currentUserRole, $allowedRoles);

        return view('profile.view', [
            'user' => $user,
            'memberRole' => $memberRole,
            'canViewDetails' => $canViewDetails
        ]);
    }
}
