<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FanfictionStatus;
use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Models\Activity;
use App\Models\Book;
use App\Models\Fanfiction;
use App\Models\Mission;
use App\Models\Review;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\Todo;
use App\Models\UserPoint;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(BaxxMariaDbConcurrencyTestFixtures::class);

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Benötigt echte MariaDB-Verbindungen.');
    }
    if (DB::connection()->getDatabaseName() !== 'omxfc_maddraxikon_test') {
        throw new RuntimeException('Unsichere Testdatenbank verweigert.');
    }
});

afterEach(function (): void {
    try {
        if ($this->committed) {
            $this->artisan('migrate:fresh')->assertExitCode(0);
        }
    } finally {
    }
});

test('parallel acceptance has one owner and one activity', function (): void {
    $first = $this->createUserWithRole(Role::Mitglied);
    $second = $this->createUserWithRole(Role::Mitglied);
    $todo = $this->todo($first->id, TodoStatus::Open);
    $result = $this->race([['assign', $first->id, $todo->id], ['assign', $second->id, $todo->id]]);
    sort($result);
    expect($result)->toBe(['ok', 'rejected'])
        ->and([$first->id, $second->id])->toContain($todo->fresh()->assigned_to)
        ->and(Activity::where('subject_type', Todo::class)->where('action', 'accepted')->count())->toBe(1);
});

test('parallel verification books once', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $todo = $this->todo($admin->id, TodoStatus::Completed);
    $this->race([['verify', $admin->id, $todo->id], ['verify', $admin->id, $todo->id]]);
    expect((int) UserPoint::sum('points'))->toBe(7)
        ->and(UserPoint::where('todo_id', $todo->id)->count())->toBe(1);
});

test('verification racing deletion leaves no orphan booking', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $todo = $this->todo($admin->id, TodoStatus::Completed);
    $this->race([['verify', $admin->id, $todo->id], ['delete', $admin->id, $todo->id]]);
    $this->assertDatabaseCount('user_points', 0);
    $this->assertModelMissing($todo);
});

test('parallel reviews cross the tenth review only once', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $books = Book::factory()->count(11)->create();
    foreach ($books->take(9) as $book) {
        Review::create(['user_id' => $member->id, 'team_id' => $member->current_team_id, 'book_id' => $book->id, 'title' => 'Alt', 'content' => 'Inhalt']);
    }
    expect($this->race([['review', $member->id, $books[9]->id], ['review', $member->id, $books[10]->id]]))->toBe(['ok', 'ok']);
    $this->assertDatabaseCount('reviews', 11);
    expect((int) UserPoint::sum('points'))->toBe(1);
});

test('parallel mission completion books once', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    Mission::create(['user_id' => $member->id, 'name' => 'Parallel', 'origin' => 'Waashton', 'destination' => 'Salem', 'travel_duration' => 1, 'mission_duration' => 1, 'started_at' => now()->subMinute(), 'arrival_at' => now()->subSeconds(59), 'mission_ends_at' => now()->subSeconds(58), 'completed' => false]);
    $this->race([['mission', $member->id, 0], ['mission', $member->id, 0]]);
    expect((int) UserPoint::sum('points'))->toBe(5)
        ->and(UserPoint::count())->toBe(1);
});

test('parallel publication and special offer activation are single use', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $story = Fanfiction::factory()->create(['user_id' => $admin->id, 'created_by' => $admin->id, 'team_id' => $admin->current_team_id, 'status' => FanfictionStatus::Draft, 'published_at' => null]);
    $this->race([['publish', $admin->id, $story->id], ['publish', $admin->id, $story->id], ['offer', $admin->id, 0], ['offer', $admin->id, 0]]);
    expect(UserPoint::count())->toBe(1)
        ->and(Activity::where('subject_type', Fanfiction::class)->where('action', 'published')->count())->toBe(1)
        ->and(ReviewBaxxSpecialOffer::currentlyActive()->count())->toBe(1);
});
