<?php

namespace Tests\Feature;

use App\Enums\PollStatus;
use App\Enums\PollVisibility;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\Book;
use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\FantreffenAnmeldung;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\Veranstaltung;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_service_returns_three_stable_groups_with_four_metrics_each(): void
    {
        [$member, $team] = $this->member();

        $dashboard = $this->build($member, $team);

        $this->assertSame(['important', 'progress', 'community'], array_column($dashboard['metricGroups'], 'key'));
        $this->assertSame([4, 4, 4], array_map(
            fn (array $group): int => count($group['metrics']),
            $dashboard['metricGroups'],
        ));
        $this->assertSame(0, $this->metric($dashboard, 'open-challenges')['value']);
        $this->assertSame('neutral', $this->metric($dashboard, 'open-challenges')['tone']);
    }

    public function test_personal_rank_is_deterministic_and_next_reward_excludes_purchases(): void
    {
        $team = Team::membersTeam();
        $tiedBefore = $this->attachMember($team);
        $ahead = $this->attachMember($team);
        $member = $this->attachMember($team);

        UserPoint::create(['user_id' => $tiedBefore->id, 'team_id' => $team->id, 'points' => 10]);
        UserPoint::create(['user_id' => $ahead->id, 'team_id' => $team->id, 'points' => 20]);
        UserPoint::create(['user_id' => $member->id, 'team_id' => $team->id, 'points' => 10]);

        Reward::query()->update(['is_active' => false]);
        $purchasedReward = Reward::factory()->create(['cost_baxx' => 2, 'sort_order' => 0]);
        $nextReward = Reward::factory()->create(['title' => 'Das nächste Ziel', 'cost_baxx' => 12, 'sort_order' => 1]);
        RewardPurchase::factory()->create([
            'user_id' => $member->id,
            'reward_id' => $purchasedReward->id,
            'wallet_team_id' => $team->id,
            'cost_baxx' => 2,
        ]);

        $dashboard = $this->build($member, $team);

        $this->assertSame(3, $dashboard['personalRank']);
        $this->assertSame('Platz 3', $this->metric($dashboard, 'personal-rank')['value']);
        $this->assertSame('4 Baxx fehlen', $this->metric($dashboard, 'next-reward')['value']);
        $this->assertSame($nextReward->title, $this->metric($dashboard, 'next-reward')['description']);
    }

    public function test_own_contributions_include_reviews_both_comment_types_and_published_fanfiction(): void
    {
        [$member, $team] = $this->member();
        $book = Book::create(['roman_number' => 9001, 'title' => 'Beitragstest', 'author' => 'Test']);
        $review = Review::create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'book_id' => $book->id,
            'title' => 'Meine Rezension',
            'content' => 'Inhalt',
        ]);
        ReviewComment::create(['review_id' => $review->id, 'user_id' => $member->id, 'content' => 'Kommentar']);
        $fanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'created_by' => $member->id,
        ]);
        FanfictionComment::factory()->create([
            'fanfiction_id' => $fanfiction->id,
            'user_id' => $member->id,
        ]);

        $dashboard = $this->build($member, $team);
        $contributions = $this->metric($dashboard, 'own-contributions');

        $this->assertSame(4, $contributions['value']);
        $this->assertSame(1, $dashboard['myReviewComments']);
        $this->assertSame(1, $dashboard['myFanfictionComments']);
        $this->assertSame(1, $dashboard['myFanfictions']);
        $this->assertStringContainsString('2 Kommentare', $contributions['description']);
    }

    public function test_community_metrics_respect_seven_and_thirty_day_windows(): void
    {
        $this->travelTo(now()->startOfMinute());
        [$member, $team] = $this->member();
        $book = Book::create(['roman_number' => 9002, 'title' => 'Zeitfenster', 'author' => 'Test']);

        $this->timestamp($this->activity($member), now()->subDays(6));
        $this->timestamp($this->activity($member), now()->subDays(8));

        $recentReview = Review::create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'book_id' => $book->id,
            'title' => 'Neu',
            'content' => 'Neu',
        ]);
        $this->timestamp($recentReview, now()->subDays(29));
        $oldReview = Review::create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'book_id' => $book->id,
            'title' => 'Alt',
            'content' => 'Alt',
        ]);
        $this->timestamp($oldReview, now()->subDays(31));

        $recentReviewComment = ReviewComment::create([
            'review_id' => $recentReview->id,
            'user_id' => $member->id,
            'content' => 'Neu',
        ]);
        $this->timestamp($recentReviewComment, now()->subDays(2));
        $oldReviewComment = ReviewComment::create([
            'review_id' => $recentReview->id,
            'user_id' => $member->id,
            'content' => 'Alt',
        ]);
        $this->timestamp($oldReviewComment, now()->subDays(31));

        $recentFanfiction = Fanfiction::factory()->published()->create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'created_by' => $member->id,
            'published_at' => now()->subDays(4),
        ]);
        Fanfiction::factory()->published()->create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'created_by' => $member->id,
            'published_at' => now()->subDays(31),
        ]);
        $fanfictionComment = FanfictionComment::factory()->create([
            'fanfiction_id' => $recentFanfiction->id,
            'user_id' => $member->id,
        ]);
        $this->timestamp($fanfictionComment, now()->subDay());

        $dashboard = $this->build($member, $team);

        $this->assertSame(1, $this->metric($dashboard, 'recent-activities')['value']);
        $this->assertSame(1, $this->metric($dashboard, 'recent-reviews')['value']);
        $this->assertSame(2, $this->metric($dashboard, 'recent-comments')['value']);
        $this->assertSame(1, $this->metric($dashboard, 'recent-fanfiction')['value']);
    }

    public function test_event_registration_and_poll_vote_are_reflected_in_important_metrics(): void
    {
        [$member, $team] = $this->member();
        $event = Veranstaltung::featuredPublic();
        $this->assertNotNull($event);
        FantreffenAnmeldung::create([
            'veranstaltung_id' => $event->id,
            'user_id' => $member->id,
            'vorname' => 'Alex',
            'nachname' => 'Mitglied',
            'email' => $member->email,
            'payment_status' => 'free',
            'payment_amount' => 0,
            'tshirt_bestellt' => false,
            'ist_mitglied' => true,
            'zahlungseingang' => false,
        ]);
        $poll = Poll::create([
            'question' => 'Welche Idee kommt als Nächstes?',
            'menu_label' => 'Nächste Idee',
            'visibility' => PollVisibility::Internal,
            'status' => PollStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'activated_at' => now(),
            'created_by_user_id' => $member->id,
        ]);
        $option = PollOption::create(['poll_id' => $poll->id, 'label' => 'Option A', 'sort_order' => 0]);

        $dashboard = $this->build($member, $team);
        $this->assertStringStartsWith('Du bist angemeldet', $this->metric($dashboard, 'current-event')['description']);
        $this->assertSame('Offen', $this->metric($dashboard, 'active-poll')['value']);

        PollVote::create([
            'poll_id' => $poll->id,
            'poll_option_id' => $option->id,
            'user_id' => $member->id,
            'voter_type' => 'member',
        ]);

        $dashboard = $this->build($member, $team);
        $this->assertSame('Abgestimmt', $this->metric($dashboard, 'active-poll')['value']);
    }

    public function test_governance_zero_states_stay_visible_and_neutral(): void
    {
        [$admin, $team] = $this->member(Role::Admin);

        $tasks = collect($this->build($admin, $team, Role::Admin)['tasks']);

        $this->assertSame(0, $tasks->firstWhere('key', 'applicants')['count']);
        $this->assertSame(0, $tasks->firstWhere('key', 'verification')['count']);
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function member(Role $role = Role::Mitglied): array
    {
        $team = Team::membersTeam();

        return [$this->attachMember($team, $role), $team];
    }

    private function attachMember(Team $team, Role $role = Role::Mitglied): User
    {
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => $role->value]);

        return $member;
    }

    /** @return array<string, mixed> */
    private function build(User $user, Team $team, Role $role = Role::Mitglied): array
    {
        return app(DashboardMetricsService::class)->build($user, $team, $role, 0);
    }

    /** @return array<string, mixed> */
    private function metric(array $dashboard, string $key): array
    {
        return collect($dashboard['metricGroups'])
            ->flatMap(fn (array $group): array => $group['metrics'])
            ->firstWhere('key', $key);
    }

    private function activity(User $user): Activity
    {
        return Activity::create([
            'user_id' => $user->id,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'action' => 'member_approved',
        ]);
    }

    private function timestamp(Model $model, mixed $timestamp): void
    {
        $model->forceFill(['created_at' => $timestamp, 'updated_at' => $timestamp])->saveQuietly();
    }
}
