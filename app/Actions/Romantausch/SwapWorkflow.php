<?php

namespace App\Actions\Romantausch;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\User;
use App\Services\Romantausch\MutationLock;
use App\Services\Romantausch\RomantauschBaxxService;
use App\Support\Romantausch\SwapResult;
use Illuminate\Validation\ValidationException;

final class SwapWorkflow
{
    public function __construct(private readonly MutationLock $lock, private readonly RomantauschBaxxService $baxx) {}

    public function confirm(User $actor, int $id): SwapResult
    {
        $source = BookSwap::with(['offer', 'request'])->findOrFail($id);
        abort_unless(in_array($actor->id, [$source->offer->user_id, $source->request->user_id]), 403);

        return $this->lock->run([$source->offer->user_id, $source->request->user_id], function () use ($actor, $source): SwapResult {
            $offer = BookOffer::lockForUpdate()->findOrFail($source->offer_id);
            $request = BookRequest::lockForUpdate()->findOrFail($source->request_id);
            $swap = BookSwap::lockForUpdate()->findOrFail($source->id);
            abort_unless(in_array($actor->id, [$offer->user_id, $request->user_id]), 403);
            if ($swap->completed_at) {
                return new SwapResult($swap, true, true);
            }
            $swap->{$actor->id === $offer->user_id ? 'offer_confirmed' : 'request_confirmed'} = true;
            $swap->save();

            return $swap->offer_confirmed && $swap->request_confirmed
                ? $this->finish($swap, $offer, $request)
                : new SwapResult($swap, false);
        });
    }

    public function complete(User $actor, int $offerId, int $requestId): SwapResult
    {
        abort_unless($actor->hasAnyMitgliederTeamRole(Role::Admin, Role::Vorstand), 403);
        $sourceOffer = BookOffer::findOrFail($offerId);
        $sourceRequest = BookRequest::findOrFail($requestId);

        return $this->lock->run([$sourceOffer->user_id, $sourceRequest->user_id], function () use ($actor, $offerId, $requestId): SwapResult {
            abort_unless($actor->hasAnyMitgliederTeamRole(Role::Admin, Role::Vorstand), 403);
            $offer = BookOffer::lockForUpdate()->findOrFail($offerId);
            $request = BookRequest::lockForUpdate()->findOrFail($requestId);
            if ($offer->series !== $request->series || $offer->book_number !== $request->book_number || $offer->user_id === $request->user_id) {
                throw ValidationException::withMessages(['swap' => 'Angebot und Gesuch bilden keinen gültigen Tausch.']);
            }
            $swaps = BookSwap::where('offer_id', $offerId)->orWhere('request_id', $requestId)->orderBy('id')->lockForUpdate()->get();
            $swap = $swaps->first(fn ($swap) => $swap->offer_id === $offerId && $swap->request_id === $requestId);
            if ($swaps->count() > ($swap ? 1 : 0)) {
                throw ValidationException::withMessages(['swap' => 'Angebot oder Gesuch ist bereits anderweitig zugeordnet.']);
            }
            if ($swap?->completed_at) {
                return new SwapResult($swap, true, true);
            }
            if ($offer->completed || $request->completed) {
                throw ValidationException::withMessages(['swap' => 'Angebot oder Gesuch ist bereits abgeschlossen.']);
            }
            $swap ??= BookSwap::create(['offer_id' => $offerId, 'request_id' => $requestId]);

            return $this->finish($swap, $offer, $request);
        });
    }

    private function finish(BookSwap $swap, BookOffer $offer, BookRequest $request): SwapResult
    {
        $swap->update(['completed_at' => now()]);
        $offer->update(['completed' => true]);
        $request->update(['completed' => true]);
        $swap->setRelation('offer', $offer)->setRelation('request', $request);
        $awards = $this->baxx->awardForCompletedSwap($swap);
        Activity::create(['user_id' => null, 'subject_type' => BookSwap::class, 'subject_id' => $swap->id, 'action' => 'swap_completed']);

        return new SwapResult($swap, true, false, $awards);
    }
}
