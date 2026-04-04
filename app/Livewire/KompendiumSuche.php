<?php

namespace App\Livewire;

use App\Models\KompendiumRoman;
use App\Services\KompendiumSearchService;
use App\Services\KompendiumService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class KompendiumSuche extends Component
{
    public string $query = '';

    public array $selectedSerien = [];

    public array $results = [];

    public int $page = 1;

    public int $lastPage = 1;

    public array $serienCounts = [];

    public bool $isPhraseSearch = false;

    public array $searchInfo = [];

    public ?string $error = null;

    public bool $hasSearched = false;

    private const PATH_SEPARATOR_PATTERN = '#[\\\\\/]+#';

    private const ALLOWED_BASE_PATH = 'romane/';

    public function mount(): void
    {
        $this->selectedSerien = array_keys($this->verfuegbareSerien);
    }

    #[Computed(cache: true, seconds: 3600)]
    public function verfuegbareSerien(): array
    {
        return KompendiumRoman::indexiert()
            ->select('serie')
            ->distinct()
            ->pluck('serie')
            ->mapWithKeys(fn ($key) => [$key => KompendiumService::SERIEN[$key] ?? $key])
            ->toArray();
    }

    public function performSearch(): void
    {
        if (mb_strlen(trim($this->query)) < 2) {
            return;
        }

        $this->page = 1;
        $this->results = [];
        $this->hasSearched = true;
        $this->error = null;
        $this->executeSearch();
    }

    public function loadMore(): void
    {
        if ($this->page >= $this->lastPage) {
            return;
        }

        $this->page++;
        $this->executeSearch();
    }

    public function updatedSelectedSerien(): void
    {
        if (empty($this->selectedSerien)) {
            $this->selectedSerien = array_keys($this->verfuegbareSerien);

            return;
        }

        if ($this->hasSearched && mb_strlen(trim($this->query)) >= 2) {
            $this->page = 1;
            $this->results = [];
            $this->executeSearch();
        }
    }

    private function executeSearch(): void
    {
        $searchService = app(KompendiumSearchService::class);
        $query = mb_strtolower(trim($this->query));
        $perPage = 5;
        $snippetsPerFile = config('kompendium.snippets_per_novel', 10) ?: 10;
        $radius = 200;

        $parsed = $searchService->parseSearchQuery($query);
        $tntQuery = $parsed['hadQuotes']
            ? $searchService->buildTntSearchQuery($parsed)
            : $query;

        if ($tntQuery === '') {
            $stripped = preg_replace('/"[^"]*"/', '', $query);
            $tntQuery = trim($stripped);

            if ($tntQuery === '') {
                $this->lastPage = 1;
                $this->isPhraseSearch = false;
                $this->searchInfo = ['phrases' => [], 'terms' => []];

                return;
            }
        }

        $raw = $searchService->search($tntQuery);
        $ids = array_values($raw['ids'] ?? []);
        $ids = array_values(array_filter($ids, fn ($path) => $this->isValidPath($path)));

        $textCache = [];
        $maxCandidates = 200;

        if ($parsed['isPhraseSearch']) {
            $candidates = array_slice($ids, 0, $maxCandidates);
            $ids = array_values(array_filter($candidates, function ($path) use ($parsed, &$textCache) {
                if (! Storage::disk('private')->exists($path)) {
                    return false;
                }

                $text = Storage::disk('private')->get($path);

                foreach ($parsed['phrases'] as $phrase) {
                    if (mb_stripos($text, $phrase) === false) {
                        return false;
                    }
                }

                foreach ($parsed['terms'] as $term) {
                    if (mb_stripos($text, $term) === false) {
                        return false;
                    }
                }

                $textCache[$path] = $text;

                return true;
            }));
        }

        $serienCounts = [];
        $pathToSerie = [];

        foreach ($ids as $path) {
            $serie = $this->extractSerie($path);
            $pathToSerie[$path] = $serie;
            $serienCounts[$serie] = ($serienCounts[$serie] ?? 0) + 1;
        }

        $this->serienCounts = $serienCounts;

        if (! empty($this->selectedSerien)) {
            $ids = array_values(array_filter(
                $ids,
                fn ($path) => in_array($pathToSerie[$path], $this->selectedSerien, true)
            ));
        }

        $total = count($ids);
        $slice = array_slice($ids, ($this->page - 1) * $perPage, $perPage);
        $this->lastPage = max(1, (int) ceil($total / $perPage));

        $snippetTerms = $this->buildSnippetTerms($parsed, $tntQuery);

        if (empty($snippetTerms)) {
            $this->isPhraseSearch = $parsed['isPhraseSearch'];
            $this->searchInfo = ['phrases' => $parsed['phrases'], 'terms' => $parsed['terms']];

            return;
        }

        $highlightPattern = '/('.implode('|', array_map(fn ($t) => preg_quote($t, '/'), $snippetTerms)).')/iu';

        $hits = [];

        foreach ($slice as $path) {
            if (! Storage::disk('private')->exists($path)) {
                continue;
            }

            [$serie, $romanNr, $title] = $this->extractMeta($path);
            $text = $textCache[$path] ?? Storage::disk('private')->get($path);
            $snippets = $this->extractSnippets($text, $snippetTerms, $highlightPattern, $snippetsPerFile, $radius);
            $cycleName = KompendiumService::SERIEN[$serie] ?? Str::of($serie)->replace('-', ' ')->title();

            $hits[] = [
                'cycle' => $cycleName,
                'romanNr' => $romanNr,
                'title' => $title,
                'serie' => $serie,
                'snippets' => $snippets,
            ];
        }

        $this->results = array_merge($this->results, $hits);
        $this->isPhraseSearch = $parsed['isPhraseSearch'];
        $this->searchInfo = ['phrases' => $parsed['phrases'], 'terms' => $parsed['terms']];
    }

    private function isValidPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        if (preg_match('#(^|/)\.\.(/|$)#', $normalized) || preg_match('#(^|/)\./#', $normalized)) {
            return false;
        }

        return str_starts_with($normalized, self::ALLOWED_BASE_PATH);
    }

    private function extractSerie(string $path): string
    {
        $parts = preg_split(self::PATH_SEPARATOR_PATTERN, $path);

        return $parts[1] ?? 'unknown';
    }

    private function extractMeta(string $path): array
    {
        $parts = preg_split(self::PATH_SEPARATOR_PATTERN, $path);
        $serie = $parts[1] ?? 'unknown';
        $filename = pathinfo($path, PATHINFO_FILENAME);

        if (! str_contains($filename, ' - ')) {
            return [$serie, '???', $filename];
        }

        [$romanNr, $title] = explode(' - ', $filename, 2);

        return [$serie, $romanNr, $title];
    }

    private function buildSnippetTerms(array $parsed, string $tntQuery): array
    {
        if ($parsed['isPhraseSearch']) {
            $terms = array_merge($parsed['phrases'], $parsed['terms']);
        } elseif (! empty($parsed['terms'])) {
            $terms = $parsed['terms'];
        } else {
            $terms = [$tntQuery];
        }

        $terms = array_slice($terms, 0, 20);
        usort($terms, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));

        $deduped = [];
        foreach ($terms as $term) {
            $isSubstring = false;
            foreach ($deduped as $existing) {
                if (mb_stripos($existing, $term) !== false) {
                    $isSubstring = true;

                    break;
                }
            }
            if (! $isSubstring) {
                $deduped[] = $term;
            }
        }

        return $deduped;
    }

    /**
     * Extrahiert Snippets mit Highlighting aus dem Text.
     *
     * Snippet-HTML ist sicher: Segmente werden mit e() escaped,
     * nur <mark>-Tags werden für Treffer-Highlighting eingefügt.
     *
     * @return list<string> HTML-Snippets mit <mark>-Highlighting
     */
    private function extractSnippets(string $text, array $terms, string $pattern, int $max, int $radius): array
    {
        $snippets = [];

        foreach ($terms as $term) {
            $offset = 0;

            while (($pos = mb_stripos($text, $term, $offset)) !== false && count($snippets) < $max) {
                $start = max($pos - $radius, 0);
                $length = mb_strlen($term) + (2 * $radius);
                $raw = mb_substr($text, $start, $length);
                $segments = preg_split($pattern, $raw, -1, PREG_SPLIT_DELIM_CAPTURE);
                $html = '';

                foreach ($segments as $i => $segment) {
                    $html .= ($i % 2 === 1) ? '<mark>'.e($segment).'</mark>' : e($segment);
                }

                $snippets[] = $html;
                $offset = $pos + mb_strlen($term);
            }
        }

        return $snippets;
    }

    public function render()
    {
        return view('livewire.kompendium-suche');
    }
}
