<?php

namespace App\Http\Controllers;

use App\Actions\Romantausch\BundleWorkflow;
use App\Actions\Romantausch\OfferWorkflow;
use App\Actions\Romantausch\RequestWorkflow;
use App\Actions\Romantausch\SwapWorkflow;
use App\Http\Requests\StoreBookOfferRequest;
use App\Http\Requests\StoreBookRequestRequest;
use App\Http\Requests\StoreBundleOfferRequest;
use App\Http\Requests\UpdateBookOfferRequest;
use App\Http\Requests\UpdateBundleOfferRequest;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Support\Romantausch\MutationResult;
use App\Support\Romantausch\WorkflowFailure;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RomantauschController extends Controller
{
    public function __construct(private readonly OfferWorkflow $offers, private readonly RequestWorkflow $requests, private readonly BundleWorkflow $bundles, private readonly SwapWorkflow $swaps) {}

    public function storeOffer(StoreBookOfferRequest $request): RedirectResponse
    {
        return $this->mutate(fn () => $this->offers->create($request->user(), $request->validated()), 'Angebot erstellt.');
    }

    public function updateOffer(UpdateBookOfferRequest $request, BookOffer $offer): RedirectResponse
    {
        return $this->mutate(fn () => $this->offers->update($request->user(), $offer->id, $request->validated()), 'Angebot aktualisiert.');
    }

    public function deleteOffer(BookOffer $offer): RedirectResponse
    {
        $this->offers->delete(auth()->user(), $offer->id);

        return redirect()->route('romantausch.index')->with('success', 'Angebot gelöscht.');
    }

    public function storeRequest(StoreBookRequestRequest $request): RedirectResponse
    {
        return $this->mutate(fn () => $this->requests->create($request->user(), $request->validated()), 'Gesuch erstellt.');
    }

    public function updateRequest(Request $request, BookRequest $bookRequest): RedirectResponse
    {
        return $this->mutate(fn () => $this->requests->update($request->user(), $bookRequest->id, $request->all()), 'Gesuch aktualisiert.');
    }

    public function deleteRequest(BookRequest $request): RedirectResponse
    {
        $this->requests->delete(auth()->user(), $request->id);

        return redirect()->route('romantausch.index')->with('success', 'Gesuch gelöscht.');
    }

    public function storeBundleOffer(StoreBundleOfferRequest $request): RedirectResponse
    {
        return $this->mutate(fn () => $this->bundles->create($request->user(), $request->validated()));
    }

    public function updateBundle(UpdateBundleOfferRequest $request, string $bundleId): RedirectResponse
    {
        return $this->mutate(fn () => $this->bundles->update($request->user(), $bundleId, $request->validated()), 'Stapel-Angebot aktualisiert.');
    }

    public function deleteBundle(string $bundleId): RedirectResponse
    {
        $this->bundles->delete(auth()->user(), $bundleId);

        return redirect()->route('romantausch.index')->with('success', 'Stapel-Angebot gelöscht.');
    }

    public function completeSwap(BookOffer $offer, BookRequest $request): RedirectResponse
    {
        $result = $this->swaps->complete(auth()->user(), $offer->id, $request->id);

        return redirect()->route('romantausch.index')->with('success', $result->message());
    }

    public function confirmSwap(BookSwap $swap): RedirectResponse
    {
        $result = $this->swaps->confirm(auth()->user(), $swap->id);

        return redirect()->route('romantausch.index')->with('success', $result->message());
    }

    private function mutate(Closure $action, ?string $success = null): RedirectResponse
    {
        try {
            /** @var MutationResult $result */
            $result = $action();
        } catch (WorkflowFailure $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }
        $response = redirect()->route('romantausch.index')->with('success', $success ?? 'Stapel-Angebot mit '.$result->details['count'].' Romanen erstellt.');

        return $result->warning() ? $response->with('warning', $result->warning()) : $response;
    }
}
