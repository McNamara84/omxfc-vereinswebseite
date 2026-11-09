<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PollStatus;
use App\Enums\PollVisibility;
use App\Enums\Role;
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
use App\Models\UserPoint;
use App\Models\Veranstaltung;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DashboardMetricsServiceTestFixtures::class);

beforeEach(function (): void {
    Cache::flush();
});

test('service returns three stable groups with four metrics each', function (): void {
    [$member, $team] = $this->member();
    $dashboard = $this->build($member, $team);
    expect(array_column($dashboard['metricGroups'], 'key'))->toBe(['important', 'progress', 'community'])
        ->and(array_map(fn (array $group): int => count($group['metrics']), $dashboard['metricGroups']))->toBe([4, 4, 4])
        ->and($this->metric($dashboard, 'open-challenges')['value'])->toBe(0)
        ->and($this->metric($dashboard, 'open-challenges')['tone'])->toBe('neutral');
});

test('personal rank is deterministic and next reward excludes purchases', function (): void {
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
    RewardPurchase::factory()->create(['user_id' => $member->id, 'reward_id' => $purchasedReward->id, 'wallet_team_id' => $team->id, 'cost_baxx' => 2]);
    $dashboard = $this->build($member, $team);
    expect($dashboard['personalRank'])->toBe(3)
        ->and($this->metric($dashboard, 'personal-rank')['value'])->toBe('Platz 3')
        ->and($this->metric($dashboard, 'next-reward')['value'])->toBe('4 Baxx fehlen')
        ->and($this->metric($dashboard, 'next-reward')['description'])->toBe($nextReward->title);
});

test('own contributions include reviews both comment types and published fanfiction', function (): void {
    [$member, $team] = $this->member();
    $book = Book::create(['roman_number' => 9001, 'title' => 'Beitragstest', 'author' => 'Test']);
    $review = Review::create(['team_id' => $team->id, 'user_id' => $member->id, 'book_id' => $book->id, 'title' => 'Meine Rezension', 'content' => 'Inhalt']);
    ReviewComment::create(['review_id' => $review->id, 'user_id' => $member->id, 'content' => 'Kommentar']);
    $fanfiction = Fanfiction::factory()->published()->create(['team_id' => $team->id, 'user_id' => $member->id, 'created_by' => $member->id]);
    FanfictionComment::factory()->create(['fanfiction_id' => $fanfiction->id, 'user_id' => $member->id]);
    $dashboard = $this->build($member, $team);
    $contributions = $this->metric($dashboard, 'own-contributions');
    expect($contributions['value'])->toBe(4)
        ->and($dashboard['myReviewComments'])->toBe(1)
        ->and($dashboard['myFanfictionComments'])->toBe(1)
        ->and($dashboard['myFanfictions'])->toBe(1)
        ->and($contributions['description'])->toContain('2 Kommentare');
});

test('community metrics respect seven and thirty day windows', function (): void {
    $this->travelTo(now()->startOfMinute());
    [$member, $team] = $this->member();
    $book = Book::create(['roman_number' => 9002, 'title' => 'Zeitfenster', 'author' => 'Test']);
    $this->timestamp($this->activity($member), now()->subDays(6));
    $this->timestamp($this->activity($member), now()->subDays(8));
    $recentReview = Review::create(['team_id' => $team->id, 'user_id' => $member->id, 'book_id' => $book->id, 'title' => 'Neu', 'content' => 'Neu']);
    $this->timestamp($recentReview, now()->subDays(29));
    $oldReview = Review::create(['team_id' => $team->id, 'user_id' => $member->id, 'book_id' => $book->id, 'title' => 'Alt', 'content' => 'Alt']);
    $this->timestamp($oldReview, now()->subDays(31));
    $recentReviewComment = ReviewComment::create(['review_id' => $recentReview->id, 'user_id' => $member->id, 'content' => 'Neu']);
    $this->timestamp($recentReviewComment, now()->subDays(2));
    $oldReviewComment = ReviewComment::create(['review_id' => $recentReview->id, 'user_id' => $member->id, 'content' => 'Alt']);
    $this->timestamp($oldReviewComment, now()->subDays(31));
    $recentFanfiction = Fanfiction::factory()->published()->create(['team_id' => $team->id, 'user_id' => $member->id, 'created_by' => $member->id, 'published_at' => now()->subDays(4)]);
    Fanfiction::factory()->published()->create(['team_id' => $team->id, 'user_id' => $member->id, 'created_by' => $member->id, 'published_at' => now()->subDays(31)]);
    $fanfictionComment = FanfictionComment::factory()->create(['fanfiction_id' => $recentFanfiction->id, 'user_id' => $member->id]);
    $this->timestamp($fanfictionComment, now()->subDay());
    $dashboard = $this->build($member, $team);
    expect($this->metric($dashboard, 'recent-activities')['value'])->toBe(1)
        ->and($this->metric($dashboard, 'recent-reviews')['value'])->toBe(1)
        ->and($this->metric($dashboard, 'recent-comments')['value'])->toBe(2)
        ->and($this->metric($dashboard, 'recent-fanfiction')['value'])->toBe(1);
});

test('event registration and poll vote are reflected in important metrics', function (): void {
    [$member, $team] = $this->member();
    $event = Veranstaltung::featuredPublic();
    expect($event)->not->toBeNull();
    FantreffenAnmeldung::create(['veranstaltung_id' => $event->id, 'user_id' => $member->id, 'vorname' => 'Alex', 'nachname' => 'Mitglied', 'email' => $member->email, 'payment_status' => 'free', 'payment_amount' => 0, 'tshirt_bestellt' => false, 'ist_mitglied' => true, 'zahlungseingang' => false]);
    $poll = Poll::create(['question' => 'Welche Idee kommt als Nächstes?', 'menu_label' => 'Nächste Idee', 'visibility' => PollVisibility::Internal, 'status' => PollStatus::Active, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'activated_at' => now(), 'created_by_user_id' => $member->id]);
    $option = PollOption::create(['poll_id' => $poll->id, 'label' => 'Option A', 'sort_order' => 0]);
    $dashboard = $this->build($member, $team);
    expect($this->metric($dashboard, 'current-event')['description'])->toStartWith('Du bist angemeldet')
        ->and($this->metric($dashboard, 'active-poll')['value'])->toBe('Offen');
    PollVote::create(['poll_id' => $poll->id, 'poll_option_id' => $option->id, 'user_id' => $member->id, 'voter_type' => 'member']);
    $dashboard = $this->build($member, $team);
    expect($this->metric($dashboard, 'active-poll')['value'])->toBe('Abgestimmt');
});

test('governance zero states stay visible and neutral', function (): void {
    [$admin, $team] = $this->member(Role::Admin);
    $tasks = collect($this->build($admin, $team, Role::Admin)['tasks']);
    expect($tasks->firstWhere('key', 'applicants')['count'])->toBe(0)
        ->and($tasks->firstWhere('key', 'verification')['count'])->toBe(0);
});
