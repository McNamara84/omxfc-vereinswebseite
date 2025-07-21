<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Book;
use Illuminate\Support\Facades\File;

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
        $this->artisan('books:import', ['--path' => 'private/missing.json'])
            ->expectsOutput('JSON file not found at ' . storage_path('app/private/missing.json'))
            ->assertExitCode(1);
    }

    public function test_error_with_invalid_json(): void
    {
        File::put(storage_path('app/private/maddrax.json'), '{ invalid json }');

        $this->artisan('books:import', ['--path' => 'private/maddrax.json'])
            ->expectsOutput('Invalid JSON: Syntax error')
            ->assertExitCode(1);
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

        $this->artisan('books:import', ['--path' => 'private/maddrax.json'])
            ->expectsOutput(PHP_EOL . 'Import completed successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('books', [
            'roman_number' => 1,
            'title' => 'Roman1 new',
            'author' => 'Author1 new',
        ]);
        $this->assertDatabaseHas('books', [
            'roman_number' => 4,
            'title' => 'Roman4',
            'author' => 'Author4',
        ]);
        $this->assertDatabaseMissing('books', ['roman_number' => 2]);
        $this->assertDatabaseMissing('books', ['roman_number' => 3]);
        $this->assertSame(2, Book::count());
    }
}
