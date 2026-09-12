<?php

namespace App\Services\Maddraxikon;

use App\Exceptions\MaddraxikonCrawlException;
use App\Support\UriSupport;
use DOMDocument;
use DOMElement;
use DOMXPath;

class MaddraxikonCategoryPaginator
{
    public function __construct(
        private readonly MaddraxikonCrawlerHttpClient $http,
    ) {}

    /** @return list<string> */
    public function articleUrls(string $categoryUrl): array
    {
        $currentUrl = $categoryUrl;
        $visitedPages = [];
        $articleUrls = [];
        $maxPages = max(1, (int) config('maddraxikon.crawler.max_pages', 50));

        for ($page = 1; $page <= $maxPages; $page++) {
            if (isset($visitedPages[$currentUrl])) {
                throw new MaddraxikonCrawlException(
                    "Paginierungsschleife bei {$currentUrl} erkannt.",
                    $currentUrl,
                );
            }

            $visitedPages[$currentUrl] = true;
            $xpath = $this->xpath($this->http->get($currentUrl), $currentUrl);
            $containers = $xpath->query("//div[@id='mw-pages']");

            if ($containers === false || $containers->length === 0) {
                throw new MaddraxikonCrawlException(
                    "Kategoriecontainer auf {$currentUrl} fehlt.",
                    $currentUrl,
                );
            }

            $anchors = $xpath->query("//div[@id='mw-pages']//a[@href]");
            $nextUrl = null;

            if ($anchors !== false) {
                foreach ($anchors as $anchor) {
                    if (! $anchor instanceof DOMElement) {
                        continue;
                    }

                    $resolved = $this->resolve($anchor->getAttribute('href'));
                    $label = $this->normalizeText($anchor->textContent);

                    if ($this->isNextLabel($label)) {
                        if ($resolved === null) {
                            throw new MaddraxikonCrawlException(
                                "Ungültiger Folgeseiten-Link auf {$currentUrl}.",
                                $currentUrl,
                            );
                        }

                        if ($nextUrl !== null && $nextUrl !== $resolved) {
                            throw new MaddraxikonCrawlException(
                                "Widersprüchliche Folgeseiten-Links auf {$currentUrl}.",
                                $currentUrl,
                            );
                        }

                        $nextUrl = $resolved;

                        continue;
                    }

                    if (
                        $resolved === null
                        || $this->isPreviousLabel($label)
                        || $this->isCategoryUrl($resolved)
                    ) {
                        continue;
                    }

                    $articleUrls[$resolved] = true;
                }
            }

            if ($nextUrl === null) {
                if ($articleUrls === []) {
                    throw new MaddraxikonCrawlException(
                        "Keine Romanartikel in {$categoryUrl} gefunden.",
                        $categoryUrl,
                    );
                }

                return array_keys($articleUrls);
            }

            $currentUrl = $nextUrl;
        }

        throw new MaddraxikonCrawlException(
            "Maximale Zahl von {$maxPages} Kategorieseiten überschritten.",
            $currentUrl,
        );
    }

    private function xpath(string $html, string $url): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new MaddraxikonCrawlException(
                "Ungültiges HTML von {$url}.",
                $url,
            );
        }

        return new DOMXPath($dom);
    }

    private function resolve(string $href): ?string
    {
        $baseUrl = rtrim((string) config(
            'maddraxikon.base_url',
            'https://de.maddraxikon.com'
        ), '/').'/';
        $resolved = UriSupport::resolve($baseUrl, $href);
        $baseParts = parse_url($baseUrl);
        $host = is_array($baseParts)
            ? (string) ($baseParts['host'] ?? 'de.maddraxikon.com')
            : 'de.maddraxikon.com';
        $port = is_array($baseParts) && isset($baseParts['port'])
            ? (int) $baseParts['port']
            : 443;

        return $resolved !== null
            && UriSupport::isAbsoluteUrlForHost($resolved, 'https', $host, $port)
                ? $resolved
                : null;
    }

    private function normalizeText(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }

    private function isNextLabel(string $label): bool
    {
        return str_contains($label, 'nächste seite');
    }

    private function isPreviousLabel(string $label): bool
    {
        return str_contains($label, 'vorherige seite');
    }

    private function isCategoryUrl(string $url): bool
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query)) {
            return false;
        }

        parse_str($query, $parameters);

        return str_starts_with((string) ($parameters['title'] ?? ''), 'Kategorie:');
    }
}
