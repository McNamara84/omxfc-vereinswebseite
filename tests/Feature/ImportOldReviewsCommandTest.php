<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Illuminate\Support\Facades\File;
use App\Models\Book;
use App\Models\Review;

class ImportOldReviewsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath . '/app/private');
        File::ensureDirectoryExists($this->testStoragePath . '/framework/views');
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
        $team->users()->attach($user, ['role' => 'Mitglied']);

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
        File::put($this->testStoragePath . '/app/private/reviews.csv', $csv);

        $this->artisan('reviews:import-old', ['--path' => 'private/reviews.csv'])->assertExitCode(0);

        $this->assertSame(10, Review::where('team_id', $team->id)->where('user_id', $user->id)->count());
        $this->assertDatabaseCount('user_points', 1);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => $team->id,
            'points' => 1,
        ]);
    }
}
