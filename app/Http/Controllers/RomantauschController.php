<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Mail\BookSwapMatched;
use App\Models\Activity;

class RomantauschController extends Controller
{
    // Übersicht
    public function index()
    {
        $offers = BookOffer::with('user')->where('completed', false)->doesntHave('swap')->get();
        $requests = BookRequest::with('user')->where('completed', false)->doesntHave('swap')->get();
        $activeSwaps = BookSwap::with(['offer.user', 'request.user'])->whereNull('completed_at')->get();
        $completedSwaps = BookSwap::with(['offer.user', 'request.user'])->whereNotNull('completed_at')->latest()->get();

        return view('romantausch.index', compact('offers', 'requests', 'activeSwaps', 'completedSwaps'));
    }

    // Formular für Angebot erstellen
    public function createOffer()
    {
        $jsonPath = storage_path('app/private/maddrax.json');

        if (!file_exists($jsonPath)) {
            abort(500, "JSON-Datei wurde nicht gefunden: {$jsonPath}");
        }

        $jsonContent = file_get_contents($jsonPath);
        $books = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            abort(500, 'Fehler beim JSON parsen: ' . json_last_error_msg());
        }

        return view('romantausch.create_offer', compact('books'));
    }

    // Angebot speichern
    public function storeOffer(Request $request)
    {
        $validated = $request->validate([
            'book_number' => 'required|integer',
            'condition' => 'required|string',
        ]);

        $jsonPath = storage_path('app/private/maddrax.json');

        if (!file_exists($jsonPath)) {
            abort(500, "Romandaten konnten nicht geladen werden.");
        }

        $jsonContent = file_get_contents($jsonPath);
        $books = json_decode($jsonContent, true);

        $book = collect($books)->firstWhere('nummer', $validated['book_number']);

        if (!$book) {
            return redirect()->back()->with('error', 'Ausgewählter Roman nicht gefunden.');
        }

        $offer = BookOffer::create([
            'user_id' => Auth::id(),
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => $validated['book_number'],
            'book_title' => $book['titel'],
            'condition' => $validated['condition'],
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
        $jsonPath = storage_path('app/private/maddrax.json');

        if (!file_exists($jsonPath)) {
            abort(500, "JSON-Datei wurde nicht gefunden: {$jsonPath}");
        }

        $jsonContent = file_get_contents($jsonPath);
        $books = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            abort(500, 'Fehler beim JSON parsen: ' . json_last_error_msg());
        }

        return view('romantausch.create_request', compact('books'));
    }

    // Gesuch speichern
    public function storeRequest(Request $request)
    {
        $validated = $request->validate([
            'book_number' => 'required|integer',
            'condition' => 'required|string',
        ]);

        $jsonPath = storage_path('app/private/maddrax.json');

        if (!file_exists($jsonPath)) {
            abort(500, "Romandaten konnten nicht geladen werden.");
        }

        $jsonContent = file_get_contents($jsonPath);
        $books = json_decode($jsonContent, true);

        $book = collect($books)->firstWhere('nummer', $validated['book_number']);

        if (!$book) {
            return redirect()->back()->with('error', 'Ausgewählter Roman nicht gefunden.');
        }

        $requestModel = BookRequest::create([
            'user_id' => Auth::id(),
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => $validated['book_number'],
            'book_title' => $book['titel'],
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

    private function matchSwap(Model $model, string $type): void
    {
        if ($type === 'offer') {
            $match = BookRequest::where('book_number', $model->book_number)
                ->where('completed', false)
                ->doesntHave('swap')
                ->first();
            if ($match) {
                $swap = BookSwap::create([
                    'offer_id' => $model->id,
                    'request_id' => $match->id,
                ]);
                Mail::to($match->user->email)->send(new BookSwapMatched($swap));
            }
        } else {
            $match = BookOffer::where('book_number', $model->book_number)
                ->where('completed', false)
                ->doesntHave('swap')
                ->first();
            if ($match) {
                $swap = BookSwap::create([
                    'offer_id' => $match->id,
                    'request_id' => $model->id,
                ]);
                Mail::to($model->user->email)->send(new BookSwapMatched($swap));
            }
        }
    }
}