<?php

namespace Tests\Feature;

use App\Enums\BookType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FixMissionMarsEnumMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_migration_updates_mission_mars_value_in_sqlite(): void
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

    #[Test]
    public function test_books_table_accepts_all_book_type_enum_values(): void
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

    #[Test]
    public function test_book_type_enum_matches_database_values(): void
    {
        $enumValues = [
            BookType::MaddraxDieDunkleZukunftDerErde->value,
            BookType::MaddraxHardcover->value,
            BookType::MissionMars->value,
            BookType::DasVolkDerTiefe->value,
            BookType::ZweiTausendZwölfDasJahrDerApokalypse->value,
            BookType::DieAbenteurer->value,
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
