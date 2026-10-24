<?php

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
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\UserPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class BaxxMariaDbConcurrencyTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private bool $committed = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Benötigt echte MariaDB-Verbindungen.');
        }
        if (DB::connection()->getDatabaseName() !== 'omxfc_maddraxikon_test') {
            throw new RuntimeException('Unsichere Testdatenbank verweigert.');
        }
    }

    /** Start both independent connections before releasing their barrier. */
    private function race(array $commands): array
    {
        DB::connection()->commit();
        $this->committed = true;
        $workers = [];
        try {
            foreach ($commands as $command) {
                $process = proc_open([PHP_BINARY, base_path('tests/Support/MariaDbBaxxWorker.php'), ...array_map(strval(...), $command)],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                if (! is_resource($process)) {
                    throw new RuntimeException('Worker konnte nicht starten.');
                }
                stream_set_timeout($pipes[1], 30);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [$process, $pipes]) {
                $this->assertSame("ready\n", fgets($pipes[1]));
            }
            foreach ($workers as [$process, $pipes]) {
                fwrite($pipes[0], "RUN\n");
                fflush($pipes[0]);
            }
            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $line = fgets($pipes[1]);
                $this->assertNotFalse($line, stream_get_contents($pipes[2]));
                $results[] = json_decode($line, true, flags: JSON_THROW_ON_ERROR)['result'];
            }

            return $results;
        } finally {
            foreach ($workers as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                }
                proc_close($process);
            }
        }
    }

    private function todo(int $userId, TodoStatus $status): Todo
    {
        return Todo::create(['team_id' => Team::membersTeam()->id, 'created_by' => $userId, 'assigned_to' => $status === TodoStatus::Open ? null : $userId,
            'status' => $status, 'points' => 7, 'title' => 'Parallel', 'category_id' => TodoCategory::create(['name' => 'Tests', 'slug' => 'tests'])->id]);
    }

    public function test_parallel_acceptance_has_one_owner_and_one_activity(): void
    {
        $first = $this->createUserWithRole(Role::Mitglied);
        $second = $this->createUserWithRole(Role::Mitglied);
        $todo = $this->todo($first->id, TodoStatus::Open);
        $result = $this->race([['assign', $first->id, $todo->id], ['assign', $second->id, $todo->id]]);
        sort($result);
        $this->assertSame(['ok', 'rejected'], $result);
        $this->assertContains($todo->fresh()->assigned_to, [$first->id, $second->id]);
        $this->assertSame(1, Activity::where('subject_type', Todo::class)->where('action', 'accepted')->count());
    }

    public function test_parallel_verification_books_once(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $todo = $this->todo($admin->id, TodoStatus::Completed);
        $this->race([['verify', $admin->id, $todo->id], ['verify', $admin->id, $todo->id]]);
        $this->assertSame(7, (int) UserPoint::sum('points'));
        $this->assertSame(1, UserPoint::where('todo_id', $todo->id)->count());
    }

    public function test_verification_racing_deletion_leaves_no_orphan_booking(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $todo = $this->todo($admin->id, TodoStatus::Completed);
        $this->race([['verify', $admin->id, $todo->id], ['delete', $admin->id, $todo->id]]);
        $this->assertDatabaseCount('user_points', 0);
        $this->assertModelMissing($todo);
    }

    public function test_parallel_reviews_cross_the_tenth_review_only_once(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $books = Book::factory()->count(11)->create();
        foreach ($books->take(9) as $book) {
            Review::create(['user_id' => $member->id, 'team_id' => $member->current_team_id, 'book_id' => $book->id, 'title' => 'Alt', 'content' => 'Inhalt']);
        }
        $this->assertSame(['ok', 'ok'], $this->race([['review', $member->id, $books[9]->id], ['review', $member->id, $books[10]->id]]));
        $this->assertDatabaseCount('reviews', 11);
        $this->assertSame(1, (int) UserPoint::sum('points'));
    }

    public function test_parallel_mission_completion_books_once(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        Mission::create(['user_id' => $member->id, 'name' => 'Parallel', 'origin' => 'Waashton', 'destination' => 'Salem',
            'travel_duration' => 1, 'mission_duration' => 1, 'started_at' => now()->subMinute(), 'arrival_at' => now()->subSeconds(59),
            'mission_ends_at' => now()->subSeconds(58), 'completed' => false]);
        $this->race([['mission', $member->id, 0], ['mission', $member->id, 0]]);
        $this->assertSame(5, (int) UserPoint::sum('points'));
        $this->assertSame(1, UserPoint::count());
    }

    public function test_parallel_publication_and_special_offer_activation_are_single_use(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $story = Fanfiction::factory()->create(['user_id' => $admin->id, 'created_by' => $admin->id, 'team_id' => $admin->current_team_id,
            'status' => FanfictionStatus::Draft, 'published_at' => null]);
        $this->race([['publish', $admin->id, $story->id], ['publish', $admin->id, $story->id], ['offer', $admin->id, 0], ['offer', $admin->id, 0]]);
        $this->assertSame(1, UserPoint::count());
        $this->assertSame(1, Activity::where('subject_type', Fanfiction::class)->where('action', 'published')->count());
        $this->assertSame(1, ReviewBaxxSpecialOffer::currentlyActive()->count());
    }

    protected function tearDown(): void
    {
        try {
            if ($this->committed) {
                $this->artisan('migrate:fresh')->assertExitCode(0);
            }
        } finally {
            parent::tearDown();
        }
    }
}
