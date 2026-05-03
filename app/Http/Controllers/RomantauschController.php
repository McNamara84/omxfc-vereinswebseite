<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookOfferRequest;
use App\Http\Requests\StoreBookRequestRequest;
use App\Http\Requests\StoreBundleOfferRequest;
use App\Http\Requests\UpdateBookOfferRequest;
use App\Http\Requests\UpdateBundleOfferRequest;
use App\Models\Activity;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Services\Romantausch\BookPhotoService;
use App\Services\Romantausch\BundleService;
use App\Services\Romantausch\RomantauschBaxxService;
use App\Services\Romantausch\SwapMatchingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Controller für die Romantauschbörse.
 *
 * Verwaltet den Austausch von Maddrax-Romanen zwischen Mitgliedern.
 * Unterstützt Einzelangebote, Stapel-Angebote (Bundles) und Gesuche.
 */
class RomantauschController extends Controller
{
    public function __construct(
        private readonly BookPhotoService $photoService,
        private readonly SwapMatchingService $matchingService,
        private readonly BundleService $bundleService,
        private readonly RomantauschBaxxService $baxxService,
    ) {}

    // ========== Einzelangebote ==========

    /**
     * Speichert ein neues Einzelangebot.
     */
    public function storeOffer(StoreBookOfferRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $book = Book::where('roman_number', $validated['book_number'])
            ->where('type', $validated['series'])
            ->first();

        if (! $book) {
            return redirect()->back()->with('error', 'Ausgewählter Roman nicht gefunden.');
        }

        try {
            $photoPaths = $this->photoService->uploadPhotosFromRequest($request);
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', 'Foto-Upload fehlgeschlagen. Bitte versuche es erneut.');
        }

        try {
            $offer = DB::transaction(function () use ($validated, $book, $photoPaths) {
                $offer = BookOffer::create([
                    'user_id' => Auth::id(),
                    'series' => $validated['series'],
                    'book_number' => $validated['book_number'],
                    'book_title' => $book->title,
                    'condition' => $validated['condition'],
                    'photos' => $photoPaths,
                ]);

                $this->baxxService->awardForNewOffers(Auth::id(), 1);
                $this->createOfferActivity($offer);

                return $offer;
            });
        } catch (\Throwable $exception) {
            $this->photoService->deletePhotos($photoPaths);

            if (! $exception instanceof LogicException) {
                report($exception);
            }

            return redirect()->back()->withInput()->with('error', 'Angebot konnte aktuell nicht erstellt werden. Bitte versuche es später erneut.');
        }

        $this->matchingService->matchSwap($offer, 'offer');

        return redirect()->route('romantausch.index')->with('success', 'Angebot erstellt.');
    }

