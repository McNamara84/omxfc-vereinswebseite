<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\Review;

class StatistikController extends Controller
{
    /**
     * Zeigt die Statistik-Unterseite.
     *
     * ▸ Card 1: Ø-Bewertung, Gesamt-Stimmen, Ø-Stimmen/Roman  
     * ▸ Card 2: Balkendiagramm „Romane je Autor“ (ab ≥ 1 Punkt)
     */
    public function index(Request $request): View
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $currentTeam = $user->currentTeam;
        $userPoints = $currentTeam ? $user->totalPointsForTeam($currentTeam) : 0;

        // ── JSON einlesen ──────────────────────────────────────────────────────────
        $jsonPath = storage_path('app/private/maddrax.json');
        if (!is_readable($jsonPath)) {
            abort(500, 'Die Maddrax-Datei wurde nicht gefunden.');
        }
        $romane = collect(json_decode(file_get_contents($jsonPath), true));

        // ── Card 1 – Grundstatistiken ──────────────────────────────────────────────
        $averageRating = round($romane->avg('bewertung'), 2);
        $totalVotes = $romane->sum('stimmen');
        $averageVotes = round($totalVotes / max($romane->count(), 1), 2);

        // ── Card 2 – Romane je Autor (inkl. Co-Autor:innen) ────────────────────────
        $authorCounts = $romane
            ->pluck('text')            // jede „text“-Spalte ist ein Array aller Autor:innen
            ->flatten()
            ->map(fn($a) => trim($a))
            ->filter()                 // leere Strings filtern
            ->countBy()                // Anzahl pro Autor
            ->sortDesc();              // nach Häufigkeit absteigend

        // ── Card 3 – Top Teamplayer ─────────────────────────────────────────
        $teamplayerTable = $romane
            ->filter(fn($r) => collect($r['text'])->filter()->count() > 1)
            ->flatMap(fn($r) => collect($r['text'])->map(fn($a) => trim($a)))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->map(fn($count, $author) => [
                'author' => $author,
                'count'  => $count,
            ])
            ->values();

        // ── Card 4 – Top-Autor:innen nach Bewertung ───────────────────────────
        $topAuthorRatings = $romane
            ->flatMap(fn($r) => collect($r['text'])->map(fn($a) => [
                'author' => trim($a),
                'rating' => $r['bewertung'],
            ]))
            ->filter(fn($a) => $a['author'] !== '')
            ->groupBy('author')
            ->map(fn($rows, $author) => [
                'author' => $author,
                'average' => round(collect($rows)->avg('rating'), 2),
            ])
            ->sortByDesc('average')
            ->take(10)
            ->values();

        $romaneSorted = $romane->sort(function ($a, $b) {
            // 1. Kriterium: Ø-Bewertung (absteigend)
            if ($a['bewertung'] !== $b['bewertung']) {
                return $b['bewertung'] <=> $a['bewertung'];
            }
            // 2. Kriterium: Stimmen (absteigend)
            return $b['stimmen'] <=> $a['stimmen'];
        });

        $romaneTable = $romaneSorted->map(fn($r) => [
            'nummer' => $r['nummer'],
            'titel' => $r['titel'],
            'autor' => implode(', ', $r['text']),
            'bewertung' => $r['bewertung'],
            'stimmen' => $r['stimmen'],
        ]);

        // ── Card 5 – Top-Charaktere nach Auftritten ──────────────────────────
        $topCharacters = $romane
            ->flatMap(fn($r) => collect($r['personen'] ?? [])->map(fn($p) => trim($p)))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->map(fn($count, $name) => [
                'name'  => $name,
                'count' => $count,
            ])
            ->values();

        // ── Card 8 – Bewertungen des Euree-Zyklus ───────────────────────
        $eureeCycle = $romane
            ->filter(fn($r) => ($r['nummer'] ?? 0) >= 1 && ($r['nummer'] ?? 0) <= 24)
            ->sortBy('nummer');

        $eureeLabels = $eureeCycle->pluck('nummer');
        $eureeValues = $eureeCycle->pluck('bewertung');

        // ── Card 9 – Bewertungen des Meeraka-Zyklus ──────────────────────
        $meerakaCycle = $romane
            ->filter(fn($r) => ($r['nummer'] ?? 0) >= 25 && ($r['nummer'] ?? 0) <= 49)
            ->sortBy('nummer');

