<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\KompendiumRoman;
use App\Services\KompendiumSearchService;
use App\Services\KompendiumService;
use App\Services\TeamPointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KompendiumController extends Controller
{
    public function __construct(
        private TeamPointService $teamPointService,
        private KompendiumService $kompendiumService,
        private KompendiumSearchService $searchService
    ) {}

    /** Mindest-Punktzahl für die Suche */
    private const REQUIRED_POINTS = 100;

    /** Regex-Pattern für Pfad-Trennung (Windows-Backslash und Unix-Slash) */
    private const PATH_SEPARATOR_PATTERN = '#[\\\\\/]+#';

    /* --------------------------------------------------------------------- */
    /*  GET /kompendium  – Übersichtsseite */
    /* --------------------------------------------------------------------- */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $userPoints = $this->teamPointService->getUserPoints($user);

        // Indexierte Romane gruppiert laden
        $indexierteRomaneSummary = $this->kompendiumService->getIndexierteRomaneSummary();

        // Prüfen ob User Admin ist
        $istAdmin = $user?->currentTeam?->hasUserWithRole($user, Role::Admin->value) ?? false;

        return view('pages.kompendium', [
            'userPoints' => $userPoints,
            'showSearch' => $userPoints >= self::REQUIRED_POINTS,
            'required' => self::REQUIRED_POINTS,
            'indexierteRomaneSummary' => $indexierteRomaneSummary,
            'istAdmin' => $istAdmin,
        ]);
    }

    /* --------------------------------------------------------------------- */
    /*  GET /kompendium/suche  (AJAX) */
    /* --------------------------------------------------------------------- */
    public function search(Request $request): JsonResponse
    {
        /* ----- Punkte-Check ------------------------------------------------ */
        $user = Auth::user();
        $userPoints = $this->teamPointService->getUserPoints($user);

        if ($userPoints < self::REQUIRED_POINTS) {
            return response()->json([
                'message' => 'Mindestens '.self::REQUIRED_POINTS." Punkte erforderlich (du hast $userPoints).",
            ], 403);
        }

        /* ----- Validierung ------------------------------------------------- */
        $validSerienKeys = array_keys(KompendiumService::SERIEN);

        $request->validate([
            'q' => 'required|string|min:2',
            'page' => 'sometimes|integer|min:1',
            'serien' => 'sometimes|array',
            'serien.*' => ['string', Rule::in($validSerienKeys)],
        ]);

        $query = mb_strtolower($request->input('q'));
        $selectedSerien = $request->input('serien', []);
        $page = (int) $request->input('page', 1);
        $perPage = 5;
        $snippetsPerFile = config('kompendium.snippets_per_novel', 10) ?: 10;
        $radius = 200;

        /* ------------------------------------------------------------------ */
        /*  SCOUT-SUCHAUFRUF  (RAW) */
        /* ------------------------------------------------------------------ */
        $raw = $this->searchService->search($query);

        $ids = $raw['ids'] ?? [];                               // enthält unsere "path"-Schlüssel
        $ids = array_values($ids);                              // re-indexieren

        /* ------------------------------------------------------------------ */
        /*  Serien-Zählung und Filterung (kombiniert für Performance) */
        /* ------------------------------------------------------------------ */
        $serienCounts = [];
        $pathToSerie = [];  // Cache für Serie pro Pfad

        foreach ($ids as $path) {
            $serie = $this->extractSerieFromPath($path);
            $pathToSerie[$path] = $serie;
            $serienCounts[$serie] = ($serienCounts[$serie] ?? 0) + 1;
        }

        // Wenn Serien-Filter gesetzt, nur diese Serien berücksichtigen
        if (! empty($selectedSerien)) {
            $ids = array_values(array_filter($ids, fn ($path) => in_array($pathToSerie[$path], $selectedSerien, true)));
        }

        $total = count($ids);
        $slice = array_slice($ids, ($page - 1) * $perPage, $perPage);

        /* ------------------------------------------------------------------ */
        /*  Treffer in Frontend-Format wandeln */
        /* ------------------------------------------------------------------ */
        $hits = [];

        foreach ($slice as $path) {

            [$serie, $romanNr, $title] = $this->extractMetaFromPath($path);

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
                    '/'.preg_quote($query, '/').'/iu',
                    '<mark>$0</mark>',
                    $snippet
                );

                $snippets[] = $snippet;
                $offset = $pos + mb_strlen($query);
            }

            // Zyklus-Name aus SERIEN-Konstante, Fallback auf formatierten Key
            $cycleName = KompendiumService::SERIEN[$serie] ?? Str::of($serie)->replace('-', ' ')->title();

            $hits[] = [
                'cycle' => $cycleName,
                'romanNr' => $romanNr,
                'title' => $title,
                'serie' => $serie,
                'snippets' => $snippets,
            ];
        }

        /* ------------------------------------------------------------------ */
        /*  Pagination-Objekt für das Frontend */
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
            'serienCounts' => $serienCounts,
        ]);
    }

    /* --------------------------------------------------------------------- */
    /*  GET /kompendium/serien – Verfügbare Serien für Filter */
    /* --------------------------------------------------------------------- */
    public function getVerfuegbareSerien(): JsonResponse
    {
        /* ----- Punkte-Check ------------------------------------------------ */
        $user = Auth::user();
        $userPoints = $this->teamPointService->getUserPoints($user);

        if ($userPoints < self::REQUIRED_POINTS) {
            return response()->json([
                'message' => 'Mindestens '.self::REQUIRED_POINTS." Punkte erforderlich (du hast $userPoints).",
            ], 403);
        }

        // Nur Serien zurückgeben, die indexierte Romane haben
        $serien = KompendiumRoman::indexiert()
            ->select('serie')
            ->distinct()
            ->pluck('serie')
            ->mapWithKeys(function ($key) {
                if (! isset(KompendiumService::SERIEN[$key])) {
                    \Log::warning("Unbekannte Serie '{$key}' in Kompendium gefunden – bitte in KompendiumService::SERIEN ergänzen.");
                }

                return [$key => KompendiumService::SERIEN[$key] ?? $key];
            });

        return response()->json($serien);
    }

    /* --------------------------------------------------------------------- */
    /*  Hilfsfunktion: Pfad → Serie, Nummer, Titel */
    /*  Pfad-Format: "romane/{serie}/001 - Titel.txt" */
    /*  → parts[0]='romane', parts[1]='{serie}' */
    /* --------------------------------------------------------------------- */
    private function extractMetaFromPath(string $path): array
    {
        $parts = preg_split(self::PATH_SEPARATOR_PATTERN, $path);
        $serie = $parts[1] ?? 'unknown';

        $filename = pathinfo($path, PATHINFO_FILENAME);

        // Format: "001 - Titel" – falls kein Trennzeichen, Fallback verwenden
        if (! str_contains($filename, ' - ')) {
            \Log::warning("Kompendium: Dateiname '{$filename}' entspricht nicht dem erwarteten Format '001 - Titel'.");

            return [$serie, '???', $filename];
        }

        [$romanNr, $title] = explode(' - ', $filename, 2);

        return [$serie, $romanNr, $title];
    }

    /* --------------------------------------------------------------------- */
    /*  Hilfsfunktion: Pfad → Serie */
    /*  Pfad-Format: "romane/{serie}/filename.txt" */
    /*  → parts[0]='romane', parts[1]='{serie}' */
    /* --------------------------------------------------------------------- */
    private function extractSerieFromPath(string $path): string
    {
        $parts = preg_split(self::PATH_SEPARATOR_PATTERN, $path);

        return $parts[1] ?? 'unknown';
    }
}
