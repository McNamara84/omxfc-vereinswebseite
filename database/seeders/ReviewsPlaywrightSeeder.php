<?php

namespace Database\Seeders;

use App\Enums\BookType;
use App\Enums\Role;
use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ReviewsPlaywrightSeeder extends Seeder
{
    /**
     * Seed review data for Playwright end-to-end tests.
     */
    public function run(): void
    {
        $team = Team::membersTeam();

        if (! $team) {
            return;
        }

        $admin = User::firstWhere('email', 'info@maddraxikon.com');

        if (! $admin) {
            return;
        }

        $reviewer = User::firstOrCreate(
            ['email' => 'playwright-reviewer@example.com'],
            [
                'name' => 'Playwright Rezensent',
                'vorname' => 'Playwright',
                'nachname' => 'Rezensent',
                'strasse' => 'Teststraße',
                'hausnummer' => '1',
                'plz' => '12345',
                'stadt' => 'Teststadt',
                'land' => 'Deutschland',
                'telefon' => '0000000',
                'verein_gefunden' => 'Sonstiges',
                'mitgliedsbeitrag' => 36.00,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );

        $team->users()->syncWithoutDetaching([
            $admin->id => ['role' => Role::Admin->value],
            $reviewer->id => ['role' => Role::Mitglied->value],
        ]);

        $reviewer->forceFill(['current_team_id' => $team->id])->save();

        $books = [
            Book::updateOrCreate(
                ['roman_number' => 1],
                ['title' => 'Einstieg ins Maddraxiversum', 'author' => 'Test Autorin']
            ),
            Book::updateOrCreate(
                ['roman_number' => 125],
                ['title' => 'Wandler Roman', 'author' => 'Max Mustermann']
            ),
        ];

        $missionMars = Book::updateOrCreate(
            ['roman_number' => 5001],
            [
                'title' => 'Mission Mars – Auftakt',
                'author' => 'Stern Wanderer',
                'type' => BookType::MissionMars,
            ]
        );

        $hardcover = Book::updateOrCreate(
            ['roman_number' => 9001],
            [
                'title' => 'Maddrax Hardcover – Sammlerausgabe',
                'author' => 'Archiv Autor',
                'type' => BookType::MaddraxHardcover,
            ]
        );

        Review::updateOrCreate(
            [
                'team_id' => $team->id,
                'user_id' => $admin->id,
                'book_id' => $books[0]->id,
            ],
            [
                'title' => 'Admin Sicht auf den Auftakt',
                'content' => str_repeat('Eine fesselnde Zukunftsvision. ', 6),
            ]
        );

        Review::updateOrCreate(
            [
                'team_id' => $team->id,
                'user_id' => $reviewer->id,
                'book_id' => $books[0]->id,
            ],
            [
                'title' => 'Mitgliedsmeinung zum Start',
                'content' => str_repeat('Die Charaktere wirken lebendig und motivierend. ', 4),
            ]
        );

        Review::updateOrCreate(
            [
                'team_id' => $team->id,
                'user_id' => $reviewer->id,
                'book_id' => $missionMars->id,
            ],
            [
                'title' => 'Mission Mars Eindruck',
                'content' => str_repeat('Spannende Weltraumabenteuer mit viel Atmosphäre. ', 4),
            ]
        );

        $team->users()->syncWithoutDetaching([
            $reviewer->id => ['role' => Role::Mitglied->value],
        ]);
    }
}
