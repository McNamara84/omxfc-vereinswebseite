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
use App\Services\ReviewBaxxService;
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
        private MembersTeamProvider $membersTeamProvider,
        private ReviewBaxxService $reviewBaxxService,
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

        $prominentReviewSpecialOffer = $this->reviewBaxxService->getProminentSpecialOffer();
        $focusCards = $this->buildFocusCards(
            openTodos: $openTodos,
            userPoints: $userPoints,
            romantauschMatches: $romantauschMatches,
            romantauschOffers: $romantauschOffers,
            myReviews: $myReviews,
            fanfictionCount: $fanfictionCount,
        );
        $dashboardGreeting = $this->resolveDashboardGreeting($user);
        $dashboardDescription = $this->resolveDashboardDescription(
            userRole: $userRole,
            allowedRoles: $allowedRoles,
            applicantCount: $anwaerter->count(),
            pendingVerification: $pendingVerification,
        );
        $quickActions = $this->buildQuickActions(
            userRole: $userRole,
            allowedRoles: $allowedRoles,
            applicantCount: $anwaerter->count(),
            pendingVerification: $pendingVerification,
        );

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
            'activities',
            'prominentReviewSpecialOffer',
            'focusCards',
            'dashboardGreeting',
            'dashboardDescription',
            'quickActions',
        ));
    }

    private function buildFocusCards(
        int $openTodos,
        int $userPoints,
        int $romantauschMatches,
        int $romantauschOffers,
        int $myReviews,
        int $fanfictionCount,
    ): array {
        return [
            [
                'title' => 'Offene Challenges',
                'description' => 'Angenommene, noch nicht abgeschlossene Challenges.',
                'value' => $openTodos,
                'href' => route('todos.index'),
                'icon' => 'o-bolt',
                'sr_text' => "Meine offenen Challenges: {$openTodos}",
            ],
            [
                'title' => 'Meine Baxx',
                'description' => 'Aktueller Punktestand für deine Aktivitäten im Verein.',
                'value' => $userPoints,
                'href' => null,
                'icon' => 'o-sparkles',
                'sr_text' => "Meine Baxx: {$userPoints}",
            ],
            [
                'title' => 'Matches in Tauschbörse',
                'description' => 'Offene Treffer aus Angeboten und Gesuchen in der Community.',
                'value' => $romantauschMatches,
                'href' => route('romantausch.index'),
                'icon' => 'o-arrows-right-left',
                'sr_text' => "Meine Matches in der Tauschbörse: {$romantauschMatches}",
            ],
            [
                'title' => 'Angebote in der Tauschbörse',
                'description' => 'Aktive Angebote, die du aktuell mit anderen teilst.',
                'value' => $romantauschOffers,
                'href' => route('romantausch.index'),
                'icon' => 'o-archive-box',
                'sr_text' => "Meine Angebote in der Tauschbörse: {$romantauschOffers}",
            ],
            [
                'title' => 'Meine Rezensionen',
                'description' => 'Dein veröffentlichter Beitragsstand im Rezensionsbereich.',
                'value' => $myReviews,
                'href' => route('reviews.index'),
                'icon' => 'o-book-open',
                'sr_text' => "Meine Rezensionen: {$myReviews}",
            ],
            [
                'title' => 'Fanfiction',
                'description' => 'Veröffentlichte Geschichten aus dem MADDRAX-Universum.',
                'value' => $fanfictionCount,
                'href' => route('fanfiction.index'),
                'icon' => 'o-pencil-square',
                'sr_text' => "Fanfiction: {$fanfictionCount}",
            ],
        ];
    }

    private function buildQuickActions(Role $userRole, array $allowedRoles, int $applicantCount, int $pendingVerification): array
    {
        $actions = [
            [
                'title' => 'Challenges öffnen',
                'description' => 'Finde offene Aufgaben, prüfe Zusagen und springe direkt in deinen Arbeitsmodus.',
                'href' => route('todos.index'),
                'icon' => 'o-bolt',
            ],
            [
                'title' => 'Tauschbörse öffnen',
                'description' => 'Neue Matches prüfen oder schnell selbst Angebote und Gesuche nachziehen.',
                'href' => route('romantausch.index'),
                'icon' => 'o-arrows-right-left',
            ],
            [
                'title' => 'Rezensionen entdecken',
                'description' => 'Neue Rezensionen lesen, kommentieren oder eigene Beiträge weiterentwickeln.',
                'href' => route('reviews.index'),
                'icon' => 'o-book-open',
            ],
            [
                'title' => 'Fantreffen 2026 ansehen',
                'description' => 'Programm, Anmeldung und aktuelle Informationen rund um das Event im Blick behalten.',
                'href' => route('fantreffen.2026'),
                'icon' => 'o-calendar-days',
            ],
        ];

        if (in_array($userRole, $allowedRoles, true)) {
            array_unshift($actions, [
                'title' => 'Verifizierungen prüfen',
                'description' => 'Abgeschlossene Challenges freigeben und nächste Schritte für Teams anstoßen.',
                'href' => route('todos.index').'?filter=pending',
                'icon' => 'o-shield-check',
                'badge' => $pendingVerification > 0 ? (string) $pendingVerification : null,
            ]);

            $actions[] = [
                'title' => 'Fantreffen verwalten',
                'description' => 'Anmeldungen, Zahlungen und operative Eventpunkte in der Admin-Ansicht pflegen.',
                'href' => route('admin.fantreffen.2026'),
                'icon' => 'o-users',
            ];

            if ($applicantCount > 0) {
                array_unshift($actions, [
                    'title' => 'Mitgliedsanträge prüfen',
                    'description' => 'Neue Anwärter sichten, Rückfragen beantworten und Freigaben zügig erledigen.',
                    'href' => route('dashboard'),
                    'icon' => 'o-user-plus',
                    'badge' => (string) $applicantCount,
                ]);
            }
        }

        return $actions;
    }

    private function resolveDashboardGreeting(User $user): string
    {
        $preferredName = trim((string) ($user->vorname ?: str($user->name)->before(' ')));

        return $preferredName !== ''
            ? "Willkommen zurück, {$preferredName}"
            : 'Willkommen zurück';
    }

    private function resolveDashboardDescription(Role $userRole, array $allowedRoles, int $applicantCount, int $pendingVerification): string
    {
        if (! in_array($userRole, $allowedRoles, true)) {
            return 'Dein Einstieg in Challenges, Community-Aktivität, Tauschbörse und aktuelle Vereinsinhalte.';
        }

        $segments = ['Behalte Community-Aktivität, Anträge und laufende Freigaben zentral im Blick.'];

        if ($applicantCount > 0) {
            $segments[] = $applicantCount === 1
                ? 'Gerade wartet ein neuer Mitgliedsantrag auf deine Rückmeldung.'
                : "Gerade warten {$applicantCount} neue Mitgliedsanträge auf deine Rückmeldung.";
        }

        if ($pendingVerification > 0) {
            $segments[] = $pendingVerification === 1
                ? 'Zusätzlich ist eine Challenge zur Verifizierung offen.'
                : "Zusätzlich sind {$pendingVerification} Challenges zur Verifizierung offen.";
        }

        return implode(' ', $segments);
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
