<?php

namespace App\Services\CoverRatings;

use App\Enums\BookCoverStatus;
use App\Models\BookCover;
use App\Models\CoverRating;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CoverRatingService
{
    public function __construct(
        private readonly CoverRatingBaxxService $baxxService,
    ) {}

    /**
     * @return array{rating: CoverRating, awarded_baxx: int, first_rating: bool}
     */
    public function rate(User $user, int $bookCoverId, int $value): array
    {
        Gate::forUser($user)->authorize('create', CoverRating::class);
        $this->validateValue($value);
        $this->enforceRateLimit($user);

        return DB::transaction(function () use ($user, $bookCoverId, $value): array {
            // Serialize first-time ratings per member so concurrent requests cannot
            // observe the same lifetime count at a Baxx milestone boundary.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $cover = BookCover::query()
                ->whereKey($bookCoverId)
                ->where('status', BookCoverStatus::Ready)
                ->whereNotNull('small_path')
                ->whereNotNull('large_path')
                ->lockForUpdate()
                ->firstOrFail();
            $existing = CoverRating::query()
                ->withTrashed()
                ->where('user_id', $user->id)
                ->where('book_cover_id', $cover->id)
                ->first();
            $firstRating = ! $existing;
            $timestamp = now();

            CoverRating::query()->upsert(
                [[
                    'user_id' => $user->id,
                    'book_cover_id' => $cover->id,
                    'rating' => $value,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'deleted_at' => null,
                ]],
                ['user_id', 'book_cover_id'],
                ['rating', 'updated_at', 'deleted_at'],
            );

            $rating = CoverRating::query()
                ->where('user_id', $user->id)
                ->where('book_cover_id', $cover->id)
                ->firstOrFail();
            $awardedBaxx = $this->baxxService->awardForRating($user, $firstRating);

            return [
                'rating' => $rating,
                'awarded_baxx' => $awardedBaxx,
                'first_rating' => $firstRating,
            ];
        });
    }

    public function update(User $user, CoverRating $rating, int $value): CoverRating
    {
        Gate::forUser($user)->authorize('update', $rating);
        $this->validateValue($value);
        $rating->update(['rating' => $value]);

        return $rating->refresh();
    }

    public function delete(User $user, CoverRating $rating): void
    {
        Gate::forUser($user)->authorize('delete', $rating);
        $rating->delete();
    }

    private function validateValue(int $value): void
    {
        if ($value < 1 || $value > 5) {
            throw ValidationException::withMessages([
                'rating' => 'Bitte wähle zwischen 1 und 5 Brinas.',
            ]);
        }
    }

    private function enforceRateLimit(User $user): void
    {
        $key = 'cover-ratings:submit:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 120)) {
            throw ValidationException::withMessages([
                'rating' => 'Du bewertest gerade zu schnell. Bitte warte kurz.',
            ]);
        }

        RateLimiter::hit($key, 60);
    }
}
