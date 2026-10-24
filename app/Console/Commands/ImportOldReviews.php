<?php

namespace App\Console\Commands;

use App\Actions\Reviews\RecordReview;
use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportOldReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reviews:import-old
                            {--path=private/maddrax_forum_posts.csv : Path to CSV file relative to storage/app}
                            {--fresh : Reset members-team reviews only when no ambiguous Baxx bookings exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import legacy reviews from forum CSV into reviews table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->option('path');
        $fullPath = storage_path("app/{$path}");

        if (! file_exists($fullPath)) {
            $this->error("CSV file not found at {$fullPath}");

            return 1;
        }

        // Historical bookings have no reliable import provenance: fail closed.
        if ($this->option('fresh')) {
            $teamId = Team::membersTeam()?->id;
            if (! $teamId || UserPoint::query()->where('team_id', $teamId)->exists()) {
                $this->error('Reset abgebrochen: Vorhandene Baxx sind nicht eindeutig dem Import zugeordnet. Es wurden keine Daten gelöscht.');

                return self::FAILURE;
            }
            Review::query()->where('team_id', $teamId)->delete();
            $this->info('Rezensionen des Mitglieder-Teams wurden zurückgesetzt.');
        }

        $team = Team::where('name', 'Mitglieder')->first();
        if (! $team) {
            $this->error('Team "Mitglieder" not found.');

            return 1;
        }
        $teamId = $team->id;

        // Map German month names to English, leave English as is
        $monthMap = [
            'Januar' => 'January',
            'Februar' => 'February',
            'März' => 'March',
            'April' => 'April',
            'Mai' => 'May',
            'Juni' => 'June',
            'Juli' => 'July',
            'August' => 'August',
            'September' => 'September',
            'Oktober' => 'October',
            'November' => 'November',
            'Dezember' => 'December',
        ];

        if (! ($handle = fopen($fullPath, 'r'))) {
            $this->error('Could not open CSV file.');

            return 1;
        }

        // Read header
        fgetcsv($handle, 0, ';');

        $bar = $this->output->createProgressBar();
        $bar->start();

        while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
            [$topic, $authorEmail, $timestampRaw, $content] = array_pad($row, 4, null);

            // Match user
            $user = User::where('email', trim($authorEmail))->first();
            if (! $user) {
                $bar->advance();

                continue;
            }

            // Extract roman_number from topic
            $parts = explode(' - ', $topic, 2);
            $romanNumber = isset($parts[0]) ? (int) ltrim($parts[0], '0') : null;
            $book = Book::where('roman_number', $romanNumber)->first();
            if (! $book) {
                $bar->advance();

                continue;
            }

            // Extract date portion via regex
            if (preg_match('/(\d{1,2}\.\s+\p{L}+\s+\d{4},\s*\d{1,2}:\d{2})/u', $timestampRaw, $matches)) {
                $datePart = $matches[1];
            } else {
                $bar->advance();

                continue;
            }

            // Replace German month to English
            $dateEn = strtr($datePart, $monthMap);

            try {
                $dt = Carbon::createFromFormat('j. F Y, H:i', $dateEn, config('app.timezone'))
                    ->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $bar->advance();

                continue;
            }

            app(RecordReview::class)->execute($user, $teamId, $book->id, [
                'title' => 'Rezi aus dem alten Maddrax-Forum',
                'content' => $content,
            ], Carbon::parse($dt, config('app.timezone')));

            $bar->advance();
        }

        fclose($handle);
        $bar->finish();
        $this->info(PHP_EOL.'Import completed.');

        return 0;
    }
}
