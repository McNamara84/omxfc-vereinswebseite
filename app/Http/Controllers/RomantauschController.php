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

class RomantauschController extends Controller
{
    private const ALLOWED_TYPES = [
        BookType::MaddraxDieDunkleZukunftDerErde,
        BookType::MaddraxHardcover,
        BookType::MissionMars,
    ];
    public const ALLOWED_PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    // Übersicht
    public function index()
    {
        $offers = BookOffer::with('user')->where('completed', false)->doesntHave('swap')->get();
        $requests = BookRequest::with('user')->where('completed', false)->doesntHave('swap')->get();

        $userId = Auth::id();
        $activeSwaps = BookSwap::with(['offer.user', 'request.user'])
            ->whereNull('completed_at')
            ->where(function ($query) use ($userId) {
                $query->whereHas('offer', fn($q) => $q->where('user_id', $userId))
                      ->orWhereHas('request', fn($q) => $q->where('user_id', $userId));
            })->get();

        $completedSwaps = BookSwap::with(['offer.user', 'request.user'])->whereNotNull('completed_at')->latest()->get();

        return view('romantausch.index', compact('offers', 'requests', 'activeSwaps', 'completedSwaps'));
    }

    // Formular für Angebot erstellen
    public function createOffer()
    {
        $typeValues = array_map(fn ($type) => $type->value, self::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = self::ALLOWED_TYPES;

        return view('romantausch.create_offer', compact('books', 'types'));
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

    // Formular für Gesuch erstellen
    public function createRequest()
    {
        $typeValues = array_map(fn ($type) => $type->value, self::ALLOWED_TYPES);
        $books = Book::whereIn('type', $typeValues)->orderBy('roman_number')->get();
        $types = self::ALLOWED_TYPES;

        return view('romantausch.create_request', compact('books', 'types'));
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
            ->keyBy(fn ($item) => $item->series . '|' . $item->book_number);

        if ($offerOwnerRequests->isEmpty()) {
            return false;
        }

        $requestOwnerOffers = BookOffer::where('user_id', $request->user_id)
            ->where('completed', false)
            ->doesntHave('swap')
            ->get()
            ->keyBy(fn ($item) => $item->series . '|' . $item->book_number);

        if ($requestOwnerOffers->isEmpty()) {
            return false;
        }

        $matchingKey = $offerOwnerRequests->keys()->first(function ($key) use ($requestOwnerOffers) {
            return $requestOwnerOffers->has($key);
        });

        if (!$matchingKey) {
            return false;
        }

        $reciprocalRequest = $offerOwnerRequests->get($matchingKey);
        $reciprocalOffer = $requestOwnerOffers->get($matchingKey);

        if (!$reciprocalRequest || !$reciprocalOffer) {
            return false;
        }

        $firstSwap = null;
        $secondSwap = null;

        DB::transaction(function () use ($offer, $request, $reciprocalOffer, $reciprocalRequest, &$firstSwap, &$secondSwap) {
            $firstSwap = BookSwap::create([
                'offer_id' => $offer->id,
                'request_id' => $request->id,
            ]);

            $secondSwap = BookSwap::create([
                'offer_id' => $reciprocalOffer->id,
                'request_id' => $reciprocalRequest->id,
            ]);
        });

        Mail::to($request->user->email)->queue(new BookSwapMatched($firstSwap));
        Mail::to($reciprocalRequest->user->email)->queue(new BookSwapMatched($secondSwap));

        return true;
    }
}
