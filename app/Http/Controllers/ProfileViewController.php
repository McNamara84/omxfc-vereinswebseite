<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Team;
use App\Models\UserPoint;
use App\Models\TodoCategory;

class ProfileViewController extends Controller
{
    public function show(User $user)
    {
        $currentUser = Auth::user();
        $team = $currentUser->currentTeam;

        // Wir erlauben jetzt auch das Anzeigen des eigenen Profils
        $isOwnProfile = $currentUser->id === $user->id;

        if (!$isOwnProfile) {
            // Stelle sicher, dass der anzuzeigende Benutzer im gleichen Team ist
            $membershipInTeam = $team->users()
                ->where('user_id', $user->id)
                ->first();

            if (!$membershipInTeam) {
                return redirect()->route('dashboard')->with('error', 'Profil nicht gefunden.');
            }

            // Korrekte Ermittlung der Rolle des anzuzeigenden Nutzers
            $memberRole = $membershipInTeam->membership->role;
        } else {
            // Bei eigenem Profil die eigene Rolle anzeigen
            $membershipInTeam = $team->users()
                ->where('user_id', $currentUser->id)
                ->first();
            $memberRole = $membershipInTeam ? $membershipInTeam->membership->role : 'Mitglied';
        }

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
        $canViewDetails = $isOwnProfile || in_array($currentUserRole, $allowedRoles);

        // Punkte-Informationen abrufen
        $memberTeam = Team::where('name', 'Mitglieder')->first();

        $userPoints = 0;
        $completedTasks = 0;
        $categoryPoints = [];

        // Badges
        $badges = [];

        if ($memberTeam) {
            // Gesamtpunkte
            $userPoints = UserPoint::where('user_points.user_id', $user->id)
                ->where('user_points.team_id', $memberTeam->id)
                ->sum('points');

            // Anzahl abgeschlossener Aufgaben
            $completedTasks = UserPoint::where('user_points.user_id', $user->id)
                ->where('user_points.team_id', $memberTeam->id)
                ->count();

            // Punkte nach Kategorien gruppieren
            $pointsByCategory = UserPoint::where('user_points.user_id', $user->id)
                ->where('user_points.team_id', $memberTeam->id)
                ->join('todos', 'user_points.todo_id', '=', 'todos.id')
                ->leftJoin('todo_categories', 'todos.category_id', '=', 'todo_categories.id')
                ->selectRaw('COALESCE(todo_categories.name, "Ohne Kategorie") as category, SUM(user_points.points) as total')
                ->groupBy('category')
                ->get();

            foreach ($pointsByCategory as $category) {
                $categoryPoints[$category->category] = $category->total;
            }

            // Badges bestimmen

            // Ersthelfer Badge - für jedes Mitglied, das mind. eine Aufgabe erfüllt hat
            if ($completedTasks > 0) {
                $badges[] = [
                    'name' => 'Ersthelfer',
                    'description' => 'Hat mindestens eine Challenge erfüllt',
                    'image' => route('badges.image', 'BadgeErsthelfer.png'),
                ];
            }

            // Retrologe Badge - für Aufgaben der Kategorie "AG Maddraxikon"
            $maddraxikonCategory = TodoCategory::where('name', 'AG Maddraxikon')->first();

            if ($maddraxikonCategory) {
                $maddraxikonTasks = UserPoint::where('user_points.user_id', $user->id)
                    ->where('user_points.team_id', $memberTeam->id)
                    ->join('todos', 'user_points.todo_id', '=', 'todos.id')
                    ->where('todos.category_id', $maddraxikonCategory->id)
                    ->count();

                if ($maddraxikonTasks > 0) {
                    $badges[] = [
                        'name' => 'Retrologe (Stufe 1)',
                        'description' => 'Hat im Maddraxikon mitgewirkt',
                        'image' => route('badges.image', 'BadgeRetrologe1.png'),
                    ];
                }
            }
        }

        return view('profile.view', [
            'user' => $user,
            'memberRole' => $memberRole,
            'canViewDetails' => $canViewDetails,
            'userPoints' => $userPoints,
            'completedTasks' => $completedTasks,
            'categoryPoints' => $categoryPoints,
            'isOwnProfile' => $isOwnProfile,
            'badges' => $badges,
        ]);
    }
}