    /**
     * Aktualisiert ein Einzelangebot.
     */
    public function updateOffer(UpdateBookOfferRequest $request, BookOffer $offer): RedirectResponse
    {
        $validated = $request->validated();

        $book = Book::where('roman_number', $validated['book_number'])
            ->where('type', $validated['series'])
            ->first();

        if (! $book) {
            return redirect()->back()->with('error', 'Ausgewählter Roman nicht gefunden.');
        }

        try {
            $photoResult = $this->photoService->updatePhotos(
                $offer->photos ?? [],
                $request->input('remove_photos', []),
                $request
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', 'Foto-Upload fehlgeschlagen. Bitte versuche es erneut.');
        }

        $offer->update([
            'series' => $validated['series'],
            'book_number' => $validated['book_number'],
            'book_title' => $book->title,
            'condition' => $validated['condition'],
            'photos' => $photoResult['photos'],
        ]);

        // Alte Fotos löschen
        $this->photoService->deletePhotos($photoResult['deleted']);

        $offer->refresh();
        $this->matchingService->matchSwap($offer, 'offer');

        return redirect()->route('romantausch.index')->with('success', 'Angebot aktualisiert.');
    }

    /**
     * Löscht ein Einzelangebot.
     */
    public function deleteOffer(BookOffer $offer): RedirectResponse
    {
        $this->authorize('delete', $offer);
        $offer->delete();

        return redirect()->route('romantausch.index')->with('success', 'Angebot gelöscht.');
    }

    // ========== Gesuche ==========

    /**
     * Speichert ein neues Gesuch.
     */
    public function storeRequest(StoreBookRequestRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $book = Book::where('roman_number', $validated['book_number'])
            ->where('type', $validated['series'])
            ->first();

        if (! $book) {
            return redirect()->back()->with('error', 'Ausgewählter Roman nicht gefunden.');
        }

        try {
            $bookRequest = DB::transaction(function () use ($validated, $book) {
                $bookRequest = BookRequest::create([
                    'user_id' => Auth::id(),
                    'series' => $validated['series'],
                    'book_number' => $validated['book_number'],
                    'book_title' => $book->title,
                    'condition' => $validated['condition'],
                ]);

                $this->baxxService->awardForNewRequests(Auth::id());
                $this->createRequestActivity($bookRequest);

                return $bookRequest;
            });
        } catch (LogicException) {
            return redirect()->back()->withInput()->with('error', 'Gesuch konnte aktuell nicht erstellt werden. Bitte versuche es später erneut.');
        }

        $this->matchingService->matchSwap($bookRequest, 'request');

        return redirect()->route('romantausch.index')->with('success', 'Gesuch erstellt.');
    }

    /**
     * Aktualisiert ein Gesuch.
     */
    public function updateRequest(Request $request, BookRequest $bookRequest): RedirectResponse
    {
        $this->authorize('update', $bookRequest);

        if ($bookRequest->completed || $bookRequest->swap) {
            return redirect()->route('romantausch.index')
                ->with('error', 'Gesuche in laufenden oder abgeschlossenen Tauschaktionen können nicht bearbeitet werden.');
        }

        $allowedTypes = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);
        $validated = $request->validate([
            'series' => ['required', Rule::in($allowedTypes)],
            'book_number' => 'required|integer',
            'condition' => 'required|string',
        ]);

        $book = Book::where('roman_number', $validated['book_number'])
            ->where('type', $validated['series'])
            ->first();

        if (! $book) {
            return redirect()->back()->with('error', 'Ausgewählter Roman nicht gefunden.');
        }

        $bookRequest->update([
            'series' => $validated['series'],
            'book_number' => $validated['book_number'],
            'book_title' => $book->title,
            'condition' => $validated['condition'],
        ]);

        $this->matchingService->matchSwap($bookRequest, 'request');

        return redirect()->route('romantausch.index')->with('success', 'Gesuch aktualisiert.');
    }

    /**
     * Löscht ein Gesuch.
     */
    public function deleteRequest(BookRequest $request): RedirectResponse
    {
        $this->authorize('delete', $request);
        $request->delete();

        return redirect()->route('romantausch.index')->with('success', 'Gesuch gelöscht.');
    }

    // ========== Stapel-Angebote (Bundles) ==========

