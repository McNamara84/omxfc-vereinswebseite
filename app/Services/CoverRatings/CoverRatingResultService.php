<?php

namespace App\Services\CoverRatings;

use App\Enums\BookCoverStatus;
use App\Enums\BookType;
use App\Models\Book;
use App\Models\BookCover;
use App\Models\CoverRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CoverRatingResultService
{
    public function resultsQuery(User $user, string $seriesKey = 'all', string $sort = 'best'): Builder
    {
        $query = BookCover::query()
            ->with('book')
            ->where('status', BookCoverStatus::Ready)
            ->whereHas('ratings', fn (Builder $ratings) => $ratings->where('user_id', $user->id))
            ->withCount('ratings')
            ->withAvg('ratings', 'rating')
            ->withMax('ratings', 'updated_at');

        $this->applySeries($query, $seriesKey);

        return match ($sort) {
            'votes' => $query->orderByDesc('ratings_count')->orderByDesc('ratings_avg_rating'),
            'recent' => $query->orderByDesc('ratings_max_updated_at'),
            'number' => $query->orderBy(
                Book::query()->select('roman_number')->whereColumn('books.id', 'book_covers.book_id')
            ),
            default => $query->orderByDesc('ratings_avg_rating')->orderByDesc('ratings_count'),
        };
    }

    public function personalQuery(User $user, string $seriesKey = 'all'): Builder
    {
        $query = CoverRating::query()
            ->where('user_id', $user->id)
            ->with('bookCover.book')
            ->orderByDesc('updated_at');

        if ($seriesKey !== 'all' && ($type = BookType::fromKey($seriesKey))) {
            $query->whereHas(
                'bookCover.book',
                fn (Builder $bookQuery) => $bookQuery->where('type', $type),
            );
        }

        return $query;
    }

    private function applySeries(Builder $query, string $seriesKey): void
    {
        if ($seriesKey !== 'all' && ($type = BookType::fromKey($seriesKey))) {
            $query->whereHas('book', fn (Builder $bookQuery) => $bookQuery->where('type', $type));
        }
    }
}