        $meerakaLabels = $meerakaCycle->pluck('nummer');
        $meerakaValues = $meerakaCycle->pluck('bewertung');

        // ── Card 10 – Bewertungen des Expeditions-Zyklus ─────────────────
        $expeditionCycle = $romane
            ->filter(fn($r) => ($r['nummer'] ?? 0) >= 50 && ($r['nummer'] ?? 0) <= 74)
            ->sortBy('nummer');

        $expeditionLabels = $expeditionCycle->pluck('nummer');
        $expeditionValues = $expeditionCycle->pluck('bewertung');

        // ── Card 11 – Bewertungen des Kratersee-Zyklus ───────────────────
        $kraterseeCycle = $romane
            ->filter(fn($r) => ($r['nummer'] ?? 0) >= 75 && ($r['nummer'] ?? 0) <= 99)
            ->sortBy('nummer');

        $kraterseeLabels = $kraterseeCycle->pluck('nummer');
        $kraterseeValues = $kraterseeCycle->pluck('bewertung');

        // ── Card 12 – Bewertungen des Daa'muren-Zyklus ──────────────────
        $daaMurenCycle = $romane
            ->filter(fn($r) => ($r['nummer'] ?? 0) >= 100 && ($r['nummer'] ?? 0) <= 124)
            ->sortBy('nummer');

        $daaMurenLabels = $daaMurenCycle->pluck('nummer');
        $daaMurenValues = $daaMurenCycle->pluck('bewertung');

        // ── Card 13 – Bewertungen des Wandler-Zyklus ─────────────────────
        $wandlerCycle = $romane
            ->filter(fn($r) => ($r['nummer'] ?? 0) >= 125 && ($r['nummer'] ?? 0) <= 149)
            ->sortBy('nummer');

        $wandlerLabels = $wandlerCycle->pluck('nummer');
        $wandlerValues = $wandlerCycle->pluck('bewertung');

        // ── Card 14 – Bewertungen des Mars-Zyklus ─────────────────────
        $marsCycle = $romane
            ->filter(fn($r) => ($r['nummer'] ?? 0) >= 150 && ($r['nummer'] ?? 0) <= 174)
            ->sortBy('nummer');

        $marsLabels = $marsCycle->pluck('nummer');
        $marsValues = $marsCycle->pluck('bewertung');

        // ── Card 15 – Bewertungen des Ausala-Zyklus ───────────────────
        $ausalaCycle = $romane
            ->filter(fn($r) => ($r['nummer'] ?? 0) >= 175 && ($r['nummer'] ?? 0) <= 199)
            ->sortBy('nummer');

        $ausalaLabels = $ausalaCycle->pluck('nummer');
        $ausalaValues = $ausalaCycle->pluck('bewertung');

        // ── Card 16 – Bewertungen des Afra-Zyklus ───────────────────
        $afraCycle = $romane
            ->filter(fn($r) => ($r['nummer'] ?? 0) >= 200 && ($r['nummer'] ?? 0) <= 224)
            ->sortBy('nummer');

        $afraLabels = $afraCycle->pluck('nummer');
        $afraValues = $afraCycle->pluck('bewertung');

        // ── Card 17 – Bewertungen des Antarktis-Zyklus ───────────────────
        $antarktisCycle = $romane
            ->filter(fn($r) => ($r['nummer'] ?? 0) >= 225 && ($r['nummer'] ?? 0) <= 249)
            ->sortBy('nummer');

        $antarktisLabels = $antarktisCycle->pluck('nummer');
        $antarktisValues = $antarktisCycle->pluck('bewertung');

        // ── Card 18 – Bewertungen des Schatten-Zyklus ───────────────────
        $schattenCycle = $romane
            ->filter(fn($r) => ($r['nummer'] ?? 0) >= 250 && ($r['nummer'] ?? 0) <= 275)
            ->sortBy('nummer');

        $schattenLabels = $schattenCycle->pluck('nummer');
        $schattenValues = $schattenCycle->pluck('bewertung');

