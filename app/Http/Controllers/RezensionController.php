<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Mail\NewReviewNotification;
use Illuminate\Support\Facades\Mail;

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

        $user = Auth::user();
        $teamId = $this->memberTeam()->id;

        $books = Book::withCount('reviews')
            ->withExists(['reviews as has_review' => function ($query) use ($user, $teamId) {
                $query->where('team_id', $teamId)
                    ->where('user_id', $user->id);
            }])
            ->orderBy('roman_number')
            ->get();

        $jsonPath = storage_path('app/private/maddrax.json');
        if (!is_readable($jsonPath)) {
            abort(500, 'Die Maddrax-Datei wurde nicht gefunden.');
        }

        $romanData = collect(json_decode(file_get_contents($jsonPath), true));
        $cycleMap = $romanData->pluck('zyklus', 'nummer');

        $books->each(function ($book) use ($cycleMap) {
            $book->cycle = $cycleMap[$book->roman_number] ?? 'Unbekannt';
        });

        $booksByCycle = $books->sortByDesc('roman_number')->groupBy('cycle');

        return view('reviews.index', compact('booksByCycle'));
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
            $reviews = $book->reviews()
                ->with(['user', 'comments' => function ($query) {
                    $query->with(['user', 'children.user'])->orderBy('created_at');
                }])
                ->get();
            return view('reviews.show', compact('book', 'reviews', 'role'));
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

        // Nur Mitglieder, Vorstand, Kassenwart oder Admin dürfen eine neue anlegen
        if (!in_array($role, ['Mitglied', 'Vorstand', 'Kassenwart', 'Admin'], true)) {
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

        if ($hasOwn || !in_array($role, ['Mitglied', 'Vorstand', 'Kassenwart', 'Admin'], true)) {
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

        // Award one Baxx for every tenth review of the member
        $reviewCount = Review::where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->count();
        if ($reviewCount % 10 === 0) {
            $user->incrementTeamPoints();
        }

        // Autoren des Romans über neue Rezension informieren
        $authorNames = array_map('trim', explode(',', $book->author));
        $authors = User::whereIn('name', $authorNames)->get();
        foreach ($authors as $author) {
            if ($author->notify_new_review) {
                Mail::to($author->email)
                    ->send(new NewReviewNotification($review, $author));
            }
        }

        return redirect()
            ->route('reviews.show', $book)
            ->with('success', 'Rezension erfolgreich erstellt.');
    }

    /**
     * Formular zum Bearbeiten einer Rezension.
     */
    public function edit(Review $review)
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();

        if ($review->user_id === $user->id || in_array($role, ['Vorstand', 'Admin'], true)) {
            return view('reviews.edit', compact('review'));
        }

        abort(403);
    }

    /**
     * Aktualisieren einer Rezension.
     */
    public function update(Request $request, Review $review)
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();

        if ($review->user_id === $user->id || in_array($role, ['Vorstand', 'Admin'], true)) {
            $data = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string|min:140',
            ]);

            $review->update($data);

            return redirect()
                ->route('reviews.show', $review->book)
                ->with('success', 'Rezension erfolgreich aktualisiert.');
        }

        abort(403);
    }

    /**
     * Löschen einer Rezension.
     */
    public function destroy(Review $review)
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();

        if ($review->user_id === $user->id || in_array($role, ['Vorstand', 'Admin'], true)) {
            $review->delete();
            return back()->with('success', 'Rezension gelöscht.');
        }

        abort(403);
    }
}
