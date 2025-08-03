<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use App\Models\RomanExcerpt;

class KompendiumController extends Controller
{
    /** Mindest-Punktzahl für die Suche */
    private const REQUIRED_POINTS = 100;

    /* --------------------------------------------------------------------- */
    /*  GET /kompendium  – Übersichtsseite                                   */
    /* --------------------------------------------------------------------- */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $currentTeam = $user->currentTeam;
        $userPoints = $currentTeam ? $user->totalPointsForTeam($currentTeam) : 0;

        return view('pages.kompendium', [
            'userPoints' => $userPoints,
            'showSearch' => $userPoints >= self::REQUIRED_POINTS,
            'required' => self::REQUIRED_POINTS,
        ]);
    }

    /* --------------------------------------------------------------------- */
    /*  GET /kompendium/suche  (AJAX)                                       */
    /* --------------------------------------------------------------------- */
    public function search(Request $request): JsonResponse
    {
        /* ----- Punkte-Check ------------------------------------------------ */
        $user = Auth::user();
        $currentTeam = $user->currentTeam;
        $userPoints = $currentTeam ? $user->totalPointsForTeam($currentTeam) : 0;

        if ($userPoints < self::REQUIRED_POINTS) {
            return response()->json([
                'message' => "Mindestens " . self::REQUIRED_POINTS . " Punkte erforderlich (du hast $userPoints)."
            ], 403);
        }

        /* ----- Validierung ------------------------------------------------- */
        $request->validate([
            'q' => 'required|string|min:2',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = mb_strtolower($request->input('q'));
        $page = (int) $request->input('page', 1);
        $perPage = 5;
        $snippetsPerFile = config('kompendium.snippets_per_novel', 10) ?: 10;
        $radius = 200;

        /* ------------------------------------------------------------------ */
        /*  SCOUT-SUCHAUFRUF  (RAW)                                           */
        /* ------------------------------------------------------------------ */
        $raw = RomanExcerpt::search($query)->raw();              // kein paginate()
        $total = $raw['hits']['total_hits'] ?? 0;

        $ids = $raw['ids'] ?? [];                               // enthält unsere "path"-Schlüssel
        $ids = array_values($ids);                              // re-indexieren
        $slice = array_slice($ids, ($page - 1) * $perPage, $perPage);

        /* ------------------------------------------------------------------ */
        /*  Treffer in Frontend-Format wandeln                                */
        /* ------------------------------------------------------------------ */
        $hits = [];

        foreach ($slice as $path) {

            [$cycleSlug, $romanNr, $title] = $this->extractMetaFromPath($path);

            /* Original-Text laden → Snippets bilden */
            $text = Storage::disk('private')->get($path);
            $snippets = [];
            $offset = 0;

            while (
                ($pos = mb_stripos($text, $query, $offset)) !== false &&
                count($snippets) < $snippetsPerFile
            ) {
                $start = max($pos - $radius, 0);
                $length = mb_strlen($query) + (2 * $radius);
                $snippet = mb_substr($text, $start, $length);

                $snippet = e($snippet);
                $snippet = preg_replace(
                    '/' . preg_quote($query, '/') . '/iu',
                    '<mark>$0</mark>',
                    $snippet
                );

                $snippets[] = $snippet;
                $offset = $pos + mb_strlen($query);
            }

            $cycleName = Str::of($cycleSlug)->after('-')->replace('-', ' ')->title() . '-Zyklus';

            $hits[] = [
                'cycle' => $cycleName,
                'romanNr' => $romanNr,
                'title' => $title,
                'snippets' => $snippets,
            ];
        }

        /* ------------------------------------------------------------------ */
        /*  Pagination-Objekt für das Frontend                                */
        /* ------------------------------------------------------------------ */
        $paginator = new LengthAwarePaginator(
            $hits,
            $total,
            $perPage,
            $page
        );

        return response()->json([
            'data' => $paginator->items(),
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
        ]);
    }

    /* --------------------------------------------------------------------- */
    /*  Hilfsfunktion: Pfad → Zyklus-Slug, Nummer, Titel                     */
    /* --------------------------------------------------------------------- */
    private function extractMetaFromPath(string $path): array
    {
        $parts = preg_split('/[\\\\\/]+/', $path);
        $cycleSlug = $parts[1] ?? 'unknown';

        [$romanNr, $title] = explode(' - ', pathinfo($path, PATHINFO_FILENAME), 2);
        return [$cycleSlug, $romanNr, $title];
    }
}