    /**
     * Speichert ein Stapel-Angebot.
     */
    public function storeBundleOffer(StoreBundleOfferRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $bookNumbers = $this->bundleService->parseBookNumbers($validated['book_numbers']);

        // Validierungen
        if (empty($bookNumbers)) {
            return redirect()->back()->withInput()
                ->with('error', 'Keine gültigen Roman-Nummern gefunden. Bitte gib Nummern im Format "1-50, 52, 55" ein.');
        }

        if (count($bookNumbers) < BundleService::MIN_BUNDLE_SIZE) {
            return redirect()->back()->withInput()
                ->withErrors(['book_numbers' => 'Ein Stapel-Angebot muss mindestens '.BundleService::MIN_BUNDLE_SIZE.' Romane enthalten.']);
        }

        if (count($bookNumbers) > BundleService::MAX_BUNDLE_SIZE) {
            return redirect()->back()->withInput()
                ->withErrors(['book_numbers' => 'Ein Stapel-Angebot darf maximal '.BundleService::MAX_BUNDLE_SIZE.' Romane enthalten.']);
        }

        $conditionError = $this->bundleService->validateConditionRange($validated['condition'], $validated['condition_max'] ?? null);
        if ($conditionError) {
            return redirect()->back()->withInput()->withErrors(['condition_max' => $conditionError]);
        }

        $existingBooks = $this->bundleService->getExistingBooks($validated['series'], $bookNumbers);
        $missingList = $this->bundleService->validateMissingBookNumbers($bookNumbers, $existingBooks->keys()->toArray());
        if ($missingList) {
            return redirect()->back()->withInput()
                ->with('error', "Folgende Roman-Nummern existieren nicht in der Serie \"{$validated['series']}\": {$missingList}");
        }

        try {
            $photoPaths = $this->photoService->uploadPhotosFromRequest($request);
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', 'Foto-Upload fehlgeschlagen. Bitte versuche es erneut.');
        }

        try {
            $result = $this->bundleService->createBundle(
                $validated['series'],
                $bookNumbers,
                $validated['condition'],
                $validated['condition_max'] ?? null,
                $photoPaths,
                Auth::id()
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('romantausch.index')
            ->with('success', 'Stapel-Angebot mit '.count($result['offers']).' Romanen erstellt.');
    }

    /**
     * Aktualisiert einen Stapel.
     */
    public function updateBundle(UpdateBundleOfferRequest $request, string $bundleId): RedirectResponse
    {
        $existingOffers = BookOffer::where('bundle_id', $bundleId)
            ->where('user_id', Auth::id())
            ->get();

        if ($existingOffers->isEmpty()) {
            abort(404);
        }

        $this->authorize('update', $existingOffers->first());

        $validated = $request->validated();
        $series = $existingOffers->first()->series;
        $newBookNumbers = $this->bundleService->parseBookNumbers($validated['book_numbers']);

        // Validierungen
        if (empty($newBookNumbers)) {
            return redirect()->back()->withInput()->with('error', 'Keine gültigen Roman-Nummern gefunden.');
        }

        if (count($newBookNumbers) < BundleService::MIN_BUNDLE_SIZE) {
            return redirect()->back()->withInput()
                ->withErrors(['book_numbers' => 'Ein Stapel-Angebot muss mindestens '.BundleService::MIN_BUNDLE_SIZE.' Romane enthalten.']);
        }

        if (count($newBookNumbers) > BundleService::MAX_BUNDLE_SIZE) {
            return redirect()->back()->withInput()
                ->withErrors(['book_numbers' => 'Ein Stapel-Angebot darf maximal '.BundleService::MAX_BUNDLE_SIZE.' Romane enthalten.']);
        }

        $conditionError = $this->bundleService->validateConditionRange($validated['condition'], $validated['condition_max'] ?? null);
        if ($conditionError) {
            return redirect()->back()->withInput()->withErrors(['condition_max' => $conditionError]);
        }

        $existingBooks = $this->bundleService->getExistingBooks($series, $newBookNumbers);
        $missingList = $this->bundleService->validateMissingBookNumbers($newBookNumbers, $existingBooks->keys()->toArray());
        if ($missingList) {
            return redirect()->back()->withInput()->with('error', 'Folgende Roman-Nummern existieren nicht: '.$missingList);
        }

        try {
            $photoResult = $this->photoService->updatePhotos(
                $existingOffers->first()->photos ?? [],
                $request->input('remove_photos', []),
                $request
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', 'Foto-Upload fehlgeschlagen.');
        }

        try {
            $this->bundleService->updateBundle(
                $bundleId,
                $newBookNumbers,
                $validated['condition'],
                $validated['condition_max'] ?? null,
                $photoResult['photos'],
                $photoResult['deleted'],
                Auth::id()
            );
        } catch (\RuntimeException $e) {
            $this->photoService->deletePhotos($photoResult['uploaded']);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('romantausch.index')->with('success', 'Stapel-Angebot aktualisiert.');
    }

    /**
     * Löscht einen kompletten Stapel.
     */
    public function deleteBundle(string $bundleId): RedirectResponse
    {
        $offers = BookOffer::where('bundle_id', $bundleId)
            ->where('user_id', Auth::id())
            ->get();

        if ($offers->isEmpty()) {
            abort(404);
        }

        $this->authorize('delete', $offers->first());

        $this->bundleService->deleteBundle($bundleId, Auth::id());

        return redirect()->route('romantausch.index')->with('success', 'Stapel-Angebot gelöscht.');
    }

    // ========== Tausch-Aktionen ==========

    /**
     * Schließt einen Tausch direkt ab.
     */
    public function completeSwap(BookOffer $offer, BookRequest $request): RedirectResponse
    {
        $this->matchingService->completeSwap($offer, $request);

        return redirect()->route('romantausch.index')->with('success', 'Tausch abgeschlossen.');
    }

    /**
     * Bestätigt einen Tausch durch einen Nutzer.
     */
    public function confirmSwap(BookSwap $swap): RedirectResponse
    {
        $this->matchingService->confirmSwap($swap, Auth::user());

        return redirect()->route('romantausch.index');
    }

    // ========== Hilfsmethoden ==========

    /**
     * Erstellt einen Activity-Log-Eintrag für ein neues Angebot.
     */
    private function createOfferActivity(BookOffer $offer): void
    {
        Activity::create([
            'user_id' => Auth::id(),
            'subject_type' => BookOffer::class,
            'subject_id' => $offer->id,
        ]);
    }

    /**
     * Erstellt einen Activity-Log-Eintrag für ein neues Gesuch.
     */
    private function createRequestActivity(BookRequest $bookRequest): void
    {
        Activity::create([
            'user_id' => Auth::id(),
            'subject_type' => BookRequest::class,
            'subject_id' => $bookRequest->id,
        ]);
    }
}
