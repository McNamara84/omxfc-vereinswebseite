<?php

namespace App\Services\CoverRatings;

use App\Enums\BookCoverStatus;
use App\Enums\BookType;
use App\Models\BookCover;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CoverSelectionService
{
    /**
     * @param  list<int>  $excludedCoverIds
     */
    public function next(
        User $user,
        string $seriesKey = 'all',
        array $excludedCoverIds = [],
        ?string $lastSeriesKey = null,
    ): ?BookCover {
        $series = $this->resolveSeries($seriesKey);
        $excludedCoverIds = collect($excludedCoverIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(2000)
            ->values()
            ->all();

        if ($series instanceof BookType) {
            return $this->randomCover(
                $this->eligibleQuery($user, $excludedCoverIds)
                    ->whereHas('book', fn (Builder $query) => $query->where('type', $series)),
            );
        }

        $availableTypes = $this->eligibleQuery($user, $excludedCoverIds)
            ->join('books', 'books.id', '=', 'book_covers.book_id')
            ->distinct()
            ->pluck('books.type')
            ->map(fn (mixed $value): ?BookType => is_string($value) ? BookType::tryFrom($value) : null)
            ->filter()
            ->values();

        if ($availableTypes->count() > 1 && $lastSeriesKey !== null) {
            $availableTypes = $availableTypes
                ->reject(fn (BookType $type): bool => $type->key() === $lastSeriesKey)
                ->values();
        }

        if ($availableTypes->isEmpty()) {
            return null;
        }

        /** @var BookType $selectedType */
        $selectedType = $availableTypes->get(random_int(0, $availableTypes->count() - 1));

        return $this->randomCover(
            $this->eligibleQuery($user, $excludedCoverIds)
                ->whereHas('book', fn (Builder $query) => $query->where('type', $selectedType)),
        );
    }

    /**
     * @return array{rated: int, total: int, remaining: int}
     */
    public function progress(User $user, string $seriesKey = 'all'): array
    {
        $series = $this->resolveSeries($seriesKey);
        $base = $this->readyQuery();

        if ($series instanceof BookType) {
            $base->whereHas('book', fn (Builder $query) => $query->where('type', $series));
        }

        $total = (clone $base)->count();
        $rated = (clone $base)
            ->whereHas('ratings', fn (Builder $query) => $query->where('user_id', $user->id))
            ->count();

        return [
            'rated' => $rated,
            'total' => $total,
            'remaining' => max(0, $total - $rated),
        ];
    }

    public function resolveSeries(string $seriesKey): ?BookType
    {
        if ($seriesKey === 'all') {
            return null;
        }

        $series = BookType::fromKey($seriesKey);

        if (! $series) {
            throw ValidationException::withMessages([
                'series' => 'Die gewählte Serie ist ungültig.',
            ]);
        }

        return $series;
    }

    /** @param list<int> $excludedCoverIds */
    private function eligibleQuery(User $user, array $excludedCoverIds): Builder
    {
        return $this->readyQuery()
            ->when(
                $excludedCoverIds !== [],
                fn (Builder $query) => $query->whereNotIn('book_covers.id', $excludedCoverIds),
            )
            ->whereDoesntHave(
                'ratings',
                fn (Builder $query) => $query->where('user_id', $user->id),
            );
    }

    private function readyQuery(): Builder
    {
        return BookCover::query()
            ->where('status', BookCoverStatus::Ready)
            ->whereNotNull('small_path')
            ->whereNotNull('large_path');
    }

    private function randomCover(Builder $query): ?BookCover
    {
        $count = (clone $query)->count();

        if ($count === 0) {
            return null;
        }

        return $query
            ->with('book')
            ->orderBy('book_covers.id')
            ->offset(random_int(0, $count - 1))
            ->first();
    }
}
