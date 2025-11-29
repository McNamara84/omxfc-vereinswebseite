<?php

namespace Tests\Feature;

use Database\Migrations\FixMissionMarsEnumValueInBooksTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FixMissionMarsEnumMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function migration_updates_mission_mars_value_in_sqlite(): void
    {
        // This test runs with SQLite (as configured in phpunit.xml)
        $this->assertEquals('sqlite', DB::connection()->getDriverName());

        // Insert a book with the old Mission Mars value
        DB::table('books')->insert([
            'roman_number' => 999,
            'title' => 'Test Mission Mars Book',
            'author' => 'Test Author',
            'type' => 'Mission Mars',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify the old value exists
        $this->assertDatabaseHas('books', [
            'roman_number' => 999,
            'type' => 'Mission Mars',
        ]);

        // Run the actual migration class
        $migration = require database_path('migrations/2025_11_29_102701_fix_mission_mars_enum_value_in_books_table.php');
        $migration->up();

        // Verify the value was updated
        $this->assertDatabaseHas('books', [
            'roman_number' => 999,
            'type' => 'Mission Mars-Heftromane',
        ]);

        // Verify old value no longer exists
        $this->assertDatabaseMissing('books', [
            'roman_number' => 999,
            'type' => 'Mission Mars',
        ]);
    }

    /** @test */
    public function books_table_accepts_all_book_type_enum_values(): void
    {
        $bookTypes = [
            'Maddrax - Die dunkle Zukunft der Erde',
            'Maddrax-Hardcover',
            'Mission Mars-Heftromane',
            'Das Volk der Tiefe',
            '2012 - Das Jahr der Apokalypse',
            'Die Abenteurer',
        ];

        foreach ($bookTypes as $index => $type) {
            DB::table('books')->insert([
                'roman_number' => 1000 + $index,
                'title' => "Test Book for {$type}",
                'author' => 'Test Author',
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertDatabaseHas('books', [
                'roman_number' => 1000 + $index,
                'type' => $type,
            ]);
        }
    }

    /** @test */
    public function book_type_enum_matches_database_values(): void
    {
        $enumValues = [
            \App\Enums\BookType::MaddraxDieDunkleZukunftDerErde->value,
            \App\Enums\BookType::MaddraxHardcover->value,
            \App\Enums\BookType::MissionMars->value,
            \App\Enums\BookType::DasVolkDerTiefe->value,
            \App\Enums\BookType::ZweiTausendZwölfDasJahrDerApokalypse->value,
            \App\Enums\BookType::DieAbenteurer->value,
        ];

        foreach ($enumValues as $index => $type) {
            DB::table('books')->insert([
                'roman_number' => 2000 + $index,
                'title' => "Enum Test Book for {$type}",
                'author' => 'Test Author',
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertDatabaseHas('books', [
                'roman_number' => 2000 + $index,
                'type' => $type,
            ]);
        }
    }
}
