<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookOfferRequest;
use App\Http\Requests\StoreBookRequestRequest;
use App\Http\Requests\StoreBundleOfferRequest;
use App\Http\Requests\UpdateBookOfferRequest;
use App\Http\Requests\UpdateBundleOfferRequest;
use App\Models\Activity;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Services\Romantausch\BookPhotoService;
use App\Services\Romantausch\BundleService;
use App\Services\Romantausch\SwapMatchingService;
use App\Services\RomantauschInfoProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Controller für die Romantauschbörse.
 *
 * Verwaltet den Austausch von Maddrax-Romanen zwischen Mitgliedern.
 * Unterstützt Einzelangebote, Stapel-Angebote (Bundles) und Gesuche.
 */
class RomantauschController extends Controller
{
    public function __construct(
        private readonly RomantauschInfoProvider $romantauschInfoProvider,
        private readonly BookPhotoService $photoService,
        private readonly SwapMatchingService $matchingService,
        private readonly BundleService $bundleService,
    ) {}

    /**
     * Übersicht der Romantauschbörse.
     */
    public function index()
    {
        $userId = Auth::id();

        $allOffers = BookOffer::with('user')
            ->where('completed', false)
            ->doesntHave('swap')
            ->get();

        $bundledOffers = $allOffers->filter(fn ($offer) => $offer->bundle_id !== null)->groupBy('bundle_id');
        $singleOffers = $allOffers->filter(fn ($offer) => $offer->bundle_id === null);

        $requests = BookRequest::with('user')->where('completed', false)->doesntHave('swap')->get();

        $ownOffers = collect();
        $ownRequests = collect();

        if ($userId) {
            $ownOffers = $allOffers
                ->filter(fn (BookOffer $offer) => (int) $offer->user_id === (int) $userId)
                ->keyBy(fn (BookOffer $offer) => $this->matchingService->buildBookKey($offer->series, (int) $offer->book_number));

            $ownRequests = $requests
                ->filter(fn (BookRequest $request) => (int) $request->user_id === (int) $userId)
                ->keyBy(fn (BookRequest $request) => $this->matchingService->buildBookKey($request->series, (int) $request->book_number));
        }

        $bundles = $this->buildBundlesWithMatchInfo($bundledOffers, $userId, $ownRequests);
        $this->enrichOffersWithMatchInfo($singleOffers, $userId, $ownRequests);
        $this->enrichRequestsWithMatchInfo($requests, $userId, $ownOffers);

        $activeSwaps = BookSwap::with(['offer.user', 'request.user'])
            ->whereNull('completed_at')
            ->where(function ($query) use ($userId) {
                $query->whereHas('offer', fn ($q) => $q->where('user_id', $userId))
                    ->orWhereHas('request', fn ($q) => $q->where('user_id', $userId));
            })->get();

        $completedSwaps = BookSwap::with(['offer.user', 'request.user'])->whereNotNull('completed_at')->latest()->get();
        $romantauschInfo = $this->romantauschInfoProvider->getInfo();
        $offers = $singleOffers;

        return view('romantausch.index', compact('offers', 'bundles', 'requests', 'activeSwaps', 'completedSwaps', 'romantauschInfo'));
    }

    /**
     * Baut Bundle-Daten mit Match-Informationen auf.
     */
    private function buildBundlesWithMatchInfo($bundledOffers, $userId, $ownRequests)
    {
        return $bundledOffers->map(function ($offers, $bundleId) use ($userId, $ownRequests) {
            $firstOffer = $offers->first();
            $matchingCount = 0;
            $matchingOffers = collect();

            if ($userId && (int) $firstOffer->user_id !== (int) $userId) {
                foreach ($offers as $offer) {
                    $bookKey = $this->matchingService->buildBookKey($offer->series, (int) $offer->book_number);
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
                'book_numbers_display' => $this->bundleService->formatBookNumbersRange($offers),
                'created_at' => $firstOffer->created_at,
            ];
        })->values();
    }

    /**
     * Reichert Einzelangebote mit Match-Info an.
     */
    private function enrichOffersWithMatchInfo($offers, $userId, $ownRequests): void
    {
        $offers->each(function (BookOffer $offer) use ($userId, $ownRequests) {
            $offer->matches_user_request = false;

            if (! $userId || (int) $offer->user_id === (int) $userId) {
                return;
            }

            $bookKey = $this->matchingService->buildBookKey($offer->series, (int) $offer->book_number);
            $offer->matches_user_request = $ownRequests->has($bookKey);
        });
    }

    /**
     * Reichert Gesuche mit Match-Info an.
     */
    private function enrichRequestsWithMatchInfo($requests, $userId, $ownOffers): void
    {
        $requests->each(function (BookRequest $request) use ($userId, $ownOffers) {
            $request->matches_user_offer = false;

            if (! $userId || (int) $request->user_id === (int) $userId) {
                return;
            }

            $bookKey = $this->matchingService->buildBookKey($request->series, (int) $request->book_number);
            $request->matches_user_offer = $ownOffers->has($bookKey);
        });
    }

    // ========== Einzelangebote ==========

    /**
     * Formular für Angebot erstellen.
     */
    public function createOffer()
    {
        $typeValues = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = StoreBookOfferRequest::ALLOWED_TYPES;

        return view('romantausch.create_offer', compact('books', 'types'));
    }

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

        $offer = BookOffer::create([
            'user_id' => Auth::id(),
            'series' => $validated['series'],
            'book_number' => $validated['book_number'],
            'book_title' => $book->title,
            'condition' => $validated['condition'],
            'photos' => $photoPaths,
        ]);

        $this->awardPointsIfMilestone();
        $this->matchingService->matchSwap($offer, 'offer');
        $this->createOfferActivity($offer);

        return redirect()->route('romantausch.index')->with('success', 'Angebot erstellt.');
    }

