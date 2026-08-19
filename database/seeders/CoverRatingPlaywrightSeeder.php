<?php

namespace Database\Seeders;

use App\Enums\BookType;
use App\Models\Book;
use App\Models\BookCover;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CoverRatingPlaywrightSeeder extends Seeder
{
    private const BROWSERS = ['chromium', 'firefox', 'webkit'];

    private const FLOWS = ['mobile', 'desktop'];

    private const RETRIES = [0, 1, 2];

    public function run(): void
    {
        $member = User::query()->where('email', 'playwright-member@example.com')->first();

        if (! $member) {
            throw new RuntimeException('Run TodoPlaywrightSeeder before CoverRatingPlaywrightSeeder.');
        }

        $membersTeam = $member->currentTeam;

        if (! $membersTeam) {
            throw new RuntimeException('The Playwright member team is missing.');
        }

        foreach (self::BROWSERS as $browser) {
            foreach (self::FLOWS as $flow) {
                foreach (self::RETRIES as $retry) {
                    $email = "playwright-cover-{$flow}-{$browser}-retry-{$retry}@example.com";
                    $testMember = User::factory()->create([
                        'name' => "Playwright Cover {$flow} {$browser} {$retry}",
                        'email' => $email,
                        'current_team_id' => $membersTeam->id,
                    ]);

                    $membersTeam->users()->syncWithoutDetaching([
                        $testMember->id => ['role' => 'Mitglied'],
                    ]);
                }
            }
        }

        $image = file_get_contents(public_path('images/brina-rating.webp'));

        if (! is_string($image)) {
            throw new RuntimeException('The Brina test image is missing.');
        }

        foreach (BookType::cases() as $index => $type) {
            $number = $type === BookType::MaddraxDieDunkleZukunftDerErde
                || $type === BookType::MissionMars
                ? 1
                : $index + 1;
            $book = Book::query()->firstOrCreate(
                ['type' => $type, 'roman_number' => $number],
                ['title' => $type->label().' Testcover', 'author' => 'Playwright'],
            );
            $smallPath = "cover-ratings/playwright/{$book->id}-small.webp";
            $largePath = "cover-ratings/playwright/{$book->id}-large.webp";
            Storage::disk('private')->put($smallPath, $image);
            Storage::disk('private')->put($largePath, $image);

            BookCover::query()->updateOrCreate(
                ['book_id' => $book->id],
                [
                    'status' => 'ready',
                    'source_file_title' => 'Datei:Playwright-'.$book->id.'.webp',
                    'source_url' => 'https://wiki.example.test/playwright-'.$book->id.'.webp',
                    'source_sha1' => sha1($image),
                    'source_description_url' => 'https://wiki.example.test/wiki/Playwright',
                    'small_path' => $smallPath,
                    'large_path' => $largePath,
                    'width' => 128,
                    'height' => 128,
                    'mime_type' => 'image/webp',
                    'last_synced_at' => now(),
                    'last_error' => null,
                ],
            );
        }
    }
}
