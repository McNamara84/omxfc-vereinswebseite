<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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

        return view('statistik.index', [
            'averageRating' => $averageRating,
            'totalVotes' => $totalVotes,
            'averageVotes' => $averageVotes,
            'authorCounts' => $authorCounts,
            'teamplayerTable' => $teamplayerTable,
            'topAuthorRatings' => $topAuthorRatings,
            'userPoints' => $userPoints,
            'romaneTable' => $romaneTable,
        ]);
    }
}
