<?php

namespace App\Services\Maddraxikon;

use App\Data\MaddraxikonRatingData;
use App\Data\MaddraxikonRatingLookup;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MaddraxikonRatingSource
{
    /**
     * @param  list<MaddraxikonRatingLookup>  $lookups
     * @return array<string, MaddraxikonRatingData>
     */
    public function ratingsFor(array $lookups): array
    {
        $unique = [];

        foreach ($lookups as $lookup) {
            if ($lookup->wikiUserId > 0 && $lookup->pageId > 0) {
                $unique[$lookup->key()] = $lookup;
            }
        }

        if ($unique === []) {
            return [];
        }

        $ratings = [];
        $invalidCount = 0;
        $batchSize = min(
            500,
            max(1, (int) config('maddraxikon.ratings.source_batch_size', 100))
        );

        foreach (array_chunk(array_values($unique), $batchSize) as $batch) {
            $rows = DB::connection('maddraxikon')
                ->table('vote as votes')
                ->whereNotNull('votes.vote_user_id')
                ->where(function (Builder $query) use ($batch): void {
                    foreach ($batch as $lookup) {
                        $query->orWhere(function (Builder $pair) use ($lookup): void {
                            $pair
                                ->where('votes.vote_user_id', $lookup->wikiUserId)
                                ->where('votes.vote_page_id', $lookup->pageId);
                        });
                    }
                })
                ->orderBy('votes.vote_date')
                ->orderBy('votes.vote_id')
                ->get([
                    'votes.vote_id',
                    'votes.vote_page_id',
                    'votes.vote_value',
                    'votes.vote_date',
                    'votes.vote_user_id',
                ]);

            foreach ($rows as $row) {
                $wikiUserId = (int) $row->vote_user_id;
                $pageId = (int) $row->vote_page_id;
                $rating = filter_var(
                    $row->vote_value,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1, 'max_range' => 5]],
                );
                $key = MaddraxikonRatingLookup::makeKey($wikiUserId, $pageId);

                if ($rating === false || ! isset($unique[$key])) {
                    $invalidCount++;

                    continue;
                }

                $ratings[$key] = new MaddraxikonRatingData(
                    wikiUserId: $wikiUserId,
                    pageId: $pageId,
                    rating: $rating,
                    votedAt: $this->parseVoteDate($row->vote_date),
                );
            }
        }

        if ($invalidCount > 0) {
            Log::warning('Maddraxikon-Bewertungssync hat ungültige Quelldatensätze verworfen.', [
                'invalid_count' => $invalidCount,
            ]);
        }

        return $ratings;
    }

    private function parseVoteDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'UTC');
        } catch (Throwable) {
            return null;
        }
    }
}
