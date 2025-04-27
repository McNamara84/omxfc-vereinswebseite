<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;

class RomantauschController extends Controller
{
    // Übersicht
    public function index()
    {
        $offers = BookOffer::with('user')->where('completed', false)->get();
        $requests = BookRequest::with('user')->where('completed', false)->get();
        $completedSwaps = BookSwap::with(['offer.user', 'request.user'])->latest()->get();

        return view('romantausch.index', compact('offers', 'requests', 'completedSwaps'));
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

        BookOffer::create([
            'user_id' => Auth::id(),
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => $validated['book_number'],
            'book_title' => $book['titel'],
            'condition' => $validated['condition'],
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

        BookRequest::create([
            'user_id' => Auth::id(),
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => $validated['book_number'],
            'book_title' => $book['titel'],
            'condition' => $validated['condition'],
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
}
