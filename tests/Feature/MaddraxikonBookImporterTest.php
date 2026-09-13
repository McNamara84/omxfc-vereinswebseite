<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Models\Book;
use App\Services\Maddraxikon\MaddraxikonBookImporter;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaddraxikonBookImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_changed_or_empty_page_titles_invalidate_existing_mappings(): void
    {
        $verifiedAt = now()->subHour()->startOfSecond();
        Book::factory()->create([
            'roman_number' => 1,
            'type' => BookType::MaddraxHardcover,
            'maddraxikon_page_title' => 'HC 1 – Veraltet',
            'maddraxikon_page_id' => 101,
            'maddraxikon_page_verified_at' => $verifiedAt,
        ]);
        Book::factory()->create([
            'roman_number' => 2,
            'type' => BookType::MaddraxHardcover,
            'maddraxikon_page_title' => 'HC 2',
            'maddraxikon_page_id' => 102,
            'maddraxikon_page_verified_at' => $verifiedAt,
        ]);
        Book::factory()->create([
            'roman_number' => 3,
            'type' => BookType::MaddraxHardcover,
            'maddraxikon_page_title' => null,
            'maddraxikon_page_id' => 103,
            'maddraxikon_page_verified_at' => $verifiedAt,
        ]);

        app(MaddraxikonBookImporter::class)->import([
            'hardcovers' => [
                $this->row(1, '   '),
                $this->row(2, 'HC 2'),
                $this->row(3, null),
            ],
        ]);

        $invalidated = Book::query()
            ->where('roman_number', 1)
            ->where('type', BookType::MaddraxHardcover)
            ->firstOrFail();
        $unchanged = Book::query()
            ->where('roman_number', 2)
            ->where('type', BookType::MaddraxHardcover)
            ->firstOrFail();
        $unverifiable = Book::query()
            ->where('roman_number', 3)
            ->where('type', BookType::MaddraxHardcover)
            ->firstOrFail();

        $this->assertNull($invalidated->maddraxikon_page_title);
        $this->assertNull($invalidated->maddraxikon_page_id);
        $this->assertNull($invalidated->maddraxikon_page_verified_at);
        $this->assertSame('HC 2', $unchanged->maddraxikon_page_title);
        $this->assertSame(102, $unchanged->maddraxikon_page_id);
        $this->assertTrue($verifiedAt->equalTo($unchanged->maddraxikon_page_verified_at));
        $this->assertNull($unverifiable->maddraxikon_page_title);
        $this->assertNull($unverifiable->maddraxikon_page_id);
        $this->assertNull($unverifiable->maddraxikon_page_verified_at);
    }

    public function test_full_import_uses_one_lookup_and_batched_upserts_per_series(): void
    {
        $rows = array_map(
            fn (int $number): array => $this->row($number, "HC {$number}"),
            range(1, 251),
        );
        $bookQueries = [];

        DB::listen(function (QueryExecuted $query) use (&$bookQueries): void {
            if (str_contains(strtolower($query->sql), 'books')) {
                $bookQueries[] = $query->sql;
            }
        });

        app(MaddraxikonBookImporter::class)->import(['hardcovers' => $rows]);

        $this->assertCount(3, $bookQueries);
        $this->assertStringStartsWith('select', strtolower($bookQueries[0]));
        $this->assertStringStartsWith('insert', strtolower($bookQueries[1]));
        $this->assertStringStartsWith('insert', strtolower($bookQueries[2]));
        $this->assertSame(
            251,
            Book::query()->where('type', BookType::MaddraxHardcover)->count(),
        );
    }

    /** @return array<string, mixed> */
    private function row(int $number, ?string $pageTitle): array
    {
        return [
            'nummer' => $number,
            'titel' => "Hardcover {$number}",
            'text' => ['Autor'],
            'zyklus' => null,
            'maddraxikon_seitentitel' => $pageTitle,
        ];
    }
}
