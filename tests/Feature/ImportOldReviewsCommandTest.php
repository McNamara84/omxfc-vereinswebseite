<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(ImportOldReviewsCommandTestFixtures::class);

beforeEach(function (): void {
    $this->testStoragePath = base_path('storage/testing');
    $this->app->useStoragePath($this->testStoragePath);
    File::ensureDirectoryExists($this->testStoragePath.'/app/private');
    File::ensureDirectoryExists($this->testStoragePath.'/framework/views');
});

afterEach(function (): void {
    File::deleteDirectory($this->testStoragePath);
});

test('old reviews are imported from csv', function (): void {
    $team = Team::membersTeam();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Autor1']);
    $user = User::factory()->create(['email' => 'user@example.com', 'current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    // create nine existing reviews so that the import triggers the 10th point
    for ($i = 1; $i <= 9; $i++) {
        Review::create(['team_id' => $team->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'Review'.$i, 'content' => 'Dummy']);
    }
    $csv = "topic;author;timestamp;content\n";
    $csv .= "001 - Roman1;user@example.com;13. März 2025, 12:34;Letzte\n";
    File::put($this->testStoragePath.'/app/private/reviews.csv', $csv);
    $this->artisan('reviews:import-old', ['--path' => 'private/reviews.csv'])->assertExitCode(0);
    expect(Review::where('team_id', $team->id)->where('user_id', $user->id)->count())->toBe(10);
    $this->assertDatabaseCount('user_points', 1);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $team->id, 'points' => 1]);
});

test('legacy csv import preserves quotes backslashes and multiline content', function (): void {
    $team = Team::membersTeam();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Autor1']);
    $user = User::factory()->create(['email' => 'legacy@example.com', 'current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    $content = "Grüße; \"zitiert\"\nPfad C:\\Verein\\Datei und \\\"Altformat\\\"";
    // Literal fixture for the legacy dialect: doubled enclosure quotes and
    // backslash-escaped quotes must retain their historical interpretation.
    $csv = "topic;author;timestamp;content\n";
    $csv .= '001 - Roman1;legacy@example.com;13. März 2025, 12:34;"Grüße; ""zitiert""'."\n".'Pfad C:\Verein\Datei und \"Altformat\""'."\n";
    File::put($this->testStoragePath.'/app/private/reviews.csv', $csv);
    $this->artisan('reviews:import-old', ['--path' => 'private/reviews.csv'])->assertExitCode(0);
    $review = Review::where('book_id', $book->id)->where('user_id', $user->id)->sole();
    expect($review->content)->toBe($content);
});

test('fresh import preserves reviews and unrelated baxx when provenance is unknown', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $review = Review::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);
    $point = UserPoint::create(['team_id' => $team->id, 'user_id' => $user->id, 'points' => 29]);
    File::put($this->testStoragePath.'/app/private/reviews.csv', "topic;author;timestamp;content\n");
    $this->artisan('reviews:import-old', ['--path' => 'private/reviews.csv', '--fresh' => true])->assertExitCode(1);
    $this->assertModelExists($review);
    $this->assertModelExists($point);
    expect((int) UserPoint::sum('points'))->toBe(29);
});

test('fresh import without bookings only resets members team reviews', function (): void {
    $review = Review::factory()->create(['team_id' => Team::membersTeam()->id]);
    $other = Review::factory()->create(['team_id' => Team::factory()->create()->id]);
    File::put($this->testStoragePath.'/app/private/reviews.csv', "topic;author;timestamp;content\n");
    $this->artisan('reviews:import-old', ['--path' => 'private/reviews.csv', '--fresh' => true])->assertExitCode(0);
    $this->assertSoftDeleted($review);
    $this->assertModelExists($other);
});
