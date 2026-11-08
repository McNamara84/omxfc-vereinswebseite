<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Models\Book;
use App\Services\Maddraxikon\MaddraxikonRefreshLock;
use App\Services\Maddraxikon\MaddraxikonSnapshotRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(ImportMaddraxBooksCommandTestFixtures::class);

beforeEach(function (): void {
    $this->testStoragePath = base_path('storage/testing-maddrax-import');
    $this->app->useStoragePath($this->testStoragePath);
    File::ensureDirectoryExists($this->testStoragePath.'/app/private');
    File::ensureDirectoryExists($this->testStoragePath.'/framework/views');
});

afterEach(function (): void {
    File::deleteDirectory($this->testStoragePath);
});

test('missing file fails entire import without partial writes', function (): void {
    $this->writeValidFiles();
    File::delete(storage_path('app/private/maddrax.json'));
    $this->artisan('books:import')->expectsOutputToContain('JSON file not found')->expectsOutputToContain('Keine Bücher wurden importiert')->assertFailed();
    expect(Book::count())->toBe(0);
});

test('invalid json and missing cycle fail closed', function (): void {
    $this->writeValidFiles();
    File::put(storage_path('app/private/maddrax.json'), '{ kaputt');
    $this->artisan('books:import')->expectsOutputToContain('Invalid JSON')->assertFailed();
    expect(Book::count())->toBe(0);
    $this->writeValidFiles();
    File::put(storage_path('app/private/maddrax.json'), json_encode([$this->row(1, null, 'Ohne Zyklus')]));
    $this->artisan('books:import')->expectsOutputToContain('hat keinen Zyklus')->assertFailed();
    expect(Book::count())->toBe(0);
});

test('import persists cycle and all supported series', function (): void {
    $this->writeValidFiles();
    $this->artisan('books:import')->assertSuccessful();
    foreach (BookType::cases() as $type) {
        $this->assertDatabaseHas('books', ['roman_number' => 1, 'type' => $type->value]);
    }
    $this->assertDatabaseHas('books', ['roman_number' => 1, 'type' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'cycle' => 'Euree', 'maddraxikon_page_title' => 'MX 1']);
    expect(Book::count())->toBe(6);
});

test('duplicate number rejects every series before database write', function (): void {
    $this->writeValidFiles();
    File::put(storage_path('app/private/missionmars.json'), json_encode([$this->row(1, null, 'Eins'), $this->row(1, null, 'Doppelt')]));
    $this->artisan('books:import')->expectsOutputToContain('doppelt')->assertFailed();
    expect(Book::count())->toBe(0);
});

test('explicit legacy path is rejected when an active snapshot exists', function (): void {
    $this->writeValidFiles();
    $datasets = collect(BookType::cases())->mapWithKeys(fn (BookType $type): array => [$type->key() => [$this->row(1, $type === BookType::MaddraxDieDunkleZukunftDerErde ? 'Weltrat' : null, 'Snapshot '.$type->key())]])->all();
    $json = collect($datasets)->map(static fn (array $rows): string => (string) json_encode($rows, JSON_THROW_ON_ERROR))->all();
    DB::transaction(fn () => app(MaddraxikonSnapshotRepository::class)->storeAndActivate('33333333-3333-4333-8333-333333333333', $json));
    $this->artisan('books:import')->assertSuccessful();
    $this->assertDatabaseHas('books', ['roman_number' => 1, 'type' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'title' => 'Snapshot maddrax', 'cycle' => 'Weltrat']);
    $this->artisan('books:import', ['--path' => 'private/maddrax.json'])->expectsOutputToContain('expliziter Dateipfad')->assertFailed();
    $this->assertDatabaseHas('books', ['roman_number' => 1, 'type' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'title' => 'Snapshot maddrax', 'cycle' => 'Weltrat']);
});

test('explicit legacy path remains available before the first snapshot', function (): void {
    $this->writeValidFiles();
    File::put(storage_path('app/private/custom-maddrax.json'), json_encode([$this->row(1, 'Weltrat', 'Expliziter Import')]));
    $this->artisan('books:import', ['--path' => 'private/custom-maddrax.json'])->assertSuccessful();
    $this->assertDatabaseHas('books', ['roman_number' => 1, 'type' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'title' => 'Expliziter Import', 'cycle' => 'Weltrat']);
});

test('import is rejected while a refresh holds the shared lock', function (): void {
    $this->writeValidFiles();
    $lock = app(MaddraxikonRefreshLock::class)->acquire();
    expect($lock)->not->toBeNull();
    try {
        $this->artisan('books:import')->expectsOutputToContain('anderer Maddraxikon-Romandaten-Refresh')->assertFailed();
    } finally {
        $lock?->release();
    }
    expect(Book::count())->toBe(0);
});
