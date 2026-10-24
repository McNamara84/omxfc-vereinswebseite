<?php

namespace App\Services\Romantausch;

use App\Mail\BookSwapMatched;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SwapMatchingService
{
    public function __construct(private readonly MutationLock $lock) {}

    public function matchSwap(Model $model, string $type): void
    {
        $model = $model->newQuery()->find($model->id);
        if (! $model || $model->completed || $model->swap()->exists()) {
            return;
        }
        $candidates = ($type === 'offer' ? BookRequest::query() : BookOffer::query())
            ->where('series', $model->series)->where('book_number', $model->book_number)
            ->where('completed', false)->where('user_id', '!=', $model->user_id)->doesntHave('swap')->orderBy('id')->get();
        $withoutReciprocal = [];
        foreach ($candidates as $candidate) {
            if (isset($withoutReciprocal[$candidate->user_id])) {
                continue;
            }
            $matched = $this->attempt($type === 'offer' ? $model : $candidate, $type === 'offer' ? $candidate : $model);
            if ($matched === true) {
                break;
            }
            // The same owner's other requests cannot supply a missing reciprocal
            // inventory. New writes schedule their own matching after commit.
            if ($matched === null) {
                $withoutReciprocal[$candidate->user_id] = true;
            }
        }
    }

    private function attempt(BookOffer $sourceOffer, BookRequest $sourceRequest): ?bool
    {
        $owners = [$sourceOffer->user_id, $sourceRequest->user_id];

        return $this->lock->run($owners, function () use ($sourceOffer, $sourceRequest): ?bool {
            // Current locking reads avoid a stale REPEATABLE READ snapshot after waiting.
            $offers = BookOffer::where('id', $sourceOffer->id)->orWhere('user_id', $sourceRequest->user_id)->orderBy('id')->lockForUpdate()->get();
            $requests = BookRequest::where('id', $sourceRequest->id)->orWhere('user_id', $sourceOffer->user_id)->orderBy('id')->lockForUpdate()->get();
            $swaps = BookSwap::whereIn('offer_id', $offers->modelKeys())->orWhereIn('request_id', $requests->modelKeys())
                ->orderBy('id')->lockForUpdate()->get();
            $freeOffers = $offers->reject(fn ($offer) => $offer->completed || $swaps->contains('offer_id', $offer->id));
            $freeRequests = $requests->reject(fn ($request) => $request->completed || $swaps->contains('request_id', $request->id));
            $offer = $freeOffers->find($sourceOffer->id);
            $request = $freeRequests->find($sourceRequest->id);
            if (! $offer || ! $request || $offer->user_id === $request->user_id || $offer->series !== $request->series || $offer->book_number !== $request->book_number) {
                return false;
            }
            $wanted = $freeRequests->where('user_id', $offer->user_id)->keyBy(fn ($entry) => $this->buildBookKey($entry->series, $entry->book_number));
            $available = $freeOffers->where('user_id', $request->user_id)->keyBy(fn ($entry) => $this->buildBookKey($entry->series, $entry->book_number));
            $key = $wanted->intersectByKeys($available)->keys()->first();
            if ($key === null) {
                return null;
            }
            $first = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
            $second = BookSwap::create(['offer_id' => $available[$key]->id, 'request_id' => $wanted[$key]->id]);
            DB::afterCommit(function () use ($first, $second, $request, $offer): void {
                $this->notify($first, $request->user_id);
                $this->notify($second, $offer->user_id);
            });

            return true;
        });
    }

    private function notify(BookSwap $swap, int $recipientId): void
    {
        try {
            if ($recipient = User::find($recipientId)) {
                Mail::to($recipient->email)->queue(new BookSwapMatched($swap));
            }
        } catch (\Throwable $error) {
            Log::error('Swap-Benachrichtigung fehlgeschlagen', ['swap_id' => $swap->id, 'recipient_id' => $recipientId, 'error' => $error->getMessage()]);
        }
    }

    public function buildBookKey(string $series, int $bookNumber): string
    {
        return sprintf('%s::%d', $series, $bookNumber);
    }
}
