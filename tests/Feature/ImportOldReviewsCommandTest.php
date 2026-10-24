<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportOldReviewsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath.'/app/private');
        File::ensureDirectoryExists($this->testStoragePath.'/framework/views');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);
        parent::tearDown();
    }

    public function test_old_reviews_are_imported_from_csv(): void
    {
        $team = Team::membersTeam();
        $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Autor1']);
        $user = User::factory()->create(['email' => 'user@example.com', 'current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        // create nine existing reviews so that the import triggers the 10th point
        for ($i = 1; $i <= 9; $i++) {
            Review::create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'book_id' => $book->id,
                'title' => 'Review'.$i,
                'content' => 'Dummy',
            ]);
        }

        $csv = "topic;author;timestamp;content\n";
        $csv .= "001 - Roman1;user@example.com;13. März 2025, 12:34;Letzte\n";
        File::put($this->testStoragePath.'/app/private/reviews.csv', $csv);

        $this->artisan('reviews:import-old', ['--path' => 'private/reviews.csv'])->assertExitCode(0);

        $this->assertSame(10, Review::where('team_id', $team->id)->where('user_id', $user->id)->count());
        $this->assertDatabaseCount('user_points', 1);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => $team->id,
            'points' => 1,
        ]);
    }

    public function test_fresh_import_preserves_reviews_and_unrelated_baxx_when_provenance_is_unknown(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $review = Review::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);
        $point = UserPoint::create(['team_id' => $team->id, 'user_id' => $user->id, 'points' => 29]);
        File::put($this->testStoragePath.'/app/private/reviews.csv', "topic;author;timestamp;content\n");

        $this->artisan('reviews:import-old', ['--path' => 'private/reviews.csv', '--fresh' => true])->assertExitCode(1);

        $this->assertModelExists($review);
        $this->assertModelExists($point);
        $this->assertSame(29, (int) UserPoint::sum('points'));
    }

    public function test_fresh_import_without_bookings_only_resets_members_team_reviews(): void
    {
        $review = Review::factory()->create(['team_id' => Team::membersTeam()->id]);
        $other = Review::factory()->create(['team_id' => Team::factory()->create()->id]);
        File::put($this->testStoragePath.'/app/private/reviews.csv', "topic;author;timestamp;content\n");

        $this->artisan('reviews:import-old', ['--path' => 'private/reviews.csv', '--fresh' => true])->assertExitCode(0);

        $this->assertSoftDeleted($review);
        $this->assertModelExists($other);
    }
}
