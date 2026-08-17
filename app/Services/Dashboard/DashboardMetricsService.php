<?php

namespace App\Services\Dashboard;

use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Models\Activity;
use App\Models\BookOffer;
use App\Models\BookSwap;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\FantreffenAnmeldung;
use App\Models\PollVote;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\Todo;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\Veranstaltung;
use App\Services\Polls\ActivePollResolver;
use App\Services\RewardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public const RECENT_ACTIVITY_DAYS = 7;

    public const RECENT_CONTENT_DAYS = 30;

    private const GOVERNANCE_ROLES = [
        Role::Admin,
        Role::Vorstand,
        Role::Kassenwart,
    ];

    public function __construct(
        private readonly RewardService $rewardService,
        private readonly ActivePollResolver $activePollResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, Team $team, Role $role, int $applicantCount): array
    {
        $cacheFor = now()->addMinutes(10);
        $isGovernance = in_array($role, self::GOVERNANCE_ROLES, true);

        $openTodosByTeam = Cache::remember(
            "open_todos_{$user->id}",
            $cacheFor,
            fn (): array => Todo::query()
                ->select('team_id', DB::raw('COUNT(*) as total'))
                ->where('assigned_to', $user->id)
                ->where('status', TodoStatus::Assigned->value)
                ->groupBy('team_id')
                ->pluck('total', 'team_id')
                ->map(fn ($count): int => (int) $count)
                ->all()
        );
        $openTodos = (int) ($openTodosByTeam[$team->id] ?? 0);

        $pendingVerification = $isGovernance
            ? (int) Cache::remember(
                "pending_verification_{$team->id}",
                $cacheFor,
                fn (): int => Todo::query()
                    ->where('team_id', $team->id)
                    ->where('status', TodoStatus::Completed->value)
                    ->count()
            )
            : 0;

        $walletState = $this->rewardService->getWalletState($user);
        $availableBaxx = (int) ($walletState['availableBaxx'] ?? 0);
        $walletWarning = $walletState['warning'] ?? null;

        $romantauschMatches = (int) Cache::remember(
            "romantausch_matches_{$team->id}_{$user->id}",
            $cacheFor,
            fn (): int => BookSwap::query()
                ->join('book_offers', 'book_swaps.offer_id', '=', 'book_offers.id')
                ->join('book_requests', 'book_swaps.request_id', '=', 'book_requests.id')
                ->whereNull('book_swaps.completed_at')
                ->where(function ($query) use ($user): void {
                    $query->where('book_offers.user_id', $user->id)
                        ->orWhere('book_requests.user_id', $user->id);
                })
                ->count()
        );

        $romantauschOffers = (int) Cache::remember(
            "romantausch_offers_{$user->id}",
            $cacheFor,
            fn (): int => BookOffer::query()
                ->where('user_id', $user->id)
                ->where('completed', false)
                ->count()
        );

        $myReviews = (int) Cache::remember(
            "my_reviews_{$team->id}_{$user->id}",
            $cacheFor,
            fn (): int => Review::query()
                ->where('team_id', $team->id)
                ->where('user_id', $user->id)
                ->count()
        );

        $myReviewComments = (int) Cache::remember(
            "my_review_comments_{$team->id}_{$user->id}",
            $cacheFor,
            fn (): int => ReviewComment::query()
                ->where('user_id', $user->id)
                ->whereHas('review', fn (Builder $query) => $query->where('team_id', $team->id))
                ->count()
        );

        $myFanfictionComments = (int) Cache::remember(
            "my_fanfiction_comments_{$team->id}_{$user->id}",
            $cacheFor,
            fn (): int => FanfictionComment::query()
                ->where('user_id', $user->id)
                ->whereHas('fanfiction', fn (Builder $query) => $query->where('team_id', $team->id))
                ->count()
        );

        $myFanfictions = (int) Cache::remember(
            "my_fanfictions_{$team->id}_{$user->id}",
            $cacheFor,
            fn (): int => Fanfiction::query()
                ->where('team_id', $team->id)
                ->where('user_id', $user->id)
                ->published()
                ->count()
        );

        $fanfictionCount = (int) Cache::remember(
            "fanfiction_count_{$team->id}",
            $cacheFor,
            fn (): int => Fanfiction::query()
                ->where('team_id', $team->id)
                ->published()
                ->count()
        );

        $topUsers = $this->topUsers($team, $cacheFor);
        $personalRank = $this->personalRank($user, $team);
        $nextReward = $this->nextReward($user);
        $event = Veranstaltung::featuredPublic();
        $eventRegistration = $event
            ? FantreffenAnmeldung::query()
                ->where('veranstaltung_id', $event->id)
                ->where('user_id', $user->id)
                ->exists()
            : false;
        $poll = $this->activePollResolver->current();
        $pollIsOpen = $poll?->isWithinVotingWindow() ?? false;
        $hasVoted = $poll
            ? PollVote::query()->where('poll_id', $poll->id)->where('user_id', $user->id)->exists()
            : false;
        $community = $this->communityMetrics($team);

        $contributionCount = $myReviews + $myReviewComments + $myFanfictionComments + $myFanfictions;

        return [
            'openTodos' => $openTodos,
            'availableBaxx' => $availableBaxx,
            'walletWarning' => $walletWarning,
            'pendingVerification' => $pendingVerification,
            'romantauschMatches' => $romantauschMatches,
            'romantauschOffers' => $romantauschOffers,
            'myReviews' => $myReviews,
            'myReviewComments' => $myReviewComments,
            'myFanfictionComments' => $myFanfictionComments,
            'myFanfictions' => $myFanfictions,
            'fanfictionCount' => $fanfictionCount,
            'topUsers' => $topUsers,
            'personalRank' => $personalRank,
            'metricGroups' => [
                [
                    'key' => 'important',
                    'title' => 'Jetzt wichtig',
                    'description' => 'Deine nächsten Schritte und laufenden Community-Aktionen.',
                    'metrics' => [
                        $this->metric(
                            'open-challenges',
                            'Offene Challenges',
                            $openTodos,
                            $openTodos === 0 ? 'Keine Challenge wartet auf dich.' : trans_choice(':count Challenge ist in Arbeit.|:count Challenges sind in Arbeit.', $openTodos, ['count' => $openTodos]),
                            route('todos.index'),
                            'o-bolt',
                            $openTodos > 0 ? 'attention' : 'neutral'
                        ),
                        $this->metric(
                            'swap-matches',
                            'Tausch-Matches',
                            $romantauschMatches,
                            trans_choice(':count aktives Angebot|:count aktive Angebote', $romantauschOffers, ['count' => $romantauschOffers]),
                            route('romantausch.index'),
                            'o-arrows-right-left',
                            $romantauschMatches > 0 ? 'attention' : 'neutral'
                        ),
                        $this->metric(
                            'current-event',
                            'Aktuelle Veranstaltung',
                            $event?->datum_von?->format('d.m.Y') ?? 'Keine geplant',
                            $event
                                ? ($eventRegistration ? 'Du bist angemeldet · '.$event->titel : $event->titel)
                                : 'Sobald ein Termin feststeht, erscheint er hier.',
                            $event ? route('veranstaltungen.show', $event) : null,
                            'o-calendar-days',
                            $event && ! $eventRegistration && $event->isRegistrationOpen() ? 'attention' : 'neutral'
                        ),
                        $this->metric(
                            'active-poll',
                            'Aktive Umfrage',
                            ! $poll || ! $pollIsOpen ? 'Keine' : ($hasVoted ? 'Abgestimmt' : 'Offen'),
                            $poll && $pollIsOpen
                                ? ($poll->menu_label ?: $poll->question)
                                : 'Derzeit läuft keine Abstimmung.',
                            $poll && $pollIsOpen ? route('umfrage.aktuell') : null,
                            'o-chart-bar',
                            $poll && $pollIsOpen && ! $hasVoted ? 'attention' : 'neutral'
                        ),
                    ],
                ],
                [
                    'key' => 'progress',
                    'title' => 'Mein Fortschritt',
                    'description' => 'Baxx, Rang und deine Beiträge kompakt zusammengefasst.',
                    'metrics' => [
                        $this->metric(
                            'available-baxx',
                            'Verfügbare Baxx',
                            $walletWarning ? 'Prüfung nötig' : "{$availableBaxx} Baxx verfügbar",
                            $walletWarning ?? 'Für Belohnungen und Freischaltungen.',
                            route('rewards.index'),
                            'o-sparkles',
                            $walletWarning ? 'warning' : 'neutral'
                        ),
                        $this->metric(
                            'personal-rank',
                            'Dein Baxx-Rang',
                            $personalRank ? "Platz {$personalRank}" : 'Noch ohne Rang',
                            'Im aktuellen Community-Ranking.',
                            route('rewards.index'),
                            'o-trophy'
                        ),
                        $this->metric(
                            'next-reward',
                            'Nächste Belohnung',
                            $nextReward
                                ? ($nextReward->cost_baxx <= $availableBaxx ? 'Jetzt verfügbar' : ($nextReward->cost_baxx - $availableBaxx).' Baxx fehlen')
                                : 'Alles entdeckt',
                            $nextReward?->title ?? 'Keine weitere aktive Belohnung offen.',
                            route('rewards.index'),
                            'o-gift'
                        ),
                        $this->metric(
                            'own-contributions',
                            'Eigene Beiträge',
                            $contributionCount,
                            "{$myReviews} Rezensionen · ".($myReviewComments + $myFanfictionComments)." Kommentare · {$myFanfictions} Fanfiction",
                            route('reviews.index'),
                            'o-pencil-square'
                        ),
                    ],
                ],
                [
                    'key' => 'community',
                    'title' => 'Community',
                    'description' => 'Was in den letzten Tagen im Verein veröffentlicht wurde.',
                    'metrics' => [
                        $this->metric(
                            'recent-activities',
                            'Aktivitäten · 7 Tage',
                            $community['activities'],
                            'Alle protokollierten Community-Aktionen.',
                            '#dashboard-activities',
                            'o-signal'
                        ),
                        $this->metric(
                            'recent-reviews',
                            'Neue Rezensionen · 30 Tage',
                            $community['reviews'],
                            'Neu veröffentlichte Rezensionen.',
                            route('reviews.index'),
                            'o-book-open'
                        ),
                        $this->metric(
                            'recent-comments',
                            'Neue Kommentare · 30 Tage',
                            $community['comments'],
                            "{$community['reviewComments']} zu Rezensionen · {$community['fanfictionComments']} zu Fanfiction",
                            route('reviews.index'),
                            'o-chat-bubble-left-right'
                        ),
                        $this->metric(
                            'recent-fanfiction',
                            'Neue Fanfiction · 30 Tage',
                            $community['fanfictions'],
                            'Neu veröffentlichte Geschichten.',
                            route('fanfiction.index'),
                            'o-document-text'
                        ),
                    ],
                ],
            ],
            'tasks' => $this->tasks(
                $openTodos,
                $romantauschMatches,
                $isGovernance,
                $applicantCount,
                $pendingVerification,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metric(
        string $key,
        string $title,
        int|string $value,
        string $description,
        ?string $href,
        string $icon,
        string $tone = 'neutral',
    ): array {
        return compact('key', 'title', 'value', 'description', 'href', 'icon', 'tone');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tasks(
        int $openTodos,
        int $matches,
        bool $isGovernance,
        int $applicantCount,
        int $pendingVerification,
    ): array {
        $tasks = [];

        if ($openTodos > 0) {
            $tasks[] = [
                'key' => 'challenges',
                'title' => trans_choice(':count offene Challenge|:count offene Challenges', $openTodos, ['count' => $openTodos]),
                'description' => 'Setze deine angenommenen Challenges fort.',
                'href' => route('todos.index'),
                'icon' => 'o-bolt',
                'count' => $openTodos,
            ];
        }

        if ($matches > 0) {
            $tasks[] = [
                'key' => 'matches',
                'title' => trans_choice(':count neues Tausch-Match|:count neue Tausch-Matches', $matches, ['count' => $matches]),
                'description' => 'Prüfe die Treffer in deiner Tauschbörse.',
                'href' => route('romantausch.index'),
                'icon' => 'o-arrows-right-left',
                'count' => $matches,
            ];
        }

        if ($isGovernance) {
            $tasks[] = [
                'key' => 'applicants',
                'title' => trans_choice(':count Mitgliedsantrag|:count Mitgliedsanträge', $applicantCount, ['count' => $applicantCount]),
                'description' => $applicantCount > 0 ? 'Neue Anträge warten auf Prüfung.' : 'Keine neuen Anträge offen.',
                'href' => $applicantCount > 0 ? '#dashboard-applicants' : null,
                'icon' => 'o-user-plus',
                'count' => $applicantCount,
            ];
            $tasks[] = [
                'key' => 'verification',
                'title' => trans_choice(':count Verifizierung|:count Verifizierungen', $pendingVerification, ['count' => $pendingVerification]),
                'description' => $pendingVerification > 0 ? 'Abgeschlossene Challenges warten auf Freigabe.' : 'Keine Freigaben ausstehend.',
                'href' => route('todos.index', ['filter' => 'pending']),
                'icon' => 'o-shield-check',
                'count' => $pendingVerification,
            ];
        }

        return $tasks;
    }

    /**
     * @return array<int, array{id: int, name: string, profile_photo_url: string, points: int}>
     */
    private function topUsers(Team $team, mixed $cacheFor): array
    {
        return Cache::remember("top_users_{$team->id}", $cacheFor, function () use ($team): array {
            $pointTotals = UserPoint::query()
                ->where('team_id', $team->id)
                ->select('user_id')
                ->selectRaw('SUM(points) as total_points')
                ->groupBy('user_id');

            return User::query()
                ->joinSub($pointTotals, 'dashboard_point_totals', function ($join): void {
                    $join->on('users.id', '=', 'dashboard_point_totals.user_id');
                })
                ->orderByDesc('dashboard_point_totals.total_points')
                ->orderBy('users.id')
                ->limit(3)
                ->get(['users.*', 'dashboard_point_totals.total_points'])
                ->map(fn (User $rankedUser): array => [
                    'id' => $rankedUser->id,
                    'name' => $rankedUser->nicknameOrName(),
                    'profile_photo_url' => $rankedUser->profile_photo_url,
                    'points' => (int) $rankedUser->getAttribute('total_points'),
                ])
                ->values()
                ->all();
        });
    }

    private function personalRank(User $user, Team $team): ?int
    {
        $userTotal = (int) UserPoint::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->sum('points');

        if ($userTotal === 0 && ! UserPoint::query()->where('team_id', $team->id)->where('user_id', $user->id)->exists()) {
            return null;
        }

        $pointTotals = UserPoint::query()
            ->where('team_id', $team->id)
            ->select('user_id')
            ->selectRaw('SUM(points) as total_points')
            ->groupBy('user_id');

        $ahead = DB::query()
            ->fromSub($pointTotals, 'dashboard_rank_totals')
            ->where(function ($query) use ($user, $userTotal): void {
                $query->where('total_points', '>', $userTotal)
                    ->orWhere(function ($tieQuery) use ($user, $userTotal): void {
                        $tieQuery->where('total_points', $userTotal)
                            ->where('user_id', '<', $user->id);
                    });
            })
            ->count();

        return $ahead + 1;
    }

    private function nextReward(User $user): ?Reward
    {
        $purchasedRewardIds = RewardPurchase::query()
            ->where('user_id', $user->id)
            ->active()
            ->select('reward_id');

        return Reward::query()
            ->active()
            ->whereNotIn('id', $purchasedRewardIds)
            ->orderBy('cost_baxx')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{activities: int, reviews: int, reviewComments: int, fanfictionComments: int, comments: int, fanfictions: int}
     */
    private function communityMetrics(Team $team): array
    {
        $activitySince = now()->subDays(self::RECENT_ACTIVITY_DAYS);
        $contentSince = now()->subDays(self::RECENT_CONTENT_DAYS);
        $reviewComments = ReviewComment::query()
            ->where('created_at', '>=', $contentSince)
            ->whereHas('review', fn (Builder $query) => $query->where('team_id', $team->id))
            ->count();
        $fanfictionComments = FanfictionComment::query()
            ->where('created_at', '>=', $contentSince)
            ->whereHas('fanfiction', fn (Builder $query) => $query->where('team_id', $team->id))
            ->count();

        return [
            'activities' => Activity::query()->where('created_at', '>=', $activitySince)->count(),
            'reviews' => Review::query()
                ->where('team_id', $team->id)
                ->where('created_at', '>=', $contentSince)
                ->count(),
            'reviewComments' => $reviewComments,
            'fanfictionComments' => $fanfictionComments,
            'comments' => $reviewComments + $fanfictionComments,
            'fanfictions' => Fanfiction::query()
                ->where('team_id', $team->id)
                ->published()
                ->where('published_at', '>=', $contentSince)
                ->count(),
        ];
    }
}
