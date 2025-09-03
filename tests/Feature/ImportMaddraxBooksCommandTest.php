<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Book;
use Illuminate\Support\Facades\File;
use App\Enums\BookType;

class ImportMaddraxBooksCommandTest extends TestCase
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

    public function test_error_when_json_file_missing(): void
    {
        File::put(storage_path('app/private/hardcovers.json'), '[]');
        File::put(storage_path('app/private/missionmars.json'), '[]');

        $this->artisan('books:import', ['--path' => 'private/missing.json'])
            ->expectsOutput('Import for ' . BookType::MaddraxDieDunkleZukunftDerErde->value . ' failed: JSON file not found at ' . storage_path('app/private/missing.json'))
            ->expectsOutput(PHP_EOL . 'Import for ' . BookType::MaddraxHardcover->value . ' completed successfully.')
            ->expectsOutput(PHP_EOL . 'Import for ' . BookType::MissionMars->value . ' completed successfully.')
            ->assertExitCode(0);
    }

    public function test_error_with_invalid_json(): void
    {
        File::put(storage_path('app/private/maddrax.json'), '{ invalid json }');
        File::put(storage_path('app/private/hardcovers.json'), '[]');
        File::put(storage_path('app/private/missionmars.json'), '[]');

        $this->artisan('books:import', ['--path' => 'private/maddrax.json'])
            ->expectsOutput('Import for ' . BookType::MaddraxDieDunkleZukunftDerErde->value . ' failed: Invalid JSON - Syntax error')
            ->expectsOutput(PHP_EOL . 'Import for ' . BookType::MaddraxHardcover->value . ' completed successfully.')
            ->expectsOutput(PHP_EOL . 'Import for ' . BookType::MissionMars->value . ' completed successfully.')
            ->assertExitCode(0);
    }

    public function test_books_are_imported_and_invalid_entries_skipped(): void
    {
        Book::create(['roman_number' => 1, 'title' => 'Existing', 'author' => 'Old']);

        $data = [
            ['nummer' => 1, 'titel' => 'Roman1', 'text' => ['Author1', 'Author2']],
            ['nummer' => 2, 'titel' => null, 'text' => 'Author2'],
            ['titel' => 'Roman3', 'text' => ['Author3']],
            ['nummer' => 1, 'titel' => 'Roman1 new', 'text' => 'Author1 new'],
            ['nummer' => 4, 'titel' => 'Roman4', 'text' => 'Author4'],
        ];
        File::put(storage_path('app/private/maddrax.json'), json_encode($data));

        $hardcovers = [
            ['nummer' => 1, 'titel' => 'HC1', 'text' => 'AuthorHC1'],
            ['titel' => 'HC Invalid'],
        ];
        File::put(storage_path('app/private/hardcovers.json'), json_encode($hardcovers));

        $missionMars = [
            ['nummer' => 1, 'titel' => 'MM1', 'text' => 'AuthorMM1'],
            ['titel' => 'MM Invalid'],
        ];
        File::put(storage_path('app/private/missionmars.json'), json_encode($missionMars));

        $this->artisan('books:import', ['--path' => 'private/maddrax.json'])
            ->expectsOutput(PHP_EOL . 'Import for ' . BookType::MaddraxDieDunkleZukunftDerErde->value . ' completed successfully.')
            ->expectsOutput(PHP_EOL . 'Import for ' . BookType::MaddraxHardcover->value . ' completed successfully.')
            ->expectsOutput(PHP_EOL . 'Import for ' . BookType::MissionMars->value . ' completed successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('books', [
            'roman_number' => 1,
            'title' => 'Roman1 new',
            'author' => 'Author1 new',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde->value,
        ]);
        $this->assertDatabaseHas('books', [
            'roman_number' => 4,
            'title' => 'Roman4',
            'author' => 'Author4',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde->value,
        ]);
        $this->assertDatabaseHas('books', [
            'roman_number' => 1,
            'title' => 'HC1',
            'author' => 'AuthorHC1',
            'type' => BookType::MaddraxHardcover->value,
        ]);
        $this->assertDatabaseHas('books', [
            'roman_number' => 1,
            'title' => 'MM1',
            'author' => 'AuthorMM1',
            'type' => BookType::MissionMars->value,
        ]);
        $this->assertDatabaseMissing('books', ['roman_number' => 2, 'type' => BookType::MaddraxDieDunkleZukunftDerErde->value]);
        $this->assertDatabaseMissing('books', ['roman_number' => 3, 'type' => BookType::MaddraxDieDunkleZukunftDerErde->value]);
        $this->assertDatabaseMissing('books', ['roman_number' => null, 'type' => BookType::MaddraxHardcover->value]);
        $this->assertDatabaseMissing('books', ['roman_number' => null, 'type' => BookType::MissionMars->value]);
        $this->assertSame(4, Book::count());
    }

    protected function migrateFreshUsing(): array
    {
        return [
            '--path' => [
                'database/migrations/2025_05_18_065853_create_books_table.php',
                'database/migrations/2025_09_19_000000_add_type_to_books_table.php',
            ],
        ];
    }
}
