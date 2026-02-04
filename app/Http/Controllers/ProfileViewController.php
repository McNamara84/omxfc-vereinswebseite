<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\BookSwap;
use App\Models\Review;
use App\Models\Team;
use App\Models\TodoCategory;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\MaddraxDataService;
use App\Services\UserRoleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileViewController extends Controller
{
    public function __construct(private UserRoleService $userRoleService) {}

    public function show(User $user)
    {
        $currentUser = Auth::user();
        $team = $currentUser->currentTeam;

        // Wir erlauben jetzt auch das Anzeigen des eigenen Profils
        $isOwnProfile = $currentUser->id === $user->id;

        try {
            $currentUserRole = $this->userRoleService->getRole($currentUser, $team);
        } catch (ModelNotFoundException) {
            return redirect()->route('dashboard')->with('error', 'Teamzugehörigkeit nicht gefunden.');
        }

        if (! $isOwnProfile) {
            try {
                $memberRole = $this->userRoleService->getRole($user, $team);
            } catch (ModelNotFoundException) {
                return redirect()->route('dashboard')->with('error', 'Profil nicht gefunden.');
            }
        } else {
            $memberRole = $currentUserRole;
        }

        // Prüfe, ob der Benutzer erweiterte Rechte hat (für detaillierte Ansicht)
        $allowedRoles = [Role::Kassenwart, Role::Vorstand, Role::Admin];
        $canViewDetails = $isOwnProfile || in_array($currentUserRole, $allowedRoles, true);

        $lastActivity = DB::table('sessions')
            ->where('user_id', $user->id)
            ->max('last_activity');

        $lastSeen = $lastActivity ? Carbon::createFromTimestamp($lastActivity) : null;
        $isOnline = $lastSeen && $lastSeen->gt(now()->subMinutes(5));

        // Punkte-Informationen abrufen
        $memberTeam = Team::membersTeam();

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

            // Anzahl abgeschlossener Challenges
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
                    'description' => 'Hat sich mindestens einmal für den Verein engagiert',
                    'image' => asset('images/badges/BadgeErsthelfer.png'),
                ];
            }

            // Retrologe Badge - für Challenges der Kategorie "AG Maddraxikon"
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
                        'image' => asset('images/badges/BadgeRetrologe1.png'),
                    ];
                }
            }
            // Rezensator Badges - für verfasste Rezensionen
            $reviewCount = Review::where('team_id', $memberTeam->id)
                ->where('user_id', $user->id)
                ->count();

            if ($reviewCount >= 1000) {
                $badges[] = [
                    'name' => 'Rezensator (Stufe 3)',
                    'description' => 'Hat 1000 Rezensionen verfasst',
                    'image' => asset('images/badges/BadgeRezensator3.png'),
                ];
            } elseif ($reviewCount >= 100) {
                $badges[] = [
                    'name' => 'Rezensator (Stufe 2)',
                    'description' => 'Hat 100 Rezensionen verfasst',
                    'image' => asset('images/badges/BadgeRezensator2.png'),
                ];
            } elseif ($reviewCount >= 10) {
                $badges[] = [
                    'name' => 'Rezensator (Stufe 1)',
                    'description' => 'Hat 10 Rezensionen verfasst',
                    'image' => asset('images/badges/BadgeRezensator1.png'),
                ];
            }

            // Weltrat-Kritiker Badge - für vollständige Rezension des Weltrat-Zyklus
            $weltratNumbers = collect(MaddraxDataService::loadData())
                ->filter(fn ($row) => Str::contains($row['zyklus'] ?? '', 'Weltrat'))
                ->pluck('nummer')
                ->map(fn ($n) => (int) $n);

            if ($weltratNumbers->isNotEmpty()) {
                $reviewedBooks = Review::where('team_id', $memberTeam->id)
                    ->where('user_id', $user->id)
                    ->whereHas('book', function ($q) use ($weltratNumbers) {
                        $q->whereIn('roman_number', $weltratNumbers);
                    })
                    ->pluck('book_id')
                    ->unique()
                    ->count();

                if ($reviewedBooks === $weltratNumbers->count()) {
                    $badges[] = [
                        'name' => 'Weltrat-Kritiker',
                        'description' => 'Hat jeden Roman des Weltrat-Zyklus rezensiert',
                        'image' => asset('images/badges/BadgeWeltratKritiker.png'),
                    ];
                }
            }

            // Amraka-Kritiker Badge - für vollständige Rezension des Amraka-Zyklus
            $amrakaNumbers = collect(MaddraxDataService::loadData())
                ->filter(fn ($row) => Str::contains($row['zyklus'] ?? '', 'Amraka'))
                ->pluck('nummer')
                ->map(fn ($n) => (int) $n);

            if ($amrakaNumbers->isNotEmpty()) {
                $reviewedBooks = Review::where('team_id', $memberTeam->id)
                    ->where('user_id', $user->id)
                    ->whereHas('book', function ($q) use ($amrakaNumbers) {
                        $q->whereIn('roman_number', $amrakaNumbers);
                    })
                    ->pluck('book_id')
                    ->unique()
                    ->count();

                if ($reviewedBooks === $amrakaNumbers->count()) {
                    $badges[] = [
                        'name' => 'Amraka-Kritiker',
                        'description' => 'Hat jeden Roman des Amraka-Zyklus rezensiert',
                        'image' => asset('images/badges/BadgeAmrakaKritiker.png'),
                    ];
                }
            }

            // Händler Badges - für abgeschlossene Tauschtransaktionen
            $tradeCount = BookSwap::whereNotNull('completed_at')
                ->where(function ($query) use ($user) {
                    $query->whereHas('offer', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })->orWhereHas('request', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
                })
                ->count();

            if ($tradeCount >= 1000) {
                $badges[] = [
                    'name' => 'Händler (Stufe 4)',
                    'description' => 'Hat 1000 Tauschgeschäfte abgeschlossen',
                    'image' => asset('images/badges/BadgeHaendler4.png'),
                ];
            } elseif ($tradeCount >= 100) {
                $badges[] = [
                    'name' => 'Händler (Stufe 3)',
                    'description' => 'Hat 100 Tauschgeschäfte abgeschlossen',
                    'image' => asset('images/badges/BadgeHaendler3.png'),
                ];
            } elseif ($tradeCount >= 10) {
                $badges[] = [
                    'name' => 'Händler (Stufe 2)',
                    'description' => 'Hat 10 Tauschgeschäfte abgeschlossen',
                    'image' => asset('images/badges/BadgeHaendler2.png'),
                ];
            } elseif ($tradeCount >= 1) {
                $badges[] = [
                    'name' => 'Händler (Stufe 1)',
                    'description' => 'Hat einen Tausch erfolgreich abgeschlossen',
                    'image' => asset('images/badges/BadgeHaendler1.png'),
                ];
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
            'lastSeen' => $lastSeen,
            'isOnline' => $isOnline,
            'badges' => $badges,
        ]);
    }
}
