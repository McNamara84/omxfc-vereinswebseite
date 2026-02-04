<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Mail\MitgliedGenehmigtMail;
use App\Models\Activity;
use App\Models\BookOffer;
use App\Models\BookSwap;
use App\Models\Fanfiction;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Todo;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\MembersTeamProvider;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DashboardController extends Controller
{
    public function __construct(
        private UserRoleService $userRoleService,
        private MembersTeamProvider $membersTeamProvider
    ) {}

    public function index()
    {
        $user = Auth::user();
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        $cacheFor = now()->addMinutes(10);

        // Anwärter abrufen, nur für Kassenwart, Vorstand, Admin
        $anwaerter = collect();
        $allowedRoles = [Role::Kassenwart, Role::Vorstand, Role::Admin];

        // Korrekte Ermittlung der Rolle des eingeloggten Nutzers
        try {
            $userRole = $this->userRoleService->getRole($user, $team);
        } catch (ModelNotFoundException) {
            return redirect()->route('home')->with('error', 'Teamzugehörigkeit nicht gefunden.');
        }

        if (in_array($userRole, $allowedRoles, true)) {
            $anwaerter = Cache::remember(
                "anwaerter_{$team->id}",
                $cacheFor,
                fn () => $team->users()
                    ->wherePivot('role', Role::Anwaerter->value)
                    ->get()
            );
        }

        // ToDo-Statistiken abrufen

        // Initialisierung der Variablen
        $openTodos = 0;
        $userPoints = 0;
        $pendingVerification = 0;
        $topUsers = [];
        $myReviews = 0;
        $myReviewComments = 0;
        $romantauschMatches = 0;
        $romantauschOffers = 0;
        $fanfictionCount = 0;

        // Offene Aufgaben
        $openTodosByTeam = Cache::remember(
            "open_todos_{$user->id}",
            $cacheFor,
            fn () => Todo::query()
                ->select('team_id', DB::raw('COUNT(*) as total'))
                ->where('assigned_to', $user->id)
                ->where('status', TodoStatus::Assigned->value)
                ->groupBy('team_id')
                ->pluck('total', 'team_id')
                ->all()
        );

        $openTodos = $openTodosByTeam[$team->id] ?? 0;

        // Punkte des angemeldeten Nutzers
        $userPoints = Cache::remember(
            "user_points_{$team->id}_{$user->id}",
            $cacheFor,
            fn () => UserPoint::where('user_id', $user->id)
                ->where('team_id', $team->id)
                ->sum('points')
        );

        // Aufgaben, die auf Verifizierung warten (nur für Admins sichtbar)
        if (in_array($userRole, $allowedRoles, true)) {
            $pendingVerification = Cache::remember(
                "pending_verification_{$team->id}",
                $cacheFor,
                fn () => Todo::where('team_id', $team->id)
                    ->where('status', 'completed')
                    ->count()
            );
        }

        // TOP3 Nutzer mit den meisten Punkten
        $topUsers = Cache::remember(
            "top_users_{$team->id}",
            $cacheFor,
            function () use ($team) {
                return UserPoint::where('team_id', $team->id)
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

        $myReviews = Cache::remember(
            "my_reviews_{$team->id}_{$user->id}",
            $cacheFor,
            fn () => Review::where('team_id', $team->id)
                ->where('user_id', $user->id)
                ->count()
        );

        // Eigene Kommentare auf Rezensionen
        $myReviewComments = Cache::remember(
            "my_review_comments_{$team->id}_{$user->id}",
            $cacheFor,
            fn () => ReviewComment::where('user_id', $user->id)
                ->whereHas('review', function ($query) use ($team) {
                    $query->where('team_id', $team->id);
                })
                ->count()
        );

        $romantauschMatches = Cache::remember(
            "romantausch_matches_{$team->id}_{$user->id}",
            $cacheFor,
            fn () => BookSwap::query()
                ->join('book_offers', 'book_swaps.offer_id', '=', 'book_offers.id')
                ->join('book_requests', 'book_swaps.request_id', '=', 'book_requests.id')
                ->whereNull('book_swaps.completed_at')
                ->where(function ($query) use ($user) {
                    $query->where('book_offers.user_id', $user->id)
                        ->orWhere('book_requests.user_id', $user->id);
                })
                ->count()
        );

        $romantauschOffers = Cache::remember(
            "romantausch_offers_{$user->id}",
            $cacheFor,
            fn () => BookOffer::query()
                ->where('user_id', $user->id)
                ->where('completed', false)
                ->count()
        );

        // Anzahl veröffentlichter Fanfiction-Storys
        $fanfictionCount = Cache::remember(
            "fanfiction_count_{$team->id}",
            $cacheFor,
            fn () => Fanfiction::where('team_id', $team->id)
                ->published()
                ->count()
        );

        $activities = Activity::with(['user', 'subject'])
            ->latest()
            ->limit(10)
            ->get();

        return view('dashboard', compact(
            'anwaerter',
            'openTodos',
            'userPoints',
            'pendingVerification',
            'userRole',
            'allowedRoles',
            'topUsers',
            'myReviews',
            'myReviewComments',
            'romantauschMatches',
            'romantauschOffers',
            'fanfictionCount',
            'activities'
        ));
    }

    public function approveAnwaerter(User $user)
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();
        $team->users()->updateExistingPivot($user->id, ['role' => Role::Mitglied->value]);
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
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();
        $team->users()->detach($user->id);
        $user->delete();

        Cache::forget("member_count_{$team->id}");
        Cache::forget("anwaerter_{$team->id}");

        return back()->with('status', 'Antrag abgelehnt und gelöscht.');
    }
}
