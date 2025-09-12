<?php

namespace App\Http\Controllers;

use App\Mail\MitgliedGenehmigtMail;
use App\Models\Activity;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Team;
use App\Models\Todo;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $cacheFor = now()->addMinutes(10);

        // Mitglieder zählen (alle außer "Anwärter")
        $memberCount = Cache::remember(
            "member_count_{$team->id}",
            $cacheFor,
            fn () => $team->users()
                ->wherePivotNotIn('role', ['Anwärter'])
                ->count()
        );

        // Anwärter abrufen, nur für Kassenwart, Vorstand, Admin
        $anwaerter = collect();
        $allowedRoles = ['Kassenwart', 'Vorstand', 'Admin'];

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers:
        $userMembership = $team->users()
            ->where('user_id', $user->id)
            ->first();

        if (! $userMembership) {
            return redirect()->route('home')->with('error', 'Teamzugehörigkeit nicht gefunden.');
        }

        $userRole = $userMembership->membership->role;

        if (in_array($userRole, $allowedRoles)) {
            $anwaerter = Cache::remember(
                "anwaerter_{$team->id}",
                $cacheFor,
                fn () => $team->users()
                    ->wherePivot('role', 'Anwärter')
                    ->get()
            );
        }

        // ToDo-Statistiken abrufen
        $memberTeam = Team::membersTeam();

        // Initialisierung der Variablen
        $openTodos = 0;
        $userPoints = 0;
        $completedTodos = 0;
        $pendingVerification = 0;
        $topUsers = [];
        $allReviews = 0;
        $myReviews = 0;
        $myReviewComments = 0;

        if ($memberTeam) {
            // Offene Aufgaben
            $openTodos = Cache::remember(
                "open_todos_{$memberTeam->id}",
                $cacheFor,
                fn () => Todo::where('team_id', $memberTeam->id)
                    ->where('status', 'open')
                    ->count()
            );

            // Punkte des angemeldeten Nutzers
            $userPoints = Cache::remember(
                "user_points_{$memberTeam->id}_{$user->id}",
                $cacheFor,
                fn () => UserPoint::where('user_id', $user->id)
                    ->where('team_id', $memberTeam->id)
                    ->sum('points')
            );

            // Abgeschlossene Aufgaben des Nutzers
            $completedTodos = Cache::remember(
                "completed_todos_{$memberTeam->id}_{$user->id}",
                $cacheFor,
                fn () => UserPoint::where('user_id', $user->id)
                    ->where('team_id', $memberTeam->id)
                    ->count()
            );

            // Aufgaben, die auf Verifizierung warten (nur für Admins sichtbar)
            if (in_array($userRole, $allowedRoles)) {
                $pendingVerification = Cache::remember(
                    "pending_verification_{$memberTeam->id}",
                    $cacheFor,
                    fn () => Todo::where('team_id', $memberTeam->id)
                        ->where('status', 'completed')
                        ->count()
                );
            }

            // TOP3 Nutzer mit den meisten Punkten
            $topUsers = Cache::remember(
                "top_users_{$memberTeam->id}",
                $cacheFor,
                function () use ($memberTeam) {
                    return UserPoint::where('team_id', $memberTeam->id)
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
                                'points' => $item->total_points,
                            ];
                        });
                }
            );
        }

        // Rezensionen zählen
        $allReviews = Cache::remember(
            "all_reviews_{$memberTeam->id}",
            $cacheFor,
            fn () => Review::where('team_id', $memberTeam->id)->count()
        );
        $myReviews = Cache::remember(
            "my_reviews_{$memberTeam->id}_{$user->id}",
            $cacheFor,
            fn () => Review::where('team_id', $memberTeam->id)
                ->where('user_id', $user->id)
                ->count()
        );

        // Eigene Kommentare auf Rezensionen
        $myReviewComments = Cache::remember(
            "my_review_comments_{$memberTeam->id}_{$user->id}",
            $cacheFor,
            fn () => ReviewComment::where('user_id', $user->id)
                ->whereHas('review', function ($query) use ($memberTeam) {
                    $query->where('team_id', $memberTeam->id);
                })
                ->count()
        );

        $activities = Activity::with(['user', 'subject'])
            ->latest()
            ->limit(10)
            ->get();

        return view('dashboard', compact(
            'memberCount',
            'anwaerter',
            'openTodos',
            'userPoints',
            'completedTodos',
            'pendingVerification',
            'userRole',
            'allowedRoles',
            'topUsers',
            'allReviews',
            'myReviews',
            'myReviewComments',
            'activities'
        ));
    }

    public function approveAnwaerter(User $user)
    {
        $team = $user->currentTeam;
        $team->users()->updateExistingPivot($user->id, ['role' => 'Mitglied']);
        // Mitgliedsdatum setzen
        $user->mitglied_seit = now()->toDateString();
        $user->save();
        Mail::to($user->email)->queue(new MitgliedGenehmigtMail($user));

        Activity::create([
            'user_id' => Auth::id(),
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'action' => 'member_approved',
        ]);

        Cache::forget("member_count_{$team->id}");
        Cache::forget("anwaerter_{$team->id}");

        return back()->with('status', 'Antrag genehmigt.');
    }

    public function rejectAnwaerter(User $user)
    {
        $team = $user->currentTeam;
        $team->users()->detach($user->id);
        $user->delete();

        Cache::forget("member_count_{$team->id}");
        Cache::forget("anwaerter_{$team->id}");

        return back()->with('status', 'Antrag abgelehnt und gelöscht.');
    }
}
