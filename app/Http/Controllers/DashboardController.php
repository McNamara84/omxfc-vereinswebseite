<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Mail\MitgliedGenehmigtMail;
use Illuminate\Support\Facades\Mail;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // Mitglieder zählen (alle außer "Anwärter")
        $memberCount = $team->users()
            ->wherePivotNotIn('role', ['Anwärter'])
            ->count();

        // Anwärter abrufen, nur für Kassenwart, Vorstand, Admin
        $anwaerter = collect();
        $allowedRoles = ['Kassenwart', 'Vorstand', 'Admin'];

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers:
        $userRole = $team->users()
            ->where('user_id', $user->id)
            ->first()
            ->membership
            ->role;

        if (in_array($userRole, $allowedRoles)) {
            $anwaerter = $team->users()
                ->wherePivot('role', 'Anwärter')
                ->get();
        }

        return view('dashboard', compact('memberCount', 'anwaerter'));
    }

    public function approveAnwaerter(User $user)
    {
        $team = $user->currentTeam;
        $team->users()->updateExistingPivot($user->id, ['role' => 'Mitglied']);
        // Mitgliedsdatum setzen
        $user->mitglied_seit = now()->toDateString();
        $user->save();
        Mail::to($user->email)->send(new MitgliedGenehmigtMail($user));

        return back()->with('status', 'Antrag genehmigt.');
    }

    public function rejectAnwaerter(User $user)
    {
        $team = $user->currentTeam;
        $team->users()->detach($user->id);
        $user->delete();

        return back()->with('status', 'Antrag abgelehnt und gelöscht.');
    }
}
