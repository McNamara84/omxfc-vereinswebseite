<?php

namespace App\Services\Romantausch;

use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Support\Romantausch\MutationResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CommittedMatching
{
    /** @param iterable<Model> $models */
    public function schedule(iterable $models, MutationResult $result): void
    {
        DB::afterCommit(function () use ($models, $result): void {
            $models = collect($models);
            // A new bundle needs one candidate query, not three queries per book
            // when no opposite entries exist. The matcher still rechecks under locks.
            $candidateKeys = null;
            if ($models->count() > 1) {
                try {
                    $opposite = $models->first() instanceof BookOffer ? BookRequest::query() : BookOffer::query();
                    $candidateKeys = $opposite->whereIn('series', $models->pluck('series')->unique())
                        ->whereIn('book_number', $models->pluck('book_number')->unique())
                        ->where('user_id', '!=', $models->first()->user_id)->where('completed', false)->doesntHave('swap')
                        ->get(['series', 'book_number'])->mapWithKeys(fn ($entry) => [$entry->series.'::'.$entry->book_number => true])->all();
                } catch (Throwable $error) {
                    $result->matchingFailed = true;
                    Log::error('Romantausch gespeichert; Matching fehlgeschlagen.', ['error' => $error->getMessage()]);

                    return;
                }
            }
            foreach ($models as $model) {
                if ($candidateKeys !== null && ! isset($candidateKeys[$model->series.'::'.$model->book_number])) {
                    continue;
                }
                try {
                    app(SwapMatchingService::class)->matchSwap($model, $model instanceof BookOffer ? 'offer' : 'request');
                } catch (Throwable $error) {
                    $result->matchingFailed = true;
                    Log::error('Romantausch gespeichert; Matching fehlgeschlagen.', ['model' => $model::class, 'id' => $model->id, 'error' => $error->getMessage()]);
                }
            }
        });
    }
}
