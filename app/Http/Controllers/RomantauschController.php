<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Book;
use App\Mail\BookSwapMatched;
use App\Models\Activity;
use App\Enums\BookType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\RomantauschInfoProvider;

class RomantauschController extends Controller
{
    public function __construct(private readonly RomantauschInfoProvider $romantauschInfoProvider)
    {
    }

    private const ALLOWED_TYPES = [
        BookType::MaddraxDieDunkleZukunftDerErde,
        BookType::MaddraxHardcover,
        BookType::MissionMars,
        BookType::DasVolkDerTiefe,
        BookType::ZweiTausendZwölfDasJahrDerApokalypse,
    ];
    public const ALLOWED_PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Maximale Spanne für einen Nummernbereich (z.B. 1-500 erlaubt, 1-501 nicht).
     * WICHTIG: Muss mit dem Frontend-Limit in create_bundle_offer.blade.php und edit_bundle.blade.php übereinstimmen!
     */
    public const MAX_RANGE_SPAN = 500;

    /**
     * Minimale Anzahl Bücher pro Bundle.
     */
    public const MIN_BUNDLE_SIZE = 2;

    /**
     * Maximale Anzahl Bücher pro Bundle (Performance-Limit).
     */
    public const MAX_BUNDLE_SIZE = 200;

    /**
     * Storage-Verzeichnis für Bundle-Fotos.
     */
    public const PHOTO_STORAGE_PATH = 'book-offers';

    /**
     * Zustandswerte in der Reihenfolge von best (0) bis schlechtester (8).
     * Wird für die Validierung des Zustandsbereichs verwendet.
     */
    private const CONDITION_ORDER = ['Z0', 'Z0-1', 'Z1', 'Z1-2', 'Z2', 'Z2-3', 'Z3', 'Z3-4', 'Z4'];

    /**
     * Übersicht der Romantauschbörse.
     *
     * Diese Methode trennt Angebote in zwei Kategorien:
     * - 'bundles': Stapel-Angebote (mehrere Romane mit gemeinsamer bundle_id)
     * - 'offers': Einzelne Angebote (ohne bundle_id)
     *
     * WICHTIG für View-Entwickler:
     * Die $offers Collection enthält NUR Einzelangebote, keine Stapel-Romane.
     * Falls alle Angebote (inkl. Stapel-Romane) benötigt werden, muss die
     * View-Logik entsprechend angepasst werden oder $allOffers verwendet werden.
     *
     * Terminologie in der UI:
     * - "Stapel-Angebote" = Bundles (mit bundle_id)
     * - "Einzelne Angebote" = Einzelne Offers (ohne bundle_id)
     */
    public function index()
    {
        $userId = Auth::id();

        // Alle Angebote laden
        $allOffers = BookOffer::with('user')
            ->where('completed', false)
            ->doesntHave('swap')
            ->get();

        // Stapel gruppieren
        $bundledOffers = $allOffers->filter(fn ($offer) => $offer->bundle_id !== null)->groupBy('bundle_id');
        $singleOffers = $allOffers->filter(fn ($offer) => $offer->bundle_id === null);

        $requests = BookRequest::with('user')->where('completed', false)->doesntHave('swap')->get();

        $ownOffers = collect();
        $ownRequests = collect();

        if ($userId) {
            $ownOffers = $allOffers
                ->filter(fn (BookOffer $offer) => (int) $offer->user_id === (int) $userId)
                ->keyBy(fn (BookOffer $offer) => $this->buildBookKey($offer->series, (int) $offer->book_number));

            $ownRequests = $requests
                ->filter(fn (BookRequest $request) => (int) $request->user_id === (int) $userId)
                ->keyBy(fn (BookRequest $request) => $this->buildBookKey($request->series, (int) $request->book_number));
        }

        // Stapel mit Match-Informationen anreichern
        $bundles = $bundledOffers->map(function ($offers, $bundleId) use ($userId, $ownRequests) {
            $firstOffer = $offers->first();

            $matchingCount = 0;
            $matchingOffers = collect();

            // Nur Matches zählen wenn eingeloggter User nicht der Besitzer ist
            if ($userId && (int) $firstOffer->user_id !== (int) $userId) {
                foreach ($offers as $offer) {
                    $bookKey = $this->buildBookKey($offer->series, (int) $offer->book_number);
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
                'book_numbers_display' => $this->formatBookNumbersRange($offers),
                'created_at' => $firstOffer->created_at,
            ];
        })->values();

        // Einzelangebote mit Match-Info
        $singleOffers->each(function (BookOffer $offer) use ($userId, $ownRequests) {
            $offer->matches_user_request = false;

            if (!$userId || (int) $offer->user_id === (int) $userId) {
                return;
            }

            $bookKey = $this->buildBookKey($offer->series, (int) $offer->book_number);
            $offer->matches_user_request = $ownRequests->has($bookKey);
        });

        $requests->each(function (BookRequest $request) use ($userId, $ownOffers) {
            $request->matches_user_offer = false;

            if (!$userId || (int) $request->user_id === (int) $userId) {
                return;
            }

            $bookKey = $this->buildBookKey($request->series, (int) $request->book_number);
            $request->matches_user_offer = $ownOffers->has($bookKey);
        });

        $activeSwaps = BookSwap::with(['offer.user', 'request.user'])
            ->whereNull('completed_at')
            ->where(function ($query) use ($userId) {
                $query->whereHas('offer', fn($q) => $q->where('user_id', $userId))
                      ->orWhereHas('request', fn($q) => $q->where('user_id', $userId));
            })->get();

        $completedSwaps = BookSwap::with(['offer.user', 'request.user'])->whereNotNull('completed_at')->latest()->get();

        $romantauschInfo = $this->romantauschInfoProvider->getInfo();

        // Für Abwärtskompatibilität: 'offers' enthält nur Einzelangebote
        $offers = $singleOffers;

        return view('romantausch.index', compact('offers', 'bundles', 'requests', 'activeSwaps', 'completedSwaps', 'romantauschInfo'));
    }

    // Formular für Angebot erstellen
    public function createOffer()
    {
        $typeValues = array_map(fn ($type) => $type->value, self::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = self::ALLOWED_TYPES;

        return view('romantausch.create_offer', compact('books', 'types'));
    }

    public function editOffer(BookOffer $offer)
    {
        $this->authorize('update', $offer);

        if ($offer->completed || $offer->swap) {
            return redirect()->route('romantausch.index')->with('error', 'Angebote in laufenden oder abgeschlossenen Tauschaktionen können nicht bearbeitet werden.');
        }

        $typeValues = array_map(fn ($type) => $type->value, self::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = self::ALLOWED_TYPES;

        return view('romantausch.edit_offer', compact('books', 'types', 'offer'));
    }

    // Angebot speichern
    public function storeOffer(Request $request)
    {
        $validated = $request->validate([
            'series' => ['required', Rule::in(array_map(fn ($case) => $case->value, self::ALLOWED_TYPES))],
            'book_number' => 'required|integer',
            'condition' => 'required|string',
            'photos' => 'nullable|array|max:3',
            'photos.*' => 'file|max:2048|mimes:' . implode(',', self::ALLOWED_PHOTO_EXTENSIONS),
        ]);

        $book = Book::where('roman_number', $validated['book_number'])
            ->where('type', $validated['series'])
            ->first();

        if (!$book) {
            return redirect()->back()->with('error', 'Ausgewählter Roman nicht gefunden.');
        }

        $photoPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                try {
                    $extension = strtolower($photo->getClientOriginalExtension());
                    $name = Str::slug(pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME));
                    if ($name === '') {
                        $name = 'photo';
                    }
                    $filename = $name . '-' . Str::uuid() . '.' . $extension;
                    $photoPaths[] = $photo->storeAs('book-offers', $filename, 'public');
                } catch (\Throwable $e) {
                    foreach ($photoPaths as $path) {
                        Storage::disk('public')->delete($path);
                    }
                    return redirect()->back()->withInput()->with('error', 'Foto-Upload fehlgeschlagen. Bitte versuche es erneut.');
                }
            }
        }

        $offer = BookOffer::create([
            'user_id' => Auth::id(),
            'series' => $validated['series'],
            'book_number' => $validated['book_number'],
            'book_title' => $book->title,
            'condition' => $validated['condition'],
            'photos' => $photoPaths,
        ]);

        $offerCount = BookOffer::where('user_id', Auth::id())->count();
        if ($offerCount % 10 === 0) {
            Auth::user()->incrementTeamPoints();
        }

        $this->matchSwap($offer, 'offer');

        Activity::create([
            'user_id' => Auth::id(),
            'subject_type' => BookOffer::class,
            'subject_id' => $offer->id,
        ]);

        return redirect()->route('romantausch.index')->with('success', 'Angebot erstellt.');
    }

