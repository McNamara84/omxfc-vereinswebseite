<?php

namespace App\Services\Dashboard;

use App\Models\Activity;
use App\Models\AdminMessage;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\FantreffenAnmeldung;
use App\Models\MaddraxikonAccountLink;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\RewardPurchase;
use App\Models\Todo;
use App\Models\User;
use App\Services\FanfictionAccessService;
use App\Services\RewardService;
use App\Support\PreviewText;
use Illuminate\Database\Eloquent\Collection;

class DashboardActivityPresenter
{
    /** @var array<class-string, string> */
    private const TYPE_LABELS = [
        Review::class => 'Rezension',
        Fanfiction::class => 'Fanfiction',
        ReviewComment::class => 'Kommentar',
        FanfictionComment::class => 'Kommentar',
        BookOffer::class => 'Tausch',
        BookRequest::class => 'Gesuch',
        BookSwap::class => 'Tausch',
        RewardPurchase::class => 'Belohnung',
        AdminMessage::class => 'Hinweis',
        FantreffenAnmeldung::class => 'Fantreffen',
        Todo::class => 'Challenge',
        User::class => 'Mitglied',
        MaddraxikonAccountLink::class => 'Maddraxikon',
    ];

    public function __construct(
        private readonly RewardService $rewardService,
        private readonly FanfictionAccessService $fanfictionAccessService,
    ) {}

    /**
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, Activity>
     */
    public function prepare(Collection $activities, User $viewer): Collection
    {
        $this->addFanfictionPreviews($activities, $viewer);
        $this->addPresentationMetadata($activities);

        return $this->groupMilestones($activities);
    }

    /**
     * @param  Collection<int, Activity>  $activities
     */
    private function addPresentationMetadata(Collection $activities): void
    {
        $activities->each(function (Activity $activity): void {
            $isRegistration = $activity->subject_type === FantreffenAnmeldung::class;
            $isSwapCompletion = $activity->subject_type === BookSwap::class
                && $activity->action === 'swap_completed';
            $label = self::TYPE_LABELS[$activity->subject_type] ?? 'Aktivität';

            if ($activity->subject_type === User::class && str_starts_with((string) $activity->action, 'baxx_milestone_reached_')) {
                $label = 'Meilenstein';
            } elseif ($activity->subject_type === User::class && str_starts_with((string) $activity->action, Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX)) {
                $label = 'Maddraxikon';
            }

            $activity->setAttribute('dashboard_date_key', $activity->created_at->toDateString());
            $activity->setAttribute('dashboard_date_label', $activity->created_at->isToday()
                ? 'Heute'
                : ($activity->created_at->isYesterday()
                    ? 'Gestern'
                    : $activity->created_at->translatedFormat('l, d. F Y')));
            $activity->setAttribute('dashboard_label', $label);
            $activity->setAttribute('dashboard_actor_name', $activity->user?->nicknameOrName());
            $activity->setAttribute('dashboard_is_registration', $isRegistration);
            $activity->setAttribute('dashboard_is_swap_completion', $isSwapCompletion);
            $activity->setAttribute('dashboard_show_profile_link', ! $isRegistration && ! $isSwapCompletion && $activity->user !== null);
            $activity->setAttribute(
                'dashboard_missing_subject_message',
                in_array($activity->subject_type, [ReviewComment::class, FanfictionComment::class], true)
                    ? 'Kommentar – Bezug nicht mehr verfügbar'
                    : 'Gelöschter Eintrag – nicht mehr verfügbar',
            );
        });
    }

    /**
     * @param  Collection<int, Activity>  $activities
     */
    private function addFanfictionPreviews(Collection $activities, User $viewer): void
    {
        if (! $activities->contains($this->isPublishedFanfiction(...))) {
            return;
        }

        $unlockedRewardIds = $this->rewardService->getUnlockedRewardIds($viewer);

        $activities->each(function (Activity $activity) use ($viewer, $unlockedRewardIds): void {
            if (! $this->isPublishedFanfiction($activity) || ! $activity->subject instanceof Fanfiction) {
                return;
            }

            $source = $this->fanfictionAccessService->hasUnlocked(
                $viewer,
                $activity->subject,
                $unlockedRewardIds,
            ) ? $activity->subject->content : $activity->subject->teaser;

            $activity->setAttribute('dashboard_fanfiction_preview', (string) PreviewText::make($source, 160));
        });
    }

    private function isPublishedFanfiction(Activity $activity): bool
    {
        return $activity->subject_type === Fanfiction::class && $activity->action === 'published';
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, Activity>
     */
    private function groupMilestones(Collection $activities): Collection
    {
        $groups = [];

        return $activities->filter(function (Activity $activity) use (&$groups): bool {
            if ($activity->subject_type !== User::class || ! str_starts_with((string) $activity->action, 'baxx_milestone_reached_')) {
                return true;
            }

            $key = $activity->created_at->toDateString().'|'.($activity->user_id ?? $activity->subject_id);
            $value = (int) str_replace('baxx_milestone_reached_', '', (string) $activity->action);

            if (! isset($groups[$key])) {
                $activity->setAttribute('dashboard_group_count', 1);
                $activity->setAttribute('dashboard_milestone_values', [$value]);
                $groups[$key] = $activity;

                return true;
            }

            $group = $groups[$key];
            $values = [...(array) $group->getAttribute('dashboard_milestone_values'), $value];
            $group->setAttribute('dashboard_group_count', count($values));
            $group->setAttribute('dashboard_milestone_values', $values);

            return false;
        })->values();
    }
}
