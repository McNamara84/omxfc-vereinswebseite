<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Models\Book;
use App\Services\Maddraxikon\MaddraxikonBookImporter;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonBookImporterTestFixtures::class);

test('changed or empty page titles invalidate existing mappings', function (): void {
    $verifiedAt = now()->subHour()->startOfSecond();
    Book::factory()->create(['roman_number' => 1, 'type' => BookType::MaddraxHardcover, 'maddraxikon_page_title' => 'HC 1 – Veraltet', 'maddraxikon_page_id' => 101, 'maddraxikon_page_verified_at' => $verifiedAt]);
    Book::factory()->create(['roman_number' => 2, 'type' => BookType::MaddraxHardcover, 'maddraxikon_page_title' => 'HC 2', 'maddraxikon_page_id' => 102, 'maddraxikon_page_verified_at' => $verifiedAt]);
    Book::factory()->create(['roman_number' => 3, 'type' => BookType::MaddraxHardcover, 'maddraxikon_page_title' => null, 'maddraxikon_page_id' => 103, 'maddraxikon_page_verified_at' => $verifiedAt]);
    app(MaddraxikonBookImporter::class)->import(['hardcovers' => [$this->row(1, '   '), $this->row(2, 'HC 2'), $this->row(3, null)]]);
    $invalidated = Book::query()->where('roman_number', 1)->where('type', BookType::MaddraxHardcover)->firstOrFail();
    $unchanged = Book::query()->where('roman_number', 2)->where('type', BookType::MaddraxHardcover)->firstOrFail();
    $unverifiable = Book::query()->where('roman_number', 3)->where('type', BookType::MaddraxHardcover)->firstOrFail();
    expect($invalidated->maddraxikon_page_title)->toBeNull()
        ->and($invalidated->maddraxikon_page_id)->toBeNull()
        ->and($invalidated->maddraxikon_page_verified_at)->toBeNull()
        ->and($unchanged->maddraxikon_page_title)->toBe('HC 2')
        ->and($unchanged->maddraxikon_page_id)->toBe(102)
        ->and($verifiedAt->equalTo($unchanged->maddraxikon_page_verified_at))->toBeTrue()
        ->and($unverifiable->maddraxikon_page_title)->toBeNull()
        ->and($unverifiable->maddraxikon_page_id)->toBeNull()
        ->and($unverifiable->maddraxikon_page_verified_at)->toBeNull();
});

test('full import uses one lookup and batched upserts per series', function (): void {
    $rows = array_map(fn (int $number): array => $this->row($number, "HC {$number}"), range(1, 251));
    $bookQueries = [];
    DB::listen(function (QueryExecuted $query) use (&$bookQueries): void {
        if (str_contains(strtolower($query->sql), 'books')) {
            $bookQueries[] = $query->sql;
        }
    });
    app(MaddraxikonBookImporter::class)->import(['hardcovers' => $rows]);
    expect($bookQueries)->toHaveCount(3)
        ->and(strtolower($bookQueries[0]))->toStartWith('select')
        ->and(strtolower($bookQueries[1]))->toStartWith('insert')
        ->and(strtolower($bookQueries[2]))->toStartWith('insert')
        ->and(Book::query()->where('type', BookType::MaddraxHardcover)->count())->toBe(251);
});