        // ── Card 7 – Rezensionen unserer Mitglieder ───────────────────────────
        $totalReviews = 0;
        $averageReviewsPerBook = 0;
        $topReviewers = collect();
        $topCommentedReviews = collect();
        $longestReviewAuthor = null;
        $avgCommentsPerReview = 0;
        $mostReviewedBook = null;

        if ($currentTeam) {
            $teamId = $currentTeam->id;

            $totalReviews = Review::where('team_id', $teamId)->count();
            $booksReviewed = Review::where('team_id', $teamId)
                ->distinct('book_id')
                ->count('book_id');
            $averageReviewsPerBook = $booksReviewed > 0 ? round($totalReviews / $booksReviewed, 2) : 0;

            $topReviewers = Review::where('team_id', $teamId)
                ->selectRaw('user_id, COUNT(*) as review_count')
                ->groupBy('user_id')
                ->orderByDesc('review_count')
                ->take(3)
                ->with('user')
                ->get()
                ->map(fn($row) => [
                    'name' => $row->user->name,
                    'count' => $row->review_count,
                ]);

            $topCommentedReviews = Review::where('team_id', $teamId)
                ->withCount('comments')
                ->orderByDesc('comments_count')
                ->take(3)
                ->get()
                ->map(fn($r) => [
                    'title' => $r->title,
                    'comments' => $r->comments_count,
                ]);

            $longest = Review::where('team_id', $teamId)
                ->selectRaw('user_id, AVG(LENGTH(content)) as avg_length')
                ->groupBy('user_id')
                ->orderByDesc('avg_length')
                ->with('user')
                ->first();
            if ($longest) {
                $longestReviewAuthor = [
                    'name' => $longest->user->name,
                    'length' => round($longest->avg_length),
                ];
            }

            $avgCommentsPerReview = round(
                Review::where('team_id', $teamId)
                    ->withCount('comments')
                    ->get()
                    ->avg('comments_count'),
                2
            );

            $mostReviewed = Review::where('team_id', $teamId)
                ->selectRaw('book_id, COUNT(*) as review_count')
                ->groupBy('book_id')
                ->orderByDesc('review_count')
                ->with('book')
                ->first();
            if ($mostReviewed) {
                $mostReviewedBook = [
                    'title' => $mostReviewed->book->title,
                    'count' => $mostReviewed->review_count,
                ];
            }
        }

        return view('statistik.index', [
            'averageRating' => $averageRating,
            'totalVotes' => $totalVotes,
            'averageVotes' => $averageVotes,
            'authorCounts' => $authorCounts,
            'teamplayerTable' => $teamplayerTable,
            'topAuthorRatings' => $topAuthorRatings,
            'topCharacters' => $topCharacters,
            'userPoints' => $userPoints,
            'romaneTable' => $romaneTable,
            'eureeLabels' => $eureeLabels,
            'eureeValues' => $eureeValues,
            'meerakaLabels' => $meerakaLabels,
            'meerakaValues' => $meerakaValues,
            'expeditionLabels' => $expeditionLabels,
            'expeditionValues' => $expeditionValues,
            'kraterseeLabels' => $kraterseeLabels,
            'kraterseeValues' => $kraterseeValues,
            'daaMurenLabels' => $daaMurenLabels,
            'daaMurenValues' => $daaMurenValues,
            'wandlerLabels' => $wandlerLabels,
            'wandlerValues' => $wandlerValues,
            'marsLabels' => $marsLabels,
            'marsValues' => $marsValues,
            'ausalaLabels' => $ausalaLabels,
            'ausalaValues' => $ausalaValues,
            'afraLabels' => $afraLabels,
            'afraValues' => $afraValues,
            'antarktisLabels' => $antarktisLabels,
            'antarktisValues' => $antarktisValues,
            'schattenLabels' => $schattenLabels,
            'schattenValues' => $schattenValues,
            'totalReviews' => $totalReviews,
            'averageReviewsPerBook' => $averageReviewsPerBook,
            'topReviewers' => $topReviewers,
            'topCommentedReviews' => $topCommentedReviews,
            'longestReviewAuthor' => $longestReviewAuthor,
            'avgCommentsPerReview' => $avgCommentsPerReview,
            'mostReviewedBook' => $mostReviewedBook,
        ]);
    }
}
