<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Models\Book;
use App\Models\BookCover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait CoverRatingFeatureTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function readyCover(BookType $type, int $number, ?string $title = null): BookCover
    {
        $book = Book::factory()->create(['type' => $type, 'roman_number' => $number, 'title' => $title ?? $type->label().' '.$number]);

        return BookCover::factory()->for($book)->create(['small_path' => "cover-ratings/{$book->id}/small.webp", 'large_path' => "cover-ratings/{$book->id}/large.webp"]);
    }
}