    public function updateOffer(Request $request, BookOffer $offer)
    {
        $this->authorize('update', $offer);

        if ($offer->completed || $offer->swap) {
            return redirect()->route('romantausch.index')->with('error', 'Angebote in laufenden oder abgeschlossenen Tauschaktionen können nicht bearbeitet werden.');
        }

        $validator = Validator::make($request->all(), [
            'series' => ['required', Rule::in(array_map(fn ($case) => $case->value, self::ALLOWED_TYPES))],
            'book_number' => 'required|integer',
            'condition' => 'required|string',
            'photos' => 'nullable|array',
            'photos.*' => 'file|max:2048|mimes:' . implode(',', self::ALLOWED_PHOTO_EXTENSIONS),
            'remove_photos' => 'nullable|array',
            'remove_photos.*' => 'string',
        ]);

        $validator->after(function ($validator) use ($offer, $request) {
            $removePhotos = collect($request->input('remove_photos', []));
            $existingPhotos = collect($offer->photos ?? []);
            $remainingCount = $existingPhotos->reject(fn ($path) => $removePhotos->contains($path))->count();
            $newCount = collect($request->file('photos', []))->filter()->count();

            if ($remainingCount + $newCount > 3) {
                $validator->errors()->add('photos', 'Du kannst maximal drei Fotos für ein Angebot speichern.');
            }
        });

        $validated = $validator->validate();

        $book = Book::where('roman_number', $validated['book_number'])
            ->where('type', $validated['series'])
            ->first();

        if (!$book) {
            return redirect()->back()->with('error', 'Ausgewählter Roman nicht gefunden.');
        }

        $removePhotos = collect($request->input('remove_photos', []));
        $existingPhotos = collect($offer->photos ?? []);

        $photosToKeep = $existingPhotos->reject(fn ($path) => $removePhotos->contains($path))->values();

        $removedPhotos = $existingPhotos->diff($photosToKeep);
        foreach ($removedPhotos as $path) {
            Storage::disk('public')->delete($path);
        }

        $newPhotoPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                if (!$photo) {
                    continue;
                }
                try {
                    $extension = strtolower($photo->getClientOriginalExtension());
                    $name = Str::slug(pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME));
                    if ($name === '') {
                        $name = 'photo';
                    }
                    $filename = $name . '-' . Str::uuid() . '.' . $extension;
                    $newPhotoPaths[] = $photo->storeAs('book-offers', $filename, 'public');
                } catch (\Throwable $e) {
                    foreach ($newPhotoPaths as $path) {
                        Storage::disk('public')->delete($path);
                    }

                    return redirect()->back()->withInput()->with('error', 'Foto-Upload fehlgeschlagen. Bitte versuche es erneut.');
                }
            }
        }

        $offer->update([
            'series' => $validated['series'],
            'book_number' => $validated['book_number'],
            'book_title' => $book->title,
            'condition' => $validated['condition'],
            'photos' => array_values(array_merge($photosToKeep->toArray(), $newPhotoPaths)),
        ]);

        $offer->refresh();

        $this->matchSwap($offer, 'offer');

        return redirect()->route('romantausch.index')->with('success', 'Angebot aktualisiert.');
    }

    // Formular für Gesuch erstellen
    public function createRequest()
    {
        $typeValues = array_map(fn ($type) => $type->value, self::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = self::ALLOWED_TYPES;

        return view('romantausch.create_request', compact('books', 'types'));
    }

    public function editRequest(BookRequest $bookRequest)
    {
        $this->authorize('update', $bookRequest);

        if ($bookRequest->completed || $bookRequest->swap) {
            return redirect()->route('romantausch.index')->with('error', 'Gesuche in laufenden oder abgeschlossenen Tauschaktionen können nicht bearbeitet werden.');
        }

        $typeValues = array_map(fn ($type) => $type->value, self::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = self::ALLOWED_TYPES;

        $requestModel = $bookRequest;

        return view('romantausch.edit_request', compact('books', 'types', 'requestModel'));
    }

    // Gesuch speichern
    public function storeRequest(Request $request)
    {
        $validated = $request->validate([
            'series' => ['required', Rule::in(array_map(fn ($case) => $case->value, self::ALLOWED_TYPES))],
            'book_number' => 'required|integer',
            'condition' => 'required|string',
        ]);

        $book = Book::where('roman_number', $validated['book_number'])
            ->where('type', $validated['series'])
            ->first();

        if (!$book) {
            return redirect()->back()->with('error', 'Ausgewählter Roman nicht gefunden.');
        }

        $requestModel = BookRequest::create([
            'user_id' => Auth::id(),
            'series' => $validated['series'],
            'book_number' => $validated['book_number'],
            'book_title' => $book->title,
            'condition' => $validated['condition'],
        ]);
        $this->matchSwap($requestModel, 'request');

        Activity::create([
            'user_id' => Auth::id(),
            'subject_type' => BookRequest::class,
            'subject_id' => $requestModel->id,
        ]);

        return redirect()->route('romantausch.index')->with('success', 'Gesuch erstellt.');
    }

    public function updateRequest(Request $request, BookRequest $bookRequest)
    {
        $this->authorize('update', $bookRequest);

        if ($bookRequest->completed || $bookRequest->swap) {
            return redirect()->route('romantausch.index')->with('error', 'Gesuche in laufenden oder abgeschlossenen Tauschaktionen können nicht bearbeitet werden.');
        }

        $validated = $request->validate([
            'series' => ['required', Rule::in(array_map(fn ($case) => $case->value, self::ALLOWED_TYPES))],
            'book_number' => 'required|integer',
            'condition' => 'required|string',
        ]);

        $book = Book::where('roman_number', $validated['book_number'])
            ->where('type', $validated['series'])
            ->first();

        if (!$book) {
            return redirect()->back()->with('error', 'Ausgewählter Roman nicht gefunden.');
        }

        $bookRequest->update([
            'series' => $validated['series'],
            'book_number' => $validated['book_number'],
            'book_title' => $book->title,
            'condition' => $validated['condition'],
        ]);

        $this->matchSwap($bookRequest, 'request');

        return redirect()->route('romantausch.index')->with('success', 'Gesuch aktualisiert.');
    }

    // Angebot löschen
    public function deleteOffer(BookOffer $offer)
    {
        $this->authorize('delete', $offer);
        $offer->delete();

        return redirect()->route('romantausch.index')->with('success', 'Angebot gelöscht.');
    }

    // Gesuch löschen
    public function deleteRequest(BookRequest $request)
    {
        $this->authorize('delete', $request);
        $request->delete();

        return redirect()->route('romantausch.index')->with('success', 'Gesuch gelöscht.');
    }

    // Tausch abschließen
    public function completeSwap(BookOffer $offer, BookRequest $request)
    {
        BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
            'completed_at' => now(),
        ]);

        $offer->update(['completed' => true]);
        $request->update(['completed' => true]);

        return redirect()->route('romantausch.index')->with('success', 'Tausch abgeschlossen.');
    }

    // Bestätigung eines Tauschs durch einen Nutzer
    public function confirmSwap(BookSwap $swap): RedirectResponse
    {
        $user = Auth::user();

        if ($user->is($swap->offer->user)) {
            $swap->offer_confirmed = true;
        }

        if ($user->is($swap->request->user)) {
            $swap->request_confirmed = true;
        }

        $swap->save();

        if ($swap->offer_confirmed && $swap->request_confirmed && !$swap->completed_at) {
            $swap->completed_at = now();
            $swap->save();

            $swap->offer->update(['completed' => true]);
            $swap->request->update(['completed' => true]);

            $swap->offer->user->incrementTeamPoints(2);
            $swap->request->user->incrementTeamPoints(2);
        }

        return redirect()->route('romantausch.index');
    }

    // Detailansicht eines Angebots, nur für beteiligte Nutzer
    public function showOffer(BookOffer $offer)
    {
        $swap = $offer->swap;
        $user = Auth::user();

        $isOwner = $user->id === $offer->user_id;
        $isSwapPartner = $swap && $swap->request !== null && $user->id === $swap->request->user_id;

        abort_unless($isOwner || $isSwapPartner, 403);

        return view('romantausch.show_offer', compact('offer'));
    }

    private function matchSwap(Model $model, string $type): void
    {
        if ($type === 'offer') {
            $offer = $model;

            $potentialRequests = BookRequest::where('book_number', $offer->book_number)
                ->where('series', $offer->series)
                ->where('completed', false)
                ->where('user_id', '!=', $offer->user_id)
                ->doesntHave('swap')
                ->get();

            foreach ($potentialRequests as $request) {
                if ($this->attemptReciprocalSwap($offer, $request)) {
                    break;
                }
            }
        } else {
            $request = $model;

            $potentialOffers = BookOffer::where('book_number', $request->book_number)
                ->where('series', $request->series)
                ->where('completed', false)
                ->where('user_id', '!=', $request->user_id)
                ->doesntHave('swap')
                ->get();

            foreach ($potentialOffers as $offer) {
                if ($this->attemptReciprocalSwap($offer, $request)) {
                    break;
                }
            }
        }
    }

    private function attemptReciprocalSwap(BookOffer $offer, BookRequest $request): bool
    {
        if ($offer->user_id === $request->user_id) {
            return false;
        }

        $offerOwnerRequests = BookRequest::where('user_id', $offer->user_id)
            ->where('completed', false)
            ->doesntHave('swap')
            ->get()
            ->keyBy(fn ($item) => $this->buildBookKey($item->series, (int) $item->book_number));

        if ($offerOwnerRequests->isEmpty()) {
            return false;
        }

        $requestOwnerOffers = BookOffer::where('user_id', $request->user_id)
            ->where('completed', false)
            ->doesntHave('swap')
            ->get()
            ->keyBy(fn ($item) => $this->buildBookKey($item->series, (int) $item->book_number));

        if ($requestOwnerOffers->isEmpty()) {
            return false;
        }

        $matchingEntries = $offerOwnerRequests->intersectByKeys($requestOwnerOffers);

        if ($matchingEntries->isEmpty()) {
            return false;
        }

        $matchingKey = $matchingEntries->keys()->first();

        $reciprocalRequest = $offerOwnerRequests->get($matchingKey);
        $reciprocalOffer = $requestOwnerOffers->get($matchingKey);

        if (!$reciprocalRequest || !$reciprocalOffer) {
            return false;
        }

        [$firstSwap, $secondSwap] = DB::transaction(function () use ($offer, $request, $reciprocalOffer, $reciprocalRequest) {
            $primarySwap = BookSwap::create([
                'offer_id' => $offer->id,
                'request_id' => $request->id,
            ]);

            $reciprocalSwap = BookSwap::create([
                'offer_id' => $reciprocalOffer->id,
                'request_id' => $reciprocalRequest->id,
            ]);

            return [$primarySwap, $reciprocalSwap];
        });

        Mail::to($request->user->email)->queue(new BookSwapMatched($firstSwap));
        Mail::to($reciprocalRequest->user->email)->queue(new BookSwapMatched($secondSwap));

        return true;
    }

    private function buildBookKey(string $series, int $bookNumber): string
    {
        return sprintf('%s::%d', $series, $bookNumber);
    }

    /**
     * Löscht ein Angebot und dessen zugehörigen Swap, falls vorhanden.
     *
     * Erstellt ein Activity-Log für den betroffenen Nutzer, damit dieser
     * nachvollziehen kann, warum sein Match verschwunden ist.
     *
     * @param BookOffer $offer Das zu löschende Angebot
     * @return void
     */
    private function deleteOfferWithSwap(BookOffer $offer): void
    {
        if ($offer->swap) {
            // Activity-Log für den betroffenen Nutzer dessen Match gelöscht wird.
            //
            // Null-safe: swap->request->user kann theoretisch null sein wenn der
            // User-Account gelöscht wurde bevor der Swap gelöscht wurde (Cascade-Timing).
            // DB-Constraints: book_swaps.request_id → book_requests (CASCADE DELETE),
            // book_requests.user_id → users (CASCADE DELETE). Bei gelöschtem User
            // sollte auch die Request und damit der Swap gelöscht sein.
            //
            // Race-Condition-Handling: Der exists()-Check ist anfällig für TOCTOU
            // (Time-Of-Check-Time-Of-Use) Probleme. Falls der User zwischen Check
            // und Activity::create() gelöscht wird, fängt der try-catch den FK-Fehler ab.
            $affectedUser = $offer->swap->request?->user;
            if ($affectedUser && \App\Models\User::where('id', $affectedUser->id)->exists()) {
                try {
                    Activity::create([
                        'user_id' => $affectedUser->id,
                        'subject_type' => BookRequest::class,
                        'subject_id' => $offer->swap->request_id,
                        'action' => 'match_cancelled_by_offer_owner',
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // FK-Constraint-Verletzung (z.B. User zwischenzeitlich gelöscht).
                    // Activity-Log ist nicht kritisch, daher nur loggen und fortfahren.
                    \Illuminate\Support\Facades\Log::warning('Activity-Log für gelöschten Swap fehlgeschlagen', [
                        'offer_id' => $offer->id,
                        'affected_user_id' => $affectedUser->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $offer->swap->delete();
        }
        $offer->delete();
    }

    /**
     * Parst eine Eingabe wie "1, 5, 7, 12-50, 52" in ein Array von Nummern.
     *
     * Akzeptiert führende Nullen (z.B. "01", "05") und normalisiert sie.
     * Buchnummer 0 ist nicht gültig und wird stillschweigend ignoriert, da
     * Romanhefte bei 1 beginnen. Das Frontend (JavaScript) verhält sich identisch:
     * parseInt("0", 10) > 0 === false, daher wird 0 dort ebenfalls abgelehnt.
     *
     * @return array<int>
     */
    private function parseBookNumbers(string $input): array
    {
        $numbers = [];
        $parts = array_map('trim', explode(',', $input));

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (str_contains($part, '-')) {
                $rangeParts = explode('-', $part, 2);
                // ltrim('0') entfernt führende Nullen ("01" → "1").
                // ?: '0' verhindert leeren String bei Eingabe "0"/"00" (wird zu 0, dann von $start > 0 abgelehnt).
                $startRaw = filter_var(ltrim(trim($rangeParts[0]), '0') ?: '0', FILTER_VALIDATE_INT);
                $endRaw = filter_var(ltrim(trim($rangeParts[1]), '0') ?: '0', FILTER_VALIDATE_INT);

                // filter_var gibt false bei ungültiger Eingabe zurück (z.B. "abc", "", "12.5")
                if ($startRaw === false || $endRaw === false) {
                    continue;
                }

                $start = $startRaw;
                $end = $endRaw;

                if ($start > 0 && $end > 0 && $end >= $start && ($end - $start) <= self::MAX_RANGE_SPAN) {
                    for ($i = $start; $i <= $end; $i++) {
                        $numbers[] = $i;
                    }
                }
            } else {
                // ltrim('0') entfernt führende Nullen; ?: '0' verhindert leeren String bei "0"/"00".
                $num = filter_var(ltrim($part, '0') ?: '0', FILTER_VALIDATE_INT);
                // filter_var gibt false bei ungültiger Eingabe zurück (intval würde 0 liefern)
                if ($num !== false && $num > 0) {
                    $numbers[] = $num;
                }
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * Formatiert eine Sammlung von Angeboten als kompakte Nummernbereiche.
     * z.B. Angebote mit book_number [1,2,3,5,7,8,9] => "1-3, 5, 7-9"
     *
     * @param  \Illuminate\Support\Collection<int, BookOffer>  $offers  Sammlung von BookOffer-Objekten
     * @return string Formatierte Nummernbereiche, durch Komma getrennt
     */
    private function formatBookNumbersRange(\Illuminate\Support\Collection $offers): string
    {
        /** @var array<int> $numbers */
        $numbers = $offers->pluck('book_number')->sort()->values()->toArray();

        if (empty($numbers)) {
            return '';
        }

        $ranges = [];
        $start = $numbers[0];
        $end = $numbers[0];

        for ($i = 1; $i < count($numbers); $i++) {
            if ($numbers[$i] === $end + 1) {
                $end = $numbers[$i];
            } else {
                $ranges[] = $start === $end ? (string) $start : "$start-$end";
                $start = $numbers[$i];
                $end = $numbers[$i];
            }
        }
        $ranges[] = $start === $end ? (string) $start : "$start-$end";

        return implode(', ', $ranges);
    }

    /**
     * Hilfsmethode für Foto-Upload.
     *
     * @todo Cleanup-Job für verwaiste Fotos implementieren.
     *       Falls die Anwendung nach dem Upload aber vor dem DB-Speichern abstürzt,
     *       können Fotos verwaist im Storage liegen. Ein Artisan-Command sollte
     *       Dateien in PHOTO_STORAGE_PATH prüfen, die nicht mehr in der DB referenziert werden.
     *       Implementierung: `php artisan make:command CleanupOrphanedBookOfferPhotos`
     *
     * @return array<string> Array mit Pfaden bei Erfolg
     *
     * @throws \RuntimeException wenn der Upload fehlschlägt
     */
    private function uploadPhotos(Request $request): array
    {
        // Sichere Mapping von MIME-Types zu Extensions (keine ausführbaren Dateien)
        $mimeToExtension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        $photoPaths = [];

        if ($request->hasFile('photos')) {
            $photoIndex = 0;
            foreach ($request->file('photos') as $photo) {
                if (!$photo) {
                    continue;
                }
                $photoIndex++;

                try {
                    // SICHERHEIT: Extension wird vom MIME-Type abgeleitet, nicht vom Client!
                    // Verhindert Polyglot-Angriffe (z.B. Bild mit .php Extension)
                    $mimeType = $photo->getMimeType();
                    $extension = $mimeToExtension[$mimeType] ?? null;

                    if ($extension === null) {
                        // Unbekannter/nicht erlaubter MIME-Type - überspringen
                        \Illuminate\Support\Facades\Log::warning('Foto-Upload: Nicht erlaubter MIME-Type', [
                            'user_id' => Auth::id(),
                            'mime_type' => $mimeType,
                            'original_name' => $photo->getClientOriginalName(),
                        ]);
                        continue;
                    }

                    // Zusätzliche Sicherheitsebene: Prüfe ob Datei echtes Bild ist.
                    // getimagesize() liest tatsächlich die Bild-Header und erkennt
                    // Polyglot-Dateien (z.B. PHP-Code mit gültigem JPEG-Header).
                    // MIME-Types können gefälscht werden, Bildheader weniger leicht.
                    $imageInfo = @getimagesize($photo->getRealPath());
                    if ($imageInfo === false) {
                        \Illuminate\Support\Facades\Log::warning('Foto-Upload: Keine gültige Bilddatei', [
                            'user_id' => Auth::id(),
                            'mime_type' => $mimeType,
                            'original_name' => $photo->getClientOriginalName(),
                        ]);
                        continue;
                    }

                    // Slug des Original-Dateinamens für Lesbarkeit.
                    // Bei reinem nicht-ASCII Input (z.B. chinesische Zeichen) wird der
                    // Slug leer, daher Fallback auf generischen Namen.
                    //
                    // Mehrere Fotos mit gleichem Slug sind kein Problem: Die UUID
                    // garantiert Eindeutigkeit. Der user-facing Teil des Namens
                    // (z.B. "bild-abc123.jpg" und "bild-def456.jpg") könnte bei
                    // vielen Uploads ähnlich aussehen, aber das ist akzeptabel.
                    $name = Str::slug(pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME));
                    if ($name === '') {
                        // Leerer/nicht-ASCII Dateiname: beschreibender Fallback mit Index
                        $name = 'photo-' . $photoIndex;
                    }
                    $filename = $name . '-' . Str::uuid() . '.' . $extension;
                    $photoPaths[] = $photo->storeAs(self::PHOTO_STORAGE_PATH, $filename, 'public');
                } catch (\Throwable $e) {
                    // Bereits hochgeladene Fotos aufräumen.
                    // Falls cleanup selbst fehlschlägt, loggen wir das separat.
                    foreach ($photoPaths as $path) {
                        try {
                            Storage::disk('public')->delete($path);
                        } catch (\Throwable $cleanupError) {
                            \Illuminate\Support\Facades\Log::warning('Foto-Cleanup fehlgeschlagen', [
                                'path' => $path,
                                'error' => $cleanupError->getMessage(),
                            ]);
                        }
                    }

                    // Details loggen, aber generische Meldung an Benutzer.
                    // HINWEIS: user_id wird geloggt für Debugging. In Produktionsumgebungen
                    // mit strengen Datenschutzanforderungen könnte stattdessen ein
                    // gehashter Identifier verwendet werden: hash('sha256', Auth::id())
                    \Illuminate\Support\Facades\Log::error('Foto-Upload fehlgeschlagen', [
                        'user_id' => Auth::id(),
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);

                    throw new \RuntimeException('Foto-Upload fehlgeschlagen.', 0, $e);
                }
            }
        }

        return $photoPaths;
    }

    /**
     * Validiert den Zustandsbereich (condition_max muss >= condition sein).
     *
     * Die Validierung basiert auf CONDITION_ORDER, wo niedrigerer Index = besserer Zustand:
     * - Index 0: Z0 (Druckfrisch) = bester Zustand
     * - Index 8: Z4 (Stark gebraucht) = schlechtester Zustand
     *
     * condition_max muss einen gleichen oder höheren Index als condition haben,
     * d.h. der "Bis"-Zustand muss gleich oder schlechter als der "Von"-Zustand sein.
     *
     * @return string|null Fehlermeldung oder null wenn gültig
     */
    private function validateConditionRange(string $condition, ?string $conditionMax): ?string
    {
        if (empty($conditionMax)) {
            return null;
        }

        // CONDITION_ORDER definiert die Reihenfolge von bester (Index 0) zu schlechtester (Index 8).
        // array_search gibt false zurück wenn der Wert nicht gefunden wird.
        $conditionIndex = array_search($condition, self::CONDITION_ORDER);
        $conditionMaxIndex = array_search($conditionMax, self::CONDITION_ORDER);

        // Explizite Fehlermeldung welcher Wert ungültig ist
        if ($conditionIndex === false) {
            $allowed = implode(', ', self::CONDITION_ORDER);

            return "Unbekannter Zustandswert '{$condition}'. Erlaubt sind: {$allowed}";
        }

        if ($conditionMaxIndex === false) {
            $allowed = implode(', ', self::CONDITION_ORDER);

            return "Unbekannter Zustandswert '{$conditionMax}'. Erlaubt sind: {$allowed}";
        }

        if ($conditionMaxIndex < $conditionIndex) {
            return 'Der "Bis"-Zustand muss gleich oder schlechter als der "Von"-Zustand sein.';
        }

        return null;
    }

    /**
     * Validiert fehlende Buchnummern und gibt eine formatierte Fehlermeldung zurück.
     *
     * @param  array<int>  $requestedNumbers
     * @param  array<int>  $existingNumbers
     * @return string|null Fehlermeldung oder null wenn alle existieren
     */
    private function validateMissingBookNumbers(array $requestedNumbers, array $existingNumbers): ?string
    {
        $missingNumbers = array_diff($requestedNumbers, $existingNumbers);

        if (empty($missingNumbers)) {
            return null;
        }

        $missingList = implode(', ', array_slice($missingNumbers, 0, 10));
        if (count($missingNumbers) > 10) {
            $missingList .= ' ... (' . count($missingNumbers) . ' insgesamt)';
        }

        return $missingList;
    }

    /**
     * Formular für Stapel-Angebot erstellen.
     */
    public function createBundleOffer()
    {
        $typeValues = array_map(fn ($type) => $type->value, self::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = self::ALLOWED_TYPES;

        return view('romantausch.create_bundle_offer', compact('books', 'types'));
    }

    /**
     * Speichert ein Stapel-Angebot (mehrere Romane auf einmal).
     */
    public function storeBundleOffer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'series' => ['required', Rule::in(array_map(fn ($case) => $case->value, self::ALLOWED_TYPES))],
            'book_numbers' => 'required|string',
            'condition' => ['required', 'string', Rule::in(self::CONDITION_ORDER)],
            'condition_max' => ['nullable', 'string', Rule::in(self::CONDITION_ORDER)],
            'photos' => 'nullable|array|max:3',
            'photos.*' => 'file|max:2048|mimes:' . implode(',', self::ALLOWED_PHOTO_EXTENSIONS),
        ]);

        $bookNumbers = $this->parseBookNumbers($validated['book_numbers']);

        if (empty($bookNumbers)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Keine gültigen Roman-Nummern gefunden. Bitte gib Nummern im Format "1-50, 52, 55" ein.');
        }

        if (count($bookNumbers) < self::MIN_BUNDLE_SIZE) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['book_numbers' => 'Ein Stapel-Angebot muss mindestens ' . self::MIN_BUNDLE_SIZE . ' Romane enthalten. Für einzelne Romane nutze bitte das normale Angebot-Formular.']);
        }

        if (count($bookNumbers) > self::MAX_BUNDLE_SIZE) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['book_numbers' => 'Ein Stapel-Angebot darf maximal ' . self::MAX_BUNDLE_SIZE . ' Romane enthalten. Bitte teile dein Angebot in mehrere Stapel auf.']);
        }

        // Validiere Zustandsbereich
        $conditionError = $this->validateConditionRange($validated['condition'], $validated['condition_max'] ?? null);
        if ($conditionError) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['condition_max' => $conditionError]);
        }

        $existingBooks = Book::where('type', $validated['series'])
            ->whereIn('roman_number', $bookNumbers)
            ->get()
            ->keyBy('roman_number');

        $missingList = $this->validateMissingBookNumbers($bookNumbers, $existingBooks->keys()->toArray());
        if ($missingList) {
            // Serie-Name für bessere Kontext-Info in der Fehlermeldung.
            // Hilft Nutzern zu verstehen, falls sie versehentlich die falsche Serie gewählt haben.
            $seriesName = $validated['series'];
            return redirect()->back()
                ->withInput()
                ->with('error', "Folgende Roman-Nummern existieren nicht in der Serie \"{$seriesName}\": {$missingList}");
        }

        try {
            $photoPaths = $this->uploadPhotos($request);
        } catch (\RuntimeException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Foto-Upload fehlgeschlagen. Bitte versuche es erneut.');
        }

        $bundleId = Str::uuid()->toString();

        try {
            $offers = DB::transaction(function () use ($existingBooks, $validated, $bundleId, $photoPaths) {
                $offers = [];

                foreach ($existingBooks as $book) {
                    $offers[] = BookOffer::create([
                        'user_id' => Auth::id(),
                        'bundle_id' => $bundleId,
                        'series' => $validated['series'],
                        'book_number' => $book->roman_number,
                        'book_title' => $book->title,
                        'condition' => $validated['condition'],
                        'condition_max' => $validated['condition_max'] ?? null,
                        'photos' => $photoPaths,
                    ]);
                }

                return $offers;
            });
        } catch (\Throwable $e) {
            // Bei Transaktionsfehler: Hochgeladene Fotos aufräumen
            // um verwaiste Dateien zu vermeiden
            foreach ($photoPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            \Illuminate\Support\Facades\Log::error('Bundle-Erstellung fehlgeschlagen', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Beim Erstellen des Stapel-Angebots ist ein Fehler aufgetreten. Bitte versuche es erneut.');
        }

        $totalOfferCount = BookOffer::where('user_id', Auth::id())->count();
        $previousCount = $totalOfferCount - count($offers);
        $newBaxx = intdiv($totalOfferCount, 10) - intdiv($previousCount, 10);
        if ($newBaxx > 0) {
            Auth::user()->incrementTeamPoints($newBaxx);
        }

        foreach ($offers as $offer) {
            $this->matchSwap($offer, 'offer');
        }

        // Activity-Log: Wir verwenden das erste Angebot als subject_id, da die
        // DB-Spalte NOT NULL ist. Falls dieses Angebot später gelöscht wird,
        // bleibt die Activity-Referenz defekt.
        //
        // Die bundle_id in properties dient als stabiler Identifier für Queries:
        //   Activity::where('properties->bundle_id', $bundleId)
        //
        // BEKANNTE LIMITATION - Orphaned Records:
        // Wenn das erste Angebot gelöscht wird, verweist subject_id auf einen
        // nicht existierenden BookOffer. Queries wie:
        //   Activity::where('subject_id', $offerId)->with('subject')->get()
        // liefern dann null für die subject-Relation.
        //
        // EMPFOHLENE WORKAROUND für Activity-Queries zu Bundles:
        //   Activity::where('action', 'bundle_created')
        //           ->where('properties->bundle_id', $bundleId)
        //           ->get()
        //
        // Alternative Ansätze für zukünftige Refactorings:
        // 1. Separate bundles-Tabelle mit eigener ID für Activity-Tracking
        //    → Vorteil: Saubere Referenzierung, Bundle-Lifecycle getrennt
        //    → Nachteil: Zusätzliche Migration, Model-Erstellung
        // 2. activities.subject_id nullable machen
        //    → Vorteil: Minimale Code-Änderung
        //    → Nachteil: Migration auf bestehenden Daten
        // 3. subject_type 'Bundle' mit bundle_id als subject_id (virtuelles Model)
        //    → Erfordert eigene Resolver-Logik für morph-Relations
        //
        // Aktuell ist das Risiko akzeptabel: Activity-Logs sind informativ,
        // keine kritische Geschäftslogik hängt von der subject_id-Referenz ab.
        Activity::create([
            'user_id' => Auth::id(),
            'subject_type' => BookOffer::class,
            'subject_id' => $offers[0]->id,
            'action' => 'bundle_created',
            'properties' => ['bundle_id' => $bundleId, 'offer_count' => count($offers)],
        ]);

        return redirect()->route('romantausch.index')
            ->with('success', 'Stapel-Angebot mit ' . count($offers) . ' Romanen erstellt.');
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

        $hasActiveSwaps = $offers->contains(fn ($offer) => $offer->swap !== null);
        if ($hasActiveSwaps) {
            return redirect()->route('romantausch.index')
                ->with('error', 'Stapel mit laufenden Tauschaktionen können nicht bearbeitet werden.');
        }

        $typeValues = array_map(fn ($type) => $type->value, self::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = self::ALLOWED_TYPES;

        $bookNumbersString = $this->formatBookNumbersRange($offers);

        return view('romantausch.edit_bundle', compact('offers', 'books', 'types', 'bundleId', 'bookNumbersString'));
    }

    /**
     * Aktualisiert einen Stapel.
     */
    public function updateBundle(Request $request, string $bundleId): RedirectResponse
    {
        $existingOffers = BookOffer::where('bundle_id', $bundleId)
            ->where('user_id', Auth::id())
            ->get();

        if ($existingOffers->isEmpty()) {
            abort(404);
        }

        // Defensive Prüfung: Alle Offers im Bundle müssen dem authentifizierten User gehören.
        // Die WHERE-Klausel oben filtert bereits nach user_id, aber als zusätzliche Absicherung
        // gegen potentielle Race-Conditions oder Datenintegritätsprobleme prüfen wir explizit.
        $authenticatedUserId = Auth::id();
        $foreignOffers = $existingOffers->reject(fn ($offer) => (int) $offer->user_id === (int) $authenticatedUserId);
        if ($foreignOffers->isNotEmpty()) {
            \Illuminate\Support\Facades\Log::warning('Bundle-Update: Fremde Offers gefunden', [
                'bundle_id' => $bundleId,
                'user_id' => $authenticatedUserId,
                'foreign_offer_ids' => $foreignOffers->pluck('id')->toArray(),
            ]);
            abort(403, 'Nicht autorisiert: Bundle enthält fremde Angebote.');
        }

        $this->authorize('update', $existingOffers->first());

        $validated = $request->validate([
            'book_numbers' => 'required|string',
            'condition' => ['required', 'string', Rule::in(self::CONDITION_ORDER)],
            'condition_max' => ['nullable', 'string', Rule::in(self::CONDITION_ORDER)],
            'photos' => 'nullable|array|max:3',
            'photos.*' => 'file|max:2048|mimes:' . implode(',', self::ALLOWED_PHOTO_EXTENSIONS),
            'remove_photos' => 'nullable|array',
            'remove_photos.*' => 'string',
        ]);

        $series = $existingOffers->first()->series;
        $newBookNumbers = $this->parseBookNumbers($validated['book_numbers']);

        if (empty($newBookNumbers)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Keine gültigen Roman-Nummern gefunden.');
        }

        // Validiere Mindest- und Maximalanzahl
        if (count($newBookNumbers) < self::MIN_BUNDLE_SIZE) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['book_numbers' => 'Ein Stapel-Angebot muss mindestens ' . self::MIN_BUNDLE_SIZE . ' Romane enthalten.']);
        }

        if (count($newBookNumbers) > self::MAX_BUNDLE_SIZE) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['book_numbers' => 'Ein Stapel-Angebot darf maximal ' . self::MAX_BUNDLE_SIZE . ' Romane enthalten.']);
        }

        // Validiere Zustandsbereich
        $conditionError = $this->validateConditionRange($validated['condition'], $validated['condition_max'] ?? null);
        if ($conditionError) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['condition_max' => $conditionError]);
        }

        $existingBooks = Book::where('type', $series)
            ->whereIn('roman_number', $newBookNumbers)
            ->get()
            ->keyBy('roman_number');

        $missingList = $this->validateMissingBookNumbers($newBookNumbers, $existingBooks->keys()->toArray());
        if ($missingList) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Folgende Roman-Nummern existieren nicht: ' . $missingList);
        }

        $existingPhotos = collect($existingOffers->first()->photos ?? []);
        $removePhotos = collect($request->input('remove_photos', []));
        $photosToKeep = $existingPhotos->reject(fn ($path) => $removePhotos->contains($path))->values();

        // Validiere dass die zu löschenden Fotos tatsächlich existieren
        $photosToDelete = $removePhotos->filter(fn ($path) => $existingPhotos->contains($path))->values();

        try {
            $newPhotoPaths = $this->uploadPhotos($request);
        } catch (\RuntimeException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Foto-Upload fehlgeschlagen.');
        }

        $allPhotos = array_merge($photosToKeep->toArray(), $newPhotoPaths);

        DB::transaction(function () use ($existingOffers, $existingBooks, $newBookNumbers, $validated, $bundleId, $allPhotos) {
            $currentNumbers = $existingOffers->pluck('book_number')->toArray();

            $toRemove = array_diff($currentNumbers, $newBookNumbers);
            $toAdd = array_diff($newBookNumbers, $currentNumbers);

            // Eine Schleife für Löschen und Aktualisieren
            foreach ($existingOffers as $offer) {
                if (in_array($offer->book_number, $toRemove)) {
                    $this->deleteOfferWithSwap($offer);
                } elseif (in_array($offer->book_number, $newBookNumbers)) {
                    // Angebot aktualisieren
                    $offer->update([
                        'condition' => $validated['condition'],
                        'condition_max' => $validated['condition_max'] ?? null,
                        'photos' => $allPhotos,
                    ]);
                }
            }

            foreach ($toAdd as $bookNumber) {
                $book = $existingBooks->get($bookNumber);
                if ($book) {
                    $newOffer = BookOffer::create([
                        'user_id' => Auth::id(),
                        'bundle_id' => $bundleId,
                        'series' => $book->type->value,
                        'book_number' => $book->roman_number,
                        'book_title' => $book->title,
                        'condition' => $validated['condition'],
                        'condition_max' => $validated['condition_max'] ?? null,
                        'photos' => $allPhotos,
                    ]);

                    $this->matchSwap($newOffer, 'offer');
                }
            }
        });

        // Fotos erst NACH erfolgreicher Transaktion löschen (via afterCommit).
        // Dies verhindert Race Conditions bei gleichzeitigen Anfragen und
        // stellt sicher, dass Fotos nur gelöscht werden wenn die DB-Änderungen
        // tatsächlich committed wurden.
        //
        // LIMITATION: Falls die Anwendung zwischen Transaktions-Commit und
        // afterCommit-Ausführung abstürzt oder terminiert wird, bleiben die
        // alten Fotos als "Orphans" im Storage liegen. Dies ist weniger kritisch
        // als der umgekehrte Fall (Fotos löschen vor Commit → Datenverlust).
        // Für Produktionsumgebungen sollte der Cleanup-Job implementiert werden,
        // der verwaiste Dateien aufräumt (siehe @todo in uploadPhotos).
        DB::afterCommit(function () use ($photosToDelete, $allPhotos) {
            foreach ($photosToDelete as $path) {
                if (!in_array($path, $allPhotos, true)) {
                    Storage::disk('public')->delete($path);
                }
            }
        });

        return redirect()->route('romantausch.index')
            ->with('success', 'Stapel-Angebot aktualisiert.');
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

        $firstOffer = $offers->first();
        $photosToDelete = $firstOffer->photos ?? [];

        DB::transaction(function () use ($offers) {
            foreach ($offers as $offer) {
                $this->deleteOfferWithSwap($offer);
            }
        });

        foreach ($photosToDelete as $path) {
            Storage::disk('public')->delete($path);
        }

        return redirect()->route('romantausch.index')
            ->with('success', 'Stapel-Angebot gelöscht.');
    }
}
