<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportMaddraxBooksCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testStoragePath = base_path('storage/testing-maddrax-import');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath.'/app/private');
        File::ensureDirectoryExists($this->testStoragePath.'/framework/views');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);
        parent::tearDown();
    }

    public function test_missing_file_fails_entire_import_without_partial_writes(): void
    {
        $this->writeValidFiles();
        File::delete(storage_path('app/private/maddrax.json'));

        $this->artisan('books:import')
            ->expectsOutputToContain('JSON file not found')
            ->expectsOutputToContain('Keine Bücher wurden importiert')
            ->assertFailed();

        $this->assertSame(0, Book::count());
    }

    public function test_invalid_json_and_missing_cycle_fail_closed(): void
    {
        $this->writeValidFiles();
        File::put(storage_path('app/private/maddrax.json'), '{ kaputt');

        $this->artisan('books:import')
            ->expectsOutputToContain('Invalid JSON')
            ->assertFailed();
        $this->assertSame(0, Book::count());

        $this->writeValidFiles();
        File::put(storage_path('app/private/maddrax.json'), json_encode([
            $this->row(1, null, 'Ohne Zyklus'),
        ]));

        $this->artisan('books:import')
            ->expectsOutputToContain('hat keinen Zyklus')
            ->assertFailed();
        $this->assertSame(0, Book::count());
    }

    public function test_import_persists_cycle_and_all_supported_series(): void
    {
        $this->writeValidFiles();

        $this->artisan('books:import')->assertSuccessful();

        foreach (BookType::cases() as $type) {
            $this->assertDatabaseHas('books', [
                'roman_number' => 1,
                'type' => $type->value,
            ]);
        }
        $this->assertDatabaseHas('books', [
            'roman_number' => 1,
            'type' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'cycle' => 'Euree',
            'maddraxikon_page_title' => 'MX 1',
        ]);
        $this->assertSame(6, Book::count());
    }

    public function test_duplicate_number_rejects_every_series_before_database_write(): void
    {
        $this->writeValidFiles();
        File::put(storage_path('app/private/missionmars.json'), json_encode([
            $this->row(1, null, 'Eins'),
            $this->row(1, null, 'Doppelt'),
        ]));

        $this->artisan('books:import')
            ->expectsOutputToContain('doppelt')
            ->assertFailed();

        $this->assertSame(0, Book::count());
    }

    private function writeValidFiles(): void
    {
        $rows = [
            'maddrax.json' => [$this->row(1, 'Euree', 'Maddrax')],
            'hardcovers.json' => [$this->row(1, null, 'Hardcover')],
            'missionmars.json' => [$this->row(1, null, 'Mission Mars')],
            'volkdertiefe.json' => [$this->row(1, null, 'Das Volk der Tiefe')],
            '2012.json' => [$this->row(1, null, '2012')],
            'abenteurer.json' => [$this->row(1, null, 'Die Abenteurer')],
        ];

        foreach ($rows as $filename => $data) {
            File::put(storage_path('app/private/'.$filename), json_encode($data));
        }
    }

    /** @return array<string, mixed> */
    private function row(int $number, ?string $cycle, string $title): array
    {
        return [
            'nummer' => $number,
            'titel' => $title,
            'zyklus' => $cycle,
            'text' => ['Autor A', 'Autor B'],
            'maddraxikon_seitentitel' => 'MX '.$number,
        ];
    }
}
