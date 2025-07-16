<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class RezensionController extends Controller
{
    /**
     * Liefert das Team „Mitglieder“.
     */
    protected function memberTeam(): Team
    {
        return Team::where('name', 'Mitglieder')->firstOrFail();
    }

    /**
     * Liest die Rolle des eingeloggten Nutzers im Team "Mitglieder" aus der Pivot-Tabelle.
     *
     * @return string|null
     */
    protected function getRoleInMemberTeam(): ?string
    {
        // Stelle sicher, dass es das Team überhaupt gibt:
        $team = Team::where('name', 'Mitglieder')->first();
        if (!$team) {
            return null;
        }

        // Wert direkt aus der Pivot-Tabelle holen:
        return DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('user_id', Auth::id())
            ->value('role');
    }

    /**
     * Übersicht aller Bücher + Rezensionszahl.
     */
    public function index()
    {
        $role = $this->getRoleInMemberTeam();
        if (!in_array($role, ['Mitglied', 'Ehrenmitglied', 'Kassenwart', 'Vorstand', 'Admin'], true)) {
            abort(403);
        }

        $books = Book::withCount('reviews')
            ->orderBy('roman_number')
            ->get();

        return view('reviews.index', compact('books'));
    }

    /**
     * Detailansicht: Alle Rezensionen zu einem Buch.
     */
    public function show(Book $book)
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();

        $hasOwn = $book->reviews()
            ->where('team_id', $this->memberTeam()->id)
            ->where('user_id', $user->id)
            ->exists();

        // Ehrenmitglied & Vorstand dürfen immer sehen, alle anderen nur wenn eigene Rezension existiert
        if ($hasOwn || in_array($role, ['Ehrenmitglied', 'Vorstand'], true)) {
            $reviews = $book->reviews()->with('user')->get();
            return view('reviews.show', compact('book', 'reviews'));
        }

        return redirect()->route('reviews.create', $book);
    }

    /**
     * Formular für neue Rezension.
     */
    public function create(Book $book)
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();

        $hasOwn = $book->reviews()
            ->where('team_id', $this->memberTeam()->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($hasOwn) {
            return redirect()->route('reviews.show', $book);
        }

        // Nur Mitglieder, Kassenwart oder Admin dürfen eine neue anlegen
        if (!in_array($role, ['Mitglied', 'Kassenwart', 'Admin'], true)) {
            abort(403);
        }

        return view('reviews.create', compact('book'));
    }

    /**
     * Speichern einer neuen Rezension.
     */
    public function store(Request $request, Book $book)
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();
        $teamId = $this->memberTeam()->id;

        $hasOwn = $book->reviews()
            ->where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->exists();

        if ($hasOwn || !in_array($role, ['Mitglied', 'Kassenwart', 'Admin'], true)) {
            abort(403);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:140',
        ]);

        $review = Review::create([
            'team_id' => $teamId,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => $data['title'],
            'content' => $data['content'],
        ]);

        return redirect()
            ->route('reviews.show', $book)
            ->with('success', 'Rezension erfolgreich erstellt.');
    }

    /**
     * Löschen einer Rezension (nur Vorstand/Admin).
     */
    public function destroy(Review $review)
    {
        $role = $this->getRoleInMemberTeam();
        if (!in_array($role, ['Vorstand', 'Admin'], true)) {
            abort(403);
        }

        $review->delete();
        return back()->with('success', 'Rezension gelöscht.');
    }
}