    /**
     * Bearbeiten eines Einzelangebots.
     */
    public function editOffer(BookOffer $offer)
    {
        $this->authorize('update', $offer);

        if ($offer->completed || $offer->swap) {
            return redirect()->route('romantausch.index')
                ->with('error', 'Angebote in laufenden oder abgeschlossenen Tauschaktionen können nicht bearbeitet werden.');
        }

        $typeValues = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = StoreBookOfferRequest::ALLOWED_TYPES;

        return view('romantausch.edit_offer', compact('books', 'types', 'offer'));
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
     * Formular für Gesuch erstellen.
     */
    public function createRequest()
    {
        $typeValues = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = StoreBookOfferRequest::ALLOWED_TYPES;

        return view('romantausch.create_request', compact('books', 'types'));
    }

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

        $bookRequest = BookRequest::create([
            'user_id' => Auth::id(),
            'series' => $validated['series'],
            'book_number' => $validated['book_number'],
            'book_title' => $book->title,
            'condition' => $validated['condition'],
        ]);

        $this->createRequestActivity($bookRequest);
        $this->matchingService->matchSwap($bookRequest, 'request');

        return redirect()->route('romantausch.index')->with('success', 'Gesuch erstellt.');
    }

    /**
     * Bearbeiten eines Gesuchs.
     */
    public function editRequest(BookRequest $bookRequest)
    {
        $this->authorize('update', $bookRequest);

        if ($bookRequest->completed || $bookRequest->swap) {
            return redirect()->route('romantausch.index')
                ->with('error', 'Gesuche in laufenden oder abgeschlossenen Tauschaktionen können nicht bearbeitet werden.');
        }

        $typeValues = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = StoreBookOfferRequest::ALLOWED_TYPES;
        $requestModel = $bookRequest;

        return view('romantausch.edit_request', compact('books', 'types', 'requestModel'));
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
     * Formular für Stapel-Angebot erstellen.
     */
    public function createBundleOffer()
    {
        $typeValues = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = StoreBookOfferRequest::ALLOWED_TYPES;

        return view('romantausch.create_bundle_offer', compact('books', 'types'));
    }

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
     * Zeigt das Bearbeitungsformular für einen Stapel.
     */
    public function editBundle(string $bundleId)
    {
        $offers = BookOffer::where('bundle_id', $bundleId)
            ->where('user_id', Auth::id())
            ->orderBy('book_number')
            ->get();

        if ($offers->isEmpty()) {
            abort(404);
        }

        $this->authorize('update', $offers->first());

        if ($this->bundleService->bundleHasActiveSwaps($bundleId, Auth::id())) {
            return redirect()->route('romantausch.index')
                ->with('error', 'Stapel mit laufenden Tauschaktionen können nicht bearbeitet werden.');
        }

        $typeValues = array_map(fn ($type) => $type->value, StoreBookOfferRequest::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = StoreBookOfferRequest::ALLOWED_TYPES;
        $bookNumbersString = $this->bundleService->formatBookNumbersRange($offers);

        return view('romantausch.edit_bundle', compact('offers', 'books', 'types', 'bundleId', 'bookNumbersString'));
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

        $this->bundleService->updateBundle(
            $bundleId,
            $newBookNumbers,
            $validated['condition'],
            $validated['condition_max'] ?? null,
            $photoResult['photos'],
            $photoResult['deleted'],
            Auth::id()
        );

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

    /**
     * Detailansicht eines Angebots.
     */
    public function showOffer(BookOffer $offer)
    {
        $swap = $offer->swap;
        $user = Auth::user();

        $isOwner = $user->id === $offer->user_id;
        $isSwapPartner = $swap && $swap->request !== null && $user->id === $swap->request->user_id;

        abort_unless($isOwner || $isSwapPartner, 403);

        return view('romantausch.show_offer', compact('offer'));
    }

    // ========== Hilfsmethoden ==========

    /**
     * Vergibt Punkte wenn ein Meilenstein erreicht wird (alle 10 Angebote).
     */
    private function awardPointsIfMilestone(): void
    {
        $offerCount = BookOffer::where('user_id', Auth::id())->count();
        if ($offerCount % 10 === 0) {
            $points = BaxxEarningRule::getPointsFor('romantausch_offer');
            if ($points > 0) {
                Auth::user()->incrementTeamPoints($points);
            }
        }
    }

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
