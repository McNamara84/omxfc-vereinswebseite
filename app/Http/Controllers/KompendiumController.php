<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class KompendiumController extends Controller
{
    /** Mindest-Punktzahl für die Suche */
    private const REQUIRED_POINTS = 100;

    /**
     * GET /kompendium  – Übersichtsseite
     */
    public function index(Request $request): View
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $currentTeam = $user->currentTeam;
        $userPoints = $currentTeam
            ? $user->totalPointsForTeam($currentTeam)
            : 0;

        $showSearch = $userPoints >= self::REQUIRED_POINTS;

        return view('pages.kompendium', [
            'userPoints' => $userPoints,
            'showSearch' => $showSearch,
            'required' => self::REQUIRED_POINTS,
        ]);
    }

    /**
     * GET /kompendium/search  (AJAX)
     */
    public function search(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $currentTeam = $user->currentTeam;
        $userPoints = $currentTeam
            ? $user->totalPointsForTeam($currentTeam)
            : 0;

        if ($userPoints < self::REQUIRED_POINTS) {
            return response()->json([
                'message' => "Mindestens "
                    . self::REQUIRED_POINTS
                    . " Punkte erforderlich (du hast $userPoints)."
            ], 403);
        }

        /* ---------- Validierung ---------- */
        $request->validate([
            'q' => 'required|string|min:2',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = mb_strtolower($request->input('q'));
        $page = (int) $request->input('page', 1);
        $perPage = 5;
        $snippetsPerFile = config('kompendium.snippets_per_novel', 10) ?: 10;
        $radius = 200;

        /* ---------- Datei-Scan (gecached) ---------- */
        $allHits = Cache::remember(
            "kompendium:{$query}",
            60,
            function () use ($query, $snippetsPerFile, $radius) {

                $files = Storage::disk('private')->allFiles('romane');

                $hits = [];

                foreach ($files as $path) {
                    if (!Str::endsWith($path, '.txt'))
                        continue;

                    $text = Storage::disk('private')->get($path);
                    $lower = mb_strtolower($text);

                    if (!Str::contains($lower, $query))
                        continue;

                    [$cycleSlug, $romanNr, $title] = $this->extractMetaFromPath($path);

                    $cycleName = Str::of($cycleSlug)
                        ->after('-')          // „Meeraka“
                        ->replace('-', ' ')   // falls mehrere Wörter
                        ->title() . '-Zyklus';

                    /* --- Snippets sammeln --- */
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

                    $hits[] = [
                        'cycle'    => $cycleName,
                        'romanNr' => $romanNr,
                        'title' => $title,
                        'snippets' => $snippets,
                    ];
                }

                usort($hits, fn($a, $b) => strnatcmp($a['romanNr'], $b['romanNr']));
                return $hits;
            }
        );

        /* ---------- Pagination ---------- */
        $paginator = new LengthAwarePaginator(
            array_slice($allHits, ($page - 1) * $perPage, $perPage),
            count($allHits),
            $perPage,
            $page
        );

        return response()->json([
            'data' => $paginator->items(),
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
        ]);
    }

    /**
     * Zerlegt Pfad: romane/01-Euree/001 - Titel.txt
     */
    private function extractMetaFromPath(string $path): array
    {
        $parts = preg_split('/[\\\\\/]+/', $path);
        $cycleSlug = $parts[1] ?? 'unknown';

        [$romanNr, $title] = explode(' - ', pathinfo($path, PATHINFO_FILENAME), 2);
        return [$cycleSlug, $romanNr, $title];
    }
}
