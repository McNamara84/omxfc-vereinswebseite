<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Mail\MitgliedGenehmigtMail;
use Illuminate\Support\Facades\Mail;
use App\Models\Team;
use App\Models\Todo;
use App\Models\UserPoint;
use Illuminate\Support\Facades\DB;

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
        $userMembership = $team->users()
            ->where('user_id', $user->id)
            ->first();

        if (!$userMembership) {
            return redirect()->route('home')->with('error', 'Teamzugehörigkeit nicht gefunden.');
        }

        $userRole = $userMembership->membership->role;

        if (in_array($userRole, $allowedRoles)) {
            $anwaerter = $team->users()
                ->wherePivot('role', 'Anwärter')
                ->get();
        }

        // ToDo-Statistiken abrufen
        $memberTeam = Team::where('name', 'Mitglieder')->first();

        // Initialisierung der Variablen
        $openTodos = 0;
        $userPoints = 0;
        $completedTodos = 0;
        $pendingVerification = 0;
        $topUsers = [];

        if ($memberTeam) {
            // Offene Aufgaben
            $openTodos = Todo::where('team_id', $memberTeam->id)
                ->where('status', 'open')
                ->count();

            // Punkte des angemeldeten Nutzers
            $userPoints = UserPoint::where('user_id', $user->id)
                ->where('team_id', $memberTeam->id)
                ->sum('points');

            // Abgeschlossene Aufgaben des Nutzers
            $completedTodos = UserPoint::where('user_id', $user->id)
                ->where('team_id', $memberTeam->id)
                ->count();

            // Aufgaben, die auf Verifizierung warten (nur für Admins sichtbar)
            if (in_array($userRole, $allowedRoles)) {
                $pendingVerification = Todo::where('team_id', $memberTeam->id)
                    ->where('status', 'completed')
                    ->count();
            }

            // TOP3 Nutzer mit den meisten Punkten
            $topUsers = UserPoint::where('team_id', $memberTeam->id)
                ->select('user_id', DB::raw('SUM(points) as total_points'))
                ->groupBy('user_id')
                ->orderBy('total_points', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($item) {
                    $user = User::find($item->user_id);
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'profile_photo_url' => $user->profile_photo_url,
                        'points' => $item->total_points
                    ];
                });
        }

        return view('dashboard', compact(
            'memberCount',
            'anwaerter',
            'openTodos',
            'userPoints',
            'completedTodos',
            'pendingVerification',
            'userRole',
            'allowedRoles',
            'topUsers'
        ));
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
