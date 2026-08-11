<?php

namespace App\Services\Maddraxikon;

use App\Data\MaddraxikonRatingLookup;
use App\Data\MaddraxikonRatingSyncResult;
use App\Models\Book;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonRatingSyncState;
use App\Models\MaddraxikonReviewRating;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class MaddraxikonRatingSynchronizer
{
    public function __construct(
        private readonly MaddraxikonRatingSource $source,
    ) {}

    public function sync(bool $dryRun = false, bool $force = false): MaddraxikonRatingSyncResult
    {
        if (! config('maddraxikon.features.ratings_enabled', false) && ! $force) {
            return new MaddraxikonRatingSyncResult(disabled: true, dryRun: $dryRun);
        }

        $wikiKey = (string) config('maddraxikon.wiki_key', 'maddraxikon-de');
        $consentVersion = (string) config('maddraxikon.consent_version');
        $state = $dryRun ? null : MaddraxikonRatingSyncState::query()->firstOrCreate([
            'wiki_key' => $wikiKey,
        ]);
        $state?->update(['last_started_at' => now()]);
        $candidates = 0;
        $updated = 0;
        $removed = 0;
        $skipped = 0;

        try {
            Review::query()
                ->whereHas('user.maddraxikonAccountLink', function (Builder $query) use ($consentVersion, $wikiKey): void {
                    $query->activeForWikiAndConsent($wikiKey, $consentVersion);
                })
                ->whereHas('book', function (Builder $query): void {
                    $query->whereNotNull('maddraxikon_page_id');
                })
                ->with([
                    'book',
                    'user.maddraxikonAccountLink',
                    'maddraxikonRating',
                ])
                ->orderBy('id')
                ->chunkById(100, function ($reviews) use (
                    $dryRun,
                    &$candidates,
                    &$updated,
                    &$removed,
                    &$skipped,
                    $consentVersion,
                    $wikiKey,
                ): void {
                    $lookups = [];

                    foreach ($reviews as $review) {
                        $link = $review->user->maddraxikonAccountLink;
                        $pageId = $review->book->maddraxikon_page_id;

                        if (
                            ! $link?->isActiveForWikiAndConsent($wikiKey, $consentVersion)
                            || ! is_int($pageId)
                            || $pageId < 1
                        ) {
                            $skipped++;

                            continue;
                        }

                        $lookups[] = new MaddraxikonRatingLookup(
                            wikiUserId: $link->wiki_user_id,
                            pageId: $pageId,
                        );
                    }

                    $ratings = $this->source->ratingsFor($lookups);

                    foreach ($reviews as $review) {
                        $candidates++;
                        $link = $review->user->maddraxikonAccountLink;
                        $pageId = $review->book->maddraxikon_page_id;

                        if (
                            ! $link?->isActiveForWikiAndConsent($wikiKey, $consentVersion)
                            || ! is_int($pageId)
                            || $pageId < 1
                        ) {
                            continue;
                        }

                        $key = MaddraxikonRatingLookup::makeKey(
                            $link->wiki_user_id,
                            $pageId,
                        );
                        $sourceRating = $ratings[$key] ?? null;

                        if ($sourceRating === null) {
                            if ($review->maddraxikonRating !== null) {
                                $removed++;

                                if (! $dryRun) {
                                    $review->maddraxikonRating->delete();
                                }
                            } else {
                                $skipped++;
                            }

                            continue;
                        }

                        if ($dryRun) {
                            $updated++;

                            continue;
                        }

                        MaddraxikonReviewRating::query()->updateOrCreate(
                            ['review_id' => $review->id],
                            [
                                'book_id' => $review->book_id,
                                'user_id' => $review->user_id,
                                'account_link_id' => $link->id,
                                'maddraxikon_page_id' => $pageId,
                                'wiki_user_id' => $link->wiki_user_id,
                                'rating' => $sourceRating->rating,
                                'source_voted_at' => $sourceRating->votedAt,
                                'synced_at' => now(),
                            ],
                        );

                        $updated++;
                    }
                });

            $removed += $this->cleanupInvalidSnapshots(
                $dryRun,
                $wikiKey,
                $consentVersion,
            );

            $state?->update([
                'last_succeeded_at' => now(),
                'last_error_at' => null,
                'last_error_category' => null,
                'consecutive_failures' => 0,
                'last_candidate_count' => $candidates,
                'last_updated_count' => $updated,
                'last_removed_count' => $removed,
                'last_skipped_count' => $skipped,
            ]);
        } catch (Throwable $exception) {
            $state?->update([
                'last_error_at' => now(),
                'last_error_category' => class_basename($exception),
                'consecutive_failures' => ($state->consecutive_failures ?? 0) + 1,
            ]);

            throw $exception;
        }

        return new MaddraxikonRatingSyncResult(
            candidates: $candidates,
            updated: $updated,
            removed: $removed,
            skipped: $skipped,
            dryRun: $dryRun,
        );
    }

    private function cleanupInvalidSnapshots(
        bool $dryRun,
        string $wikiKey,
        string $consentVersion,
    ): int {
        $removed = 0;

        MaddraxikonReviewRating::query()
            ->with([
                'review.book',
                'review.user.maddraxikonAccountLink',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($snapshots) use (
                $consentVersion,
                $dryRun,
                &$removed,
                $wikiKey,
            ): void {
                foreach ($snapshots as $snapshot) {
                    $review = $snapshot->review;
                    $user = $review?->user;
                    $book = $review?->book;
                    $link = $user?->maddraxikonAccountLink;

                    if ($this->snapshotMatches(
                        $snapshot,
                        $review,
                        $user,
                        $book,
                        $link,
                        $wikiKey,
                        $consentVersion,
                    )) {
                        continue;
                    }

                    $removed++;

                    if (! $dryRun) {
                        $snapshot->delete();
                    }
                }
            });

        return $removed;
    }

    private function snapshotMatches(
        MaddraxikonReviewRating $snapshot,
        ?Review $review,
        ?User $user,
        ?Book $book,
        ?MaddraxikonAccountLink $link,
        string $wikiKey,
        string $consentVersion,
    ): bool {
        return $review !== null
            && $user !== null
            && $book !== null
            && $link?->isActiveForWikiAndConsent($wikiKey, $consentVersion)
            && $book->maddraxikon_page_id !== null
            && $snapshot->review_id === $review->id
            && $snapshot->book_id === $book->id
            && $snapshot->user_id === $user->id
            && $snapshot->account_link_id === $link->id
            && $snapshot->maddraxikon_page_id === $book->maddraxikon_page_id
            && $snapshot->wiki_user_id === $link->wiki_user_id;
    }
}
