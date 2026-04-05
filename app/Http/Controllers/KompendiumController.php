<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\KompendiumRoman;
use App\Models\Reward;
use App\Models\User;
use App\Services\KompendiumSearchService;
use App\Services\KompendiumService;
use App\Services\RewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KompendiumController extends Controller
{
    public function __construct(
        private KompendiumService $kompendiumService,
        private KompendiumSearchService $searchService,
        private RewardService $rewardService
    ) {}

    /** Regex-Pattern für Pfad-Trennung (Windows-Backslash und Unix-Slash) */
    private const PATH_SEPARATOR_PATTERN = '#[\\\\\/]+#';

    /** Erlaubtes Basis-Verzeichnis für Roman-Dateien */
    private const ALLOWED_BASE_PATH = 'romane/';

    /* --------------------------------------------------------------------- */
    /*  Zugangs-Check: Reward gekauft ODER AG-Maddraxikon-Mitglied */
    /* --------------------------------------------------------------------- */

    /**
     * Prüft ob der User Zugang zur Kompendium-Suche hat.
     * Zugang besteht bei gekauftem Kompendium-Reward ODER Mitgliedschaft in AG Maddraxikon.
     */
    private function hatKompendiumZugang(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // AG-Maddraxikon-Mitgliedschaft gewährt sofortigen Zugang
        if ($user->isMemberOfTeam('AG Maddraxikon')) {
            return true;
        }

        // Reward-basierter Zugang
        return $this->rewardService->hasUnlockedReward($user, 'kompendium');
    }

    /* --------------------------------------------------------------------- */
    /*  Pfad-Validierung gegen Path-Traversal-Angriffe */
    /* --------------------------------------------------------------------- */
    private function isValidRomanPath(string $path): bool
    {
        // Normalisiere den Pfad: ersetze Backslashes durch Slashes
        $normalized = str_replace('\\', '/', $path);

        // Prüfe auf Path-Traversal-Sequenzen (nur an Segment-Grenzen)
        // Muster: '../' irgendwo, oder './' am Anfang oder nach einem '/'
        if (preg_match('#(^|/)\.\.(/|$)#', $normalized) || preg_match('#(^|/)\./#', $normalized)) {
            Log::warning("Kompendium: Verdächtiger Pfad mit Traversal-Sequenz abgelehnt: '{$path}'");

            return false;
        }

        // Stelle sicher, dass der Pfad mit dem erlaubten Basis-Verzeichnis beginnt
        if (! str_starts_with($normalized, self::ALLOWED_BASE_PATH)) {
            Log::warning("Kompendium: Pfad außerhalb des erlaubten Verzeichnisses abgelehnt: '{$path}'");

            return false;
        }

        return true;
    }

    /* --------------------------------------------------------------------- */
    /*  GET /kompendium  – Übersichtsseite */
    /* --------------------------------------------------------------------- */
    public function index(): View
    {
        $user = Auth::user();
        $hatZugang = $this->hatKompendiumZugang($user);

        // Kompendium-Reward aus der DB laden
        $kompendiumReward = Reward::where('slug', 'kompendium')->first();

        // Zusammengefasste Übersicht (Maddrax-Zyklen konsolidiert, Miniserien als ein Eintrag)
        $indexierteRomaneSummary = $this->kompendiumService->getZusammengefassteUebersicht();

        // Prüfen ob User Admin ist
        $istAdmin = $user?->currentTeam?->hasUserWithRole($user, Role::Admin->value) ?? false;

        return view('pages.kompendium', [
            'showSearch' => $hatZugang,
            'kompendiumReward' => $kompendiumReward,
            'indexierteRomaneSummary' => $indexierteRomaneSummary,
            'istAdmin' => $istAdmin,
        ]);
    }

    /* --------------------------------------------------------------------- */
    /*  GET /kompendium/suche  (AJAX) */
    /* --------------------------------------------------------------------- */
    public function search(Request $request): JsonResponse
    {
        /* ----- Zugangs-Check ----------------------------------------------- */
        $user = Auth::user();

        if (! $this->hatKompendiumZugang($user)) {
            return response()->json([
                'message' => 'Zugang erfordert den Kauf des Kompendium-Rewards oder AG-Maddraxikon-Mitgliedschaft.',
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
        /*  Query parsen: Phrasen in Anführungszeichen vs. freie Begriffe */
        /* ------------------------------------------------------------------ */
        $parsed = $this->searchService->parseSearchQuery($query);
        $tntQuery = $parsed['isPhraseSearch']
            ? $this->searchService->buildTntSearchQuery($parsed)
            : $query;

        // Fallback: Wenn Quotes vorhanden waren, aber keine gültigen Begriffe extrahiert
        // werden konnten (z.B. "A" oder ""), auf den bereinigten Original-Query zurückfallen.
        if ($tntQuery === '') {
            $stripped = preg_replace('/"[^"]*"/', '', $query);
            $tntQuery = trim($stripped);

            if ($tntQuery === '') {
                // Kein brauchbarer Suchbegriff übrig → leere Ergebnisse zurückgeben
                return response()->json([
                    'data' => [],
                    'currentPage' => $page,
                    'lastPage' => 1,
                    'serienCounts' => [],
                    'isPhraseSearch' => false,
                    'searchInfo' => ['phrases' => [], 'terms' => []],
                ]);
            }
        }

        /* ------------------------------------------------------------------ */
        /*  SCOUT-SUCHAUFRUF  (RAW) */
        /* ------------------------------------------------------------------ */
        $raw = $this->searchService->search($tntQuery);

        $ids = $raw['ids'] ?? [];                               // enthält unsere "path"-Schlüssel
        $ids = array_values($ids);                              // re-indexieren

        // Sicherheitsprüfung: Nur gültige Pfade weiterverarbeiten
        $ids = array_values(array_filter($ids, fn ($path) => $this->isValidRomanPath($path)));

        /* ------------------------------------------------------------------ */
        /*  Phrasensuche: Post-Filterung auf exakte Übereinstimmung */
        /*  Obergrenze für Kandidaten, um I/O und RAM zu begrenzen. */
        /* ------------------------------------------------------------------ */
        $textCache = [];
        $maxPostFilterCandidates = 200;
        $candidatesTruncated = false;

        if ($parsed['isPhraseSearch']) {
            if (count($ids) > $maxPostFilterCandidates) {
                $candidatesTruncated = true;
            }
            $candidates = array_slice($ids, 0, $maxPostFilterCandidates);
            $ids = array_values(array_filter($candidates, function ($path) use ($parsed, &$textCache) {
                if (! Storage::disk('private')->exists($path)) {
                    return false;
                }

                $text = Storage::disk('private')->get($path);

                // Alle Phrasen müssen als exakte Substrings vorkommen
                foreach ($parsed['phrases'] as $phrase) {
                    if (mb_stripos($text, $phrase) === false) {
                        return false;
                    }
                }

                // Alle freien Begriffe müssen ebenfalls vorkommen
                foreach ($parsed['terms'] as $term) {
                    if (mb_stripos($text, $term) === false) {
                        return false;
                    }
                }

                // Nur Treffer cachen, um RAM zu sparen
                $textCache[$path] = $text;

                return true;
            }));
        }

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
        /*  Suchbegriffe für Snippet-Extraktion zusammenstellen */
        /*  Immer aus den geparsten Begriffen ableiten, damit keine */
        /*  Anführungszeichen in den Snippet-Suchterms landen. */
        /* ------------------------------------------------------------------ */
        if ($parsed['isPhraseSearch']) {
            $snippetSearchTerms = array_merge($parsed['phrases'], $parsed['terms']);
        } elseif (! empty($parsed['terms'])) {
            // Quotes vorhanden oder normale Suche → individuelle Terme für Highlighting
            $snippetSearchTerms = $parsed['terms'];
        } else {
            // Fallback (sollte selten auftreten)
            $snippetSearchTerms = [$tntQuery];
        }

        // Begrenzung der Highlight-Terme: maximal 20 Terme verwenden,
        // um bei sehr langen Queries das Regex nicht zu sprengen.
        $maxHighlightTerms = 20;
        if (count($snippetSearchTerms) > $maxHighlightTerms) {
            $snippetSearchTerms = array_slice($snippetSearchTerms, 0, $maxHighlightTerms);
        }

        // Längere Begriffe zuerst → verhindert Teilmarkierungen bei Überlappung
        usort($snippetSearchTerms, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));

        // Begriffe deduplizieren: Entferne Teilstrings, die bereits von einem längeren
        // Begriff abgedeckt werden (z.B. "Matthew" wenn "Matthew Drax" schon enthalten ist)
        $deduplicated = [];
        foreach ($snippetSearchTerms as $term) {
            $isSubstring = false;
            foreach ($deduplicated as $existing) {
                if (mb_stripos($existing, $term) !== false) {
                    $isSubstring = true;
                    break;
                }
            }
            if (! $isSubstring) {
                $deduplicated[] = $term;
            }
        }
        $snippetSearchTerms = $deduplicated;

        // Kombiniertes Regex-Pattern für Single-Pass-Highlighting (alle Begriffe als Alternation, auf Raw-Text)
        $highlightPattern = '/('.implode('|', array_map(fn ($t) => preg_quote($t, '/'), $snippetSearchTerms)).')/iu';

        /* ------------------------------------------------------------------ */
        /*  Treffer in Frontend-Format wandeln */
        /* ------------------------------------------------------------------ */
        $hits = [];

        foreach ($slice as $path) {

            // Prüfe ob Datei existiert (könnte nach Indexierung gelöscht worden sein)
            if (! Storage::disk('private')->exists($path)) {
                Log::info("Kompendium: Datei nicht gefunden, überspringe: '{$path}'");

                continue;
            }

            [$serie, $romanNr, $title] = $this->extractMetaFromPath($path);

            /* Original-Text laden (Cache nutzen falls vorhanden) → Snippets bilden */
            $text = $textCache[$path] ?? Storage::disk('private')->get($path);
            $snippets = [];

            foreach ($snippetSearchTerms as $searchTerm) {
                $offset = 0;

                while (
                    ($pos = mb_stripos($text, $searchTerm, $offset)) !== false &&
                    count($snippets) < $snippetsPerFile
                ) {
                    $start = max($pos - $radius, 0);
                    $length = mb_strlen($searchTerm) + (2 * $radius);
                    $snippet = mb_substr($text, $start, $length);

                    // Highlighting auf Raw-Text: in Segmente splitten, einzeln escapen,
                    // Matches mit <mark> umschließen → keine Matches innerhalb von HTML-Entities
                    $segments = preg_split($highlightPattern, $snippet, -1, PREG_SPLIT_DELIM_CAPTURE);
                    $snippet = '';
                    foreach ($segments as $i => $segment) {
                        if ($i % 2 === 1) {
                            // Ungerade Indizes = Matches (Capture-Group)
                            $snippet .= '<mark>'.e($segment).'</mark>';
                        } else {
                            $snippet .= e($segment);
                        }
                    }

                    $snippets[] = $snippet;
                    $offset = $pos + mb_strlen($searchTerm);
                }
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

        $responseData = [
            'data' => $paginator->items(),
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'serienCounts' => $serienCounts,
            'isPhraseSearch' => $parsed['isPhraseSearch'],
            'searchInfo' => [
                'phrases' => $parsed['phrases'],
                'terms' => $parsed['terms'],
            ],
        ];

        if ($candidatesTruncated) {
            $responseData['candidatesTruncated'] = true;
        }

        return response()->json($responseData);
    }

    /* --------------------------------------------------------------------- */
    /*  GET /kompendium/serien – Verfügbare Serien für Filter */
    /* --------------------------------------------------------------------- */
    public function getVerfuegbareSerien(): JsonResponse
    {
        /* ----- Zugangs-Check ----------------------------------------------- */
        $user = Auth::user();

        if (! $this->hatKompendiumZugang($user)) {
            return response()->json([
                'message' => 'Zugang erfordert den Kauf des Kompendium-Rewards oder AG-Maddraxikon-Mitgliedschaft.',
            ], 403);
        }

        // Nur Serien zurückgeben, die indexierte Romane haben
        $serien = KompendiumRoman::indexiert()
            ->select('serie')
            ->distinct()
            ->pluck('serie')
            ->mapWithKeys(function ($key) {
                if (! isset(KompendiumService::SERIEN[$key])) {
                    Log::warning("Unbekannte Serie '{$key}' in Kompendium gefunden – bitte in KompendiumService::SERIEN ergänzen.");
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
            Log::warning("Kompendium: Dateiname '{$filename}' entspricht nicht dem erwarteten Format '001 - Titel'.");

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
