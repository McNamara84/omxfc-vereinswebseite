<?php

namespace App\Http\Controllers;

use App\Enums\BookType;
use App\Enums\Role;
use App\Http\Controllers\Concerns\MembersTeamAware;
use App\Http\Requests\ReviewRequest;
use App\Mail\NewReviewNotification;
use App\Models\Activity;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use App\Services\MaddraxDataService;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class RezensionController extends Controller
{
    use MembersTeamAware;

    public function __construct(
        private UserRoleService $userRoleService,
        private MaddraxDataService $maddraxDataService,
    ) {}

    protected function getUserRoleService(): UserRoleService
    {
        return $this->userRoleService;
    }

    /**
     * Prepare standardized book query with review counts and user-specific review existence.
     *
     * @param  Builder  $query  Base query to augment.
     * @param  User  $user  Authenticated user for review existence check.
     * @param  int  $teamId  Team identifier used for scoping reviews.
     * @param  string  $direction  Sort direction for roman numbers (asc or desc).
     * @return Collection Books matching the query with review metadata.
     */
    protected function prepareBookQuery(
        Builder $query,
        User $user,
        int $teamId,
        string $direction = 'asc'
    ): Collection {
        return $query->withCount('reviews')
            ->withExists(['reviews as has_review' => function ($query) use ($user, $teamId) {
                $query->where('team_id', $teamId)
                    ->where('user_id', $user->id);
            }])
            ->orderBy('roman_number', $direction)
            ->get();
    }

    /**
     * Übersicht aller Bücher + Rezensionszahl.
     */
    public function index(Request $request)
    {
        $role = $this->authorizeFullMember();

        $user = Auth::user();
        $teamId = $this->memberTeam()->id;

        $applyFilters = function ($query) use ($request) {
            if ($request->filled('roman_number')) {
                $query->where('roman_number', $request->integer('roman_number'));
            }

            if ($request->filled('title')) {
                $query->where('title', 'like', '%'.$request->input('title').'%');
            }

            if ($request->filled('author')) {
                $query->where('author', 'like', '%'.$request->input('author').'%');
            }

            if ($request->input('review_status') === 'with') {
                $query->whereHas('reviews');
            } elseif ($request->input('review_status') === 'without') {
                $query->doesntHave('reviews');
            }
        };

        $novelsQuery = Book::query()->where('type', BookType::MaddraxDieDunkleZukunftDerErde);
        $applyFilters($novelsQuery);

        $hardcoversQuery = Book::query()->where('type', BookType::MaddraxHardcover);
        $applyFilters($hardcoversQuery);

        $missionMarsQuery = Book::query()->where('type', BookType::MissionMars);
        $applyFilters($missionMarsQuery);

        $miniSeries2012Query = Book::query()->where('type', BookType::ZweiTausendZwölfDasJahrDerApokalypse);
        $applyFilters($miniSeries2012Query);

        $volkDerTiefeQuery = Book::query()->where('type', BookType::DasVolkDerTiefe);
        $applyFilters($volkDerTiefeQuery);

        $abenteurerQuery = Book::query()->where('type', BookType::DieAbenteurer);
        $applyFilters($abenteurerQuery);

        $books = $this->prepareBookQuery($novelsQuery, $user, $teamId);

        $hardcovers = $this->prepareBookQuery($hardcoversQuery, $user, $teamId, 'desc');

        $missionMars = $this->prepareBookQuery($missionMarsQuery, $user, $teamId, 'desc');

        $miniSeries2012 = $this->prepareBookQuery($miniSeries2012Query, $user, $teamId, 'desc');

        $volkDerTiefe = $this->prepareBookQuery($volkDerTiefeQuery, $user, $teamId, 'desc');

        $abenteurer = $this->prepareBookQuery($abenteurerQuery, $user, $teamId, 'desc');

        $cycleMap = $this->maddraxDataService->getCycleMap();

        $books->each(function ($book) use ($cycleMap) {
            $book->cycle = $cycleMap[$book->roman_number] ?? 'Unbekannt';
        });

        $existingCycles = $books->pluck('cycle')->unique();

        $preferredCycleOrder = collect([
            'Weltrat',
            'Amraka',
            'Weltenriss',
            'Parallelwelt',
            'Fremdwelt',
            'Zeitsprung',
            'Archivar',
            'Ursprung',
            'Streiter',
            'Schatten',
            'Antarktis',
            'Afra',
            'Ausala',
            'Mars',
            'Wandler',
            "Daa'muren",
            'Kratersee',
            'Expedition',
            'Meeraka',
            'Euree',
        ]);

        // Keep the accordion in publication order while allowing new or unexpected
        // cycles to appear at the end without needing code changes. When adding a
        // new cycle, append it here in descending release order; anything missing
        // falls back to alphabetical placement after the curated list.
        $unlistedCycles = $existingCycles
            ->reject(fn ($cycle) => $preferredCycleOrder->contains($cycle))
            ->sort();

        $cycleOrder = $preferredCycleOrder
            ->filter(fn ($cycle) => $existingCycles->contains($cycle))
            ->concat($unlistedCycles);

        $booksByCycle = $cycleOrder
            ->mapWithKeys(function ($cycle) use ($books) {
                $cycleBooks = $books->where('cycle', $cycle)->sortByDesc('roman_number');

                if ($cycleBooks->isEmpty()) {
                    return [];
                }

                return [$cycle => $cycleBooks];
            });

        return view('reviews.index', [
            'booksByCycle' => $booksByCycle,
            'hardcovers' => $hardcovers,
            'missionMars' => $missionMars,
            'miniSeries2012' => $miniSeries2012,
            'volkDerTiefe' => $volkDerTiefe,
            'abenteurer' => $abenteurer,
            'title' => 'Rezensionen – Offizieller MADDRAX Fanclub e. V.',
            'description' => 'Alle Vereinsrezensionen zu den Maddrax-Romanen im Überblick.',
        ]);
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
        if ($hasOwn || in_array($role, [Role::Ehrenmitglied, Role::Vorstand], true)) {
            $reviews = $book->reviews()
                ->with(['user', 'comments' => function ($query) {
                    $query->with(['user', 'children.user'])->orderBy('created_at');
                }])
                ->get();

            return view('reviews.show', [
                'book' => $book,
                'reviews' => $reviews,
                'role' => $role,
                'title' => 'Rezensionen zu '.$book->title.' – Offizieller MADDRAX Fanclub e. V.',
                'description' => 'Leserrezensionen zum Roman "'.$book->title.'".',
            ]);
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
        if (! in_array($role, [Role::Mitglied, Role::Vorstand, Role::Kassenwart, Role::Admin], true)) {
            abort(403);
        }

        return view('reviews.create', [
            'book' => $book,
            'title' => 'Rezension zu '.$book->title.' verfassen – Offizieller MADDRAX Fanclub e. V.',
            'description' => 'Schreibe deine Rezension zum Roman "'.$book->title.'".',
        ]);
    }

    /**
     * Speichern einer neuen Rezension.
     */
    public function store(ReviewRequest $request, Book $book)
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();
        $teamId = $this->memberTeam()->id;

        $hasOwn = $book->reviews()
            ->where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->exists();

        if ($hasOwn || ! in_array($role, [Role::Mitglied, Role::Vorstand, Role::Kassenwart, Role::Admin], true)) {
            abort(403);
        }

        $data = $request->validated();

        $review = Review::create([
            'team_id' => $teamId,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => $data['title'],
            'content' => $data['content'],
        ]);

        // Award Baxx for every tenth review of the member
        $reviewCount = Review::where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->count();
        if ($reviewCount % 10 === 0) {
            $points = BaxxEarningRule::getPointsFor('rezension');
            if ($points > 0) {
                $user->incrementTeamPoints($points);
            }
        }

        // Autoren des Romans über neue Rezension informieren
        $authorNames = array_map('trim', explode(',', $book->author));
        $authors = User::whereIn('name', $authorNames)->get();
        foreach ($authors as $author) {
            if ($author->notify_new_review) {
                Mail::to($author->email)
                    ->queue(new NewReviewNotification($review, $author));
            }
        }

        Activity::create([
            'user_id' => $user->id,
            'subject_type' => Review::class,
            'subject_id' => $review->id,
        ]);

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

        if ($review->user_id === $user->id || in_array($role, [Role::Vorstand, Role::Admin], true)) {
            return view('reviews.edit', [
                'review' => $review,
                'title' => 'Rezension zu '.$review->book->title.' bearbeiten – Offizieller MADDRAX Fanclub e. V.',
                'description' => 'Überarbeite deine Rezension zum Roman "'.$review->book->title.'".',
            ]);
        }

        abort(403);
    }

    /**
     * Aktualisieren einer Rezension.
     */
    public function update(ReviewRequest $request, Review $review)
    {
        $user = Auth::user();
        $role = $this->getRoleInMemberTeam();

        if ($review->user_id === $user->id || in_array($role, [Role::Vorstand, Role::Admin], true)) {
            $data = $request->validated();

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

        if ($review->user_id === $user->id || in_array($role, [Role::Vorstand, Role::Admin], true)) {
            $review->delete();

            return back()->with('success', 'Rezension gelöscht.');
        }

        abort(403);
    }
}
