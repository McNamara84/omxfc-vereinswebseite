<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Mail\MitgliedGenehmigtMail;
use App\Models\Activity;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\LockedMembersTeamMemberships;
use App\Services\MembersTeamMembershipLock;
use App\Services\MembersTeamProvider;
use App\Services\ReviewBaxxService;
use App\Services\TourAssignmentService;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class DashboardController extends Controller
{
    public function __construct(
        private UserRoleService $userRoleService,
        private MembersTeamProvider $membersTeamProvider,
        private ReviewBaxxService $reviewBaxxService,
        private DashboardMetricsService $dashboardMetricsService,
        private TourAssignmentService $tourAssignmentService,
        private MembersTeamMembershipLock $membershipLock,
    ) {}

    public function index()
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $team = $this->membersTeamProvider->getMembersTeamOrAbort();
        $allowedRoles = [Role::Kassenwart, Role::Vorstand, Role::Admin];

        try {
            $userRole = $this->userRoleService->getRole($user, $team);
        } catch (ModelNotFoundException) {
            return redirect()->route('home')->with('error', 'Teamzugehörigkeit nicht gefunden.');
        }

        $anwaerter = collect();

        if (in_array($userRole, $allowedRoles, true)) {
            $cachedApplicants = Cache::remember(
                self::applicantsCacheKey($team->id),
                now()->addMinutes(10),
                fn () => $team->users()
                    ->wherePivot('role', Role::Anwaerter->value)
                    ->get(['users.id', 'users.name', 'users.email', 'users.mitgliedsbeitrag'])
                    ->map(fn (User $applicant) => $applicant->getAttributes())
                    ->values()
                    ->all()
            );

            if (is_array($cachedApplicants)) {
                $anwaerter = User::hydrate(array_values(array_filter(
                    $cachedApplicants,
                    static fn (mixed $applicant): bool => is_array($applicant),
                )));
            }
        }

        $dashboard = $this->dashboardMetricsService->build($user, $team, $userRole, $anwaerter->count());
        $showGovernanceTools = in_array($userRole, $allowedRoles, true);
        $dashboardGreeting = $this->resolveDashboardGreeting($user);
        $dashboardDescription = $this->resolveDashboardDescription(
            $userRole,
            $allowedRoles,
            $anwaerter->count(),
            $dashboard['pendingVerification'],
        );
        $dashboardPrimaryAction = collect($dashboard['tasks'])
            ->first(fn (array $task): bool => ($task['count'] ?? 0) > 0);
        ['entries' => $topUsersEntries, 'summary' => $topUsersSummary, 'payload' => $topUsersPayload] = $this->buildTopUsersViewData($dashboard['topUsers']);

        return view('dashboard', [
            ...$dashboard,
            'anwaerter' => $anwaerter,
            'userRole' => $userRole,
            'allowedRoles' => $allowedRoles,
            'prominentReviewSpecialOffer' => $this->reviewBaxxService->getProminentSpecialOffer(),
            'dashboardGreeting' => $dashboardGreeting,
            'dashboardDescription' => $dashboardDescription,
            'dashboardPrimaryAction' => $dashboardPrimaryAction,
            'quickActions' => $this->buildQuickActions(),
            'showGovernanceTools' => $showGovernanceTools,
            'topUsersEntries' => $topUsersEntries,
            'topUsersSummary' => $topUsersSummary,
            'topUsersPayload' => $topUsersPayload,
        ]);
    }

    private function buildTopUsersViewData(iterable $topUsers): array
    {
        $entries = collect($topUsers)->values()->map(function ($user) {
            $points = (int) $user['points'];

            return [
                ...$user,
                'points' => $points,
                'formatted_points' => number_format($points, 0, ',', '.'),
            ];
        });
        $summary = $entries->isNotEmpty()
            ? 'Top '.$entries->count().' Baxx-Sammler: '.$entries->map(function ($user, $index) {
                return ($index + 1).'. '.$user['name'].' ('.$user['formatted_points'].' Baxx)';
            })->implode(', ')
            : null;
        $payload = $entries->map(fn ($user): array => [
            'id' => $user['id'],
            'name' => $user['name'],
            'points' => (int) $user['points'],
            'formatted_points' => $user['formatted_points'],
        ])->toArray();

        return ['entries' => $entries, 'summary' => $summary, 'payload' => $payload];
    }

    private function buildQuickActions(): array
    {
        return [
            ['title' => 'Baxx verdienen', 'href' => route('todos.index'), 'icon' => 'o-bolt'],
            ['title' => 'Tauschbörse', 'href' => route('romantausch.index'), 'icon' => 'o-arrows-right-left'],
            ['title' => 'Rezensionen', 'href' => route('reviews.index'), 'icon' => 'o-book-open'],
            ['title' => 'Veranstaltung', 'href' => route('veranstaltungen.aktuell'), 'icon' => 'o-calendar-days'],
        ];
    }

    private function resolveDashboardGreeting(User $user): string
    {
        $preferredName = trim((string) ($user->vorname ?: str($user->name)->before(' ')));

        return $preferredName !== '' ? "Willkommen zurück, {$preferredName}" : 'Willkommen zurück';
    }

    private function resolveDashboardDescription(Role $userRole, array $allowedRoles, int $applicantCount, int $pendingVerification): string
    {
        if (! in_array($userRole, $allowedRoles, true)) {
            return 'Deine nächsten Schritte, dein Fortschritt und Neues aus der Community.';
        }

        $openGovernanceTasks = $applicantCount + $pendingVerification;

        return $openGovernanceTasks > 0
            ? trans_choice(':count Verwaltungsaufgabe wartet auf dich.|:count Verwaltungsaufgaben warten auf dich.', $openGovernanceTasks, ['count' => $openGovernanceTasks])
            : 'Deine nächsten Schritte und der aktuelle Stand im Verein.';
    }

    public function approveAnwaerter(User $user)
    {
        $this->membersTeamProvider->getMembersTeamOrAbort();
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        $locked = $this->membershipLock->run(
            [$actor->id, $user->id],
            function (LockedMembersTeamMemberships $memberships) use ($actor, $user): ?array {
                abort_unless($memberships->hasRole(
                    $actor->id,
                    Role::Kassenwart,
                    Role::Vorstand,
                    Role::Admin,
                ), 403);

                if ($memberships->role($user->id) !== Role::Anwaerter) {
                    return null;
                }

                $memberships->team->users()->updateExistingPivot($user->id, ['role' => Role::Mitglied->value]);
                $lockedUser = $memberships->user($user->id);
                $lockedUser->forceFill(['mitglied_seit' => now()->toDateString()])->save();

                return [$memberships->team, $lockedUser];
            },
        );

        if ($locked === null) {
            return back()->with('error', 'Der Antrag wurde bereits anderweitig bearbeitet.');
        }

        [$team, $user] = $locked;
        $this->tourAssignmentService->assignAutoToursForApprovedMember($user, $actor);
        Mail::to($user->email)->queue(new MitgliedGenehmigtMail($user));
        Activity::create([
            'user_id' => $actor->id,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'action' => 'member_approved',
        ]);

        Cache::forget("member_count_{$team->id}");
        Cache::forget(self::applicantsCacheKey($team->id));
        Cache::forget(self::legacyApplicantsCacheKey($team->id));

        return back()->with('status', 'Antrag genehmigt.');
    }

    public function rejectAnwaerter(User $user)
    {
        $this->membersTeamProvider->getMembersTeamOrAbort();
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        $team = $this->membershipLock->run(
            [$actor->id, $user->id],
            function (LockedMembersTeamMemberships $memberships) use ($actor, $user) {
                abort_unless($memberships->hasRole(
                    $actor->id,
                    Role::Kassenwart,
                    Role::Vorstand,
                    Role::Admin,
                ), 403);

                if ($memberships->role($user->id) !== Role::Anwaerter) {
                    return null;
                }

                $memberships->team->users()->detach($user->id);
                $memberships->user($user->id)->delete();

                return $memberships->team;
            },
        );

        if ($team === null) {
            return back()->with('error', 'Der Antrag wurde bereits anderweitig bearbeitet.');
        }

        Cache::forget("member_count_{$team->id}");
        Cache::forget(self::applicantsCacheKey($team->id));
        Cache::forget(self::legacyApplicantsCacheKey($team->id));

        return back()->with('status', 'Antrag abgelehnt und gelöscht.');
    }

    private static function applicantsCacheKey(int $teamId): string
    {
        return self::legacyApplicantsCacheKey($teamId).'.v2';
    }

    private static function legacyApplicantsCacheKey(int $teamId): string
    {
        return "anwaerter_{$teamId}";
    }
}
