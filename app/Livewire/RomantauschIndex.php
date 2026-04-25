<?php

namespace App\Livewire;

use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Services\Romantausch\BundleService;
use App\Services\Romantausch\SwapMatchingService;
use App\Services\RomantauschInfoProvider;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RomantauschIndex extends Component
{
    private function matchingService(): SwapMatchingService
    {
        return app(SwapMatchingService::class);
    }

    private function bundleService(): BundleService
    {
        return app(BundleService::class);
    }

    #[Computed]
    public function romantauschInfo(): array
    {
        return app(RomantauschInfoProvider::class)->getInfo();
    }

    #[Computed]
    public function allOffers()
    {
        return BookOffer::with('user')
            ->where('completed', false)
            ->doesntHave('swap')
            ->get();
    }

    #[Computed]
    public function offers()
    {
        return $this->allOffers->filter(fn ($offer) => $offer->bundle_id === null);
    }

    #[Computed]
    public function bundles()
    {
        $userId = Auth::id();
        $bundledOffers = $this->allOffers->filter(fn ($offer) => $offer->bundle_id !== null)->groupBy('bundle_id');

        $ownRequests = $this->ownRequests;

        return $bundledOffers->map(function ($offers, $bundleId) use ($userId, $ownRequests) {
            $firstOffer = $offers->first();
            $matchingCount = 0;
            $matchingOffers = collect();

            if ($userId && (int) $firstOffer->user_id !== (int) $userId) {
                foreach ($offers as $offer) {
                    $bookKey = $this->matchingService()->buildBookKey($offer->series, (int) $offer->book_number);
                    if ($ownRequests->has($bookKey)) {
                        $matchingCount++;
                        $matchingOffers->push($offer);
                    }
                }
            }

            return (object) [
                'bundle_id' => $bundleId,
                'series' => $firstOffer->series,
                'user' => $firstOffer->user,
                'user_id' => $firstOffer->user_id,
                'condition' => $firstOffer->condition,
                'condition_max' => $firstOffer->condition_max,
                'condition_range' => $firstOffer->condition_range,
                'photos' => $firstOffer->photos,
                'offers' => $offers->sortBy('book_number'),
                'total_count' => $offers->count(),
                'matching_count' => $matchingCount,
                'matching_offers' => $matchingOffers,
                'book_numbers_display' => $this->bundleService()->formatBookNumbersRange($offers),
                'created_at' => $firstOffer->created_at,
            ];
        })->values();
    }

    #[Computed]
    public function requests()
    {
        return BookRequest::with('user')->where('completed', false)->doesntHave('swap')->get();
    }

    #[Computed]
    public function ownOffers()
    {
        $userId = Auth::id();
        if (! $userId) {
            return collect();
        }

        return $this->allOffers
            ->filter(fn (BookOffer $offer) => (int) $offer->user_id === (int) $userId)
            ->keyBy(fn (BookOffer $offer) => $this->matchingService()->buildBookKey($offer->series, (int) $offer->book_number));
    }

    #[Computed]
    public function ownRequests()
    {
        $userId = Auth::id();
        if (! $userId) {
            return collect();
        }

        return $this->requests
            ->filter(fn (BookRequest $request) => (int) $request->user_id === (int) $userId)
            ->keyBy(fn (BookRequest $request) => $this->matchingService()->buildBookKey($request->series, (int) $request->book_number));
    }

    #[Computed]
    public function activeSwaps()
    {
        $userId = Auth::id();

        return BookSwap::with(['offer.user', 'request.user'])
            ->whereNull('completed_at')
            ->where(function ($query) use ($userId) {
                $query->whereHas('offer', fn ($q) => $q->where('user_id', $userId))
                    ->orWhereHas('request', fn ($q) => $q->where('user_id', $userId));
            })->get();
    }

    #[Computed]
    public function completedSwaps()
    {
        return BookSwap::with(['offer.user', 'request.user'])->whereNotNull('completed_at')->latest()->get();
    }

    public function offerMatchesRequest(BookOffer $offer): bool
    {
        $userId = Auth::id();
        if (! $userId || (int) $offer->user_id === (int) $userId) {
            return false;
        }

        $bookKey = $this->matchingService()->buildBookKey($offer->series, (int) $offer->book_number);

        return $this->ownRequests->has($bookKey);
    }

    public function requestMatchesOffer(BookRequest $request): bool
    {
        $userId = Auth::id();
        if (! $userId || (int) $request->user_id === (int) $userId) {
            return false;
        }

        $bookKey = $this->matchingService()->buildBookKey($request->series, (int) $request->book_number);

        return $this->ownOffers->has($bookKey);
    }

    public function deleteOffer(int $offerId): void
    {
        $offer = BookOffer::findOrFail($offerId);
        $this->authorize('delete', $offer);
        $offer->delete();

        $this->clearComputedCache();
        $this->dispatch('toast', type: 'success', title: 'Angebot gelöscht.');
    }

    public function deleteRequest(int $requestId): void
    {
        $request = BookRequest::findOrFail($requestId);
        $this->authorize('delete', $request);
        $request->delete();

        $this->clearComputedCache();
        $this->dispatch('toast', type: 'success', title: 'Gesuch gelöscht.');
    }

    public function deleteBundle(string $bundleId): void
    {
        $offers = BookOffer::where('bundle_id', $bundleId)
            ->where('user_id', Auth::id())
            ->get();

        if ($offers->isEmpty()) {
            abort(404);
        }

        $this->authorize('delete', $offers->first());

        $this->bundleService()->deleteBundle($bundleId, Auth::id());

        $this->clearComputedCache();
        $this->dispatch('toast', type: 'success', title: 'Stapel-Angebot gelöscht.');
    }

    public function confirmSwap(int $swapId): void
    {
        $swap = BookSwap::findOrFail($swapId);
        $result = $this->matchingService()->confirmSwap($swap, Auth::user());

        $this->clearComputedCache();

        if ($result['completed']) {
            $this->dispatch('toast', type: 'success', title: 'Tausch abgeschlossen! Beide erhalten 2 Baxx.');
        } else {
            $this->dispatch('toast', type: 'info', title: 'Bestätigung gespeichert.');
        }
    }

    public function completeSwap(int $offerId, int $requestId): void
    {
        $user = Auth::user();
        abort_unless(
            $user && $user->hasAnyRole(\App\Enums\Role::Admin, \App\Enums\Role::Vorstand),
            403
        );

        $offer = BookOffer::findOrFail($offerId);
        $request = BookRequest::findOrFail($requestId);

        $this->matchingService()->completeSwap($offer, $request);

        $this->clearComputedCache();
        $this->dispatch('toast', type: 'success', title: 'Tausch abgeschlossen.');
    }

    private function clearComputedCache(): void
    {
        unset(
            $this->allOffers,
            $this->offers,
            $this->bundles,
            $this->requests,
            $this->ownOffers,
            $this->ownRequests,
            $this->activeSwaps,
            $this->completedSwaps,
        );
    }

    public function render()
    {
        // Enrich offers with match info for the view
        $offers = $this->offers;
        $requests = $this->requests;

        return view('livewire.romantausch-index', [
            'offers' => $offers,
            'bundles' => $this->bundles,
            'requests' => $requests,
            'activeSwaps' => $this->activeSwaps,
            'completedSwaps' => $this->completedSwaps,
            'romantauschInfo' => $this->romantauschInfo,
        ])->layout('layouts.app', ['title' => 'Romantauschbörse']);
    }

    public function placeholder()
    {
        return view('components.skeleton-card', ['cols' => 2, 'rows' => 3]);
    }
}
