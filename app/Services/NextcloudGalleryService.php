<?php

namespace App\Services;

use DateTimeInterface;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NextcloudGalleryService
{
    private const SUCCESSFUL_LISTING_CACHE_MINUTES = 30;

    private const EMPTY_LISTING_CACHE_MINUTES = 2;

    /**
     * @return array<int, string>
     */
    public function photoUrls(string $galleryLink, ?string $cacheKey = null): array
    {
        $galleryLink = $this->sanitizeGalleryLink($galleryLink);

        if ($galleryLink === '') {
            return [];
        }

        $cacheKey ??= $this->cacheKey($galleryLink);

        $cachedPhotoUrls = Cache::get($cacheKey);

        if (is_array($cachedPhotoUrls)) {
            return $cachedPhotoUrls;
        }

        $result = $this->fetchPhotoUrls($galleryLink);

        if ($result['cache_until'] instanceof DateTimeInterface) {
            Cache::put($cacheKey, $result['urls'], $result['cache_until']);
        }

        return $result['urls'];
    }

    public function photoUrlForIndex(string $galleryLink, int $index, ?string $cacheKey = null): ?string
    {
        if ($index < 1) {
            return null;
        }

        return $this->photoUrls($galleryLink, $cacheKey)[$index - 1] ?? null;
    }

    private function cacheKey(string $galleryLink): string
    {
        return 'nextcloud_gallery:'.sha1($galleryLink);
    }

    private function sanitizeGalleryLink(string $galleryLink): string
    {
        return trim($galleryLink, " \t\n\r\0\x0B\"'");
    }

    /**
     * @return array{urls: array<int, string>, cache_until: ?DateTimeInterface}
     */
    private function fetchPhotoUrls(string $galleryLink): array
    {
        $galleryLink = $this->sanitizeGalleryLink($galleryLink);

        $configuration = $this->resolveListingConfiguration($galleryLink);

        if ($configuration === null) {
            return [
                'urls' => [],
                'cache_until' => null,
            ];
        }

        try {
            $response = Http::timeout(10)
                ->accept('application/xml, text/xml, */*')
                ->withHeaders(['Depth' => '1'])
                ->send('PROPFIND', $configuration['directory_url']);

            if (! $response->successful()) {
                return [
                    'urls' => [],
                    'cache_until' => null,
                ];
            }
        } catch (\Throwable $throwable) {
            Log::warning('Fehler beim Laden der Nextcloud-Fotogalerie', [
                'link' => $galleryLink,
                'message' => $throwable->getMessage(),
            ]);

            return [
                'urls' => [],
                'cache_until' => null,
            ];
        }

        $photoUrls = $this->parsePhotoUrls(
            $response->body(),
            $configuration['origin'],
            $configuration['filename_prefix'],
        );

        if ($photoUrls === null) {
            return [
                'urls' => [],
                'cache_until' => null,
            ];
        }

        return [
            'urls' => $photoUrls,
            'cache_until' => empty($photoUrls)
                ? now()->addMinutes(self::EMPTY_LISTING_CACHE_MINUTES)
                : now()->addMinutes(self::SUCCESSFUL_LISTING_CACHE_MINUTES),
        ];
    }

    /**
     * @return array{directory_url: string, origin: string, filename_prefix: ?string}|null
     */
    private function resolveListingConfiguration(string $galleryLink): ?array
    {
        $parts = parse_url($galleryLink);

        if (! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = $parts['path'];

        if (preg_match('#^/s/([^/]+)/?$#', $path, $matches) === 1) {
            return [
                'directory_url' => $origin.'/public.php/dav/files/'.$matches[1].'/',
                'origin' => $origin,
                'filename_prefix' => null,
            ];
        }

        if (preg_match('#^/public\.php/dav/files/([^/]+)(?:/(.*))?$#', $path, $matches) !== 1) {
            return null;
        }

        $token = $matches[1];
        $suffix = $matches[2] ?? '';

        if ($suffix === '' || str_ends_with($path, '/')) {
            return [
                'directory_url' => $origin.'/public.php/dav/files/'.$token.'/'.ltrim($suffix, '/'),
                'origin' => $origin,
                'filename_prefix' => null,
            ];
        }

        $segments = explode('/', trim($suffix, '/'));
        $filenamePrefix = array_pop($segments);
        $directoryPath = '/public.php/dav/files/'.$token.'/';

        if ($segments !== []) {
            $directoryPath .= implode('/', $segments).'/';
        }

        return [
            'directory_url' => $origin.$directoryPath,
            'origin' => $origin,
            'filename_prefix' => $filenamePrefix,
        ];
    }

    /**
     * @return array<int, string>|null
     */
    private function parsePhotoUrls(string $xml, string $origin, ?string $filenamePrefix): ?array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');

        $previousState = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('d', 'DAV:');

        $urls = [];

        foreach ($xpath->query('/d:multistatus/d:response') as $response) {
            $href = trim((string) $xpath->evaluate('string(d:href)', $response));

            if ($href === '' || str_ends_with($href, '/')) {
                continue;
            }

            $basename = basename(rawurldecode((string) parse_url($href, PHP_URL_PATH)));

            if ($filenamePrefix !== null && ! str_starts_with($basename, $filenamePrefix)) {
                continue;
            }

            $contentType = strtolower(trim((string) $xpath->evaluate(
                'string(d:propstat[d:status[contains(., "200")]]/d:prop/d:getcontenttype)',
                $response,
            )));

            if (! $this->isImageResource($basename, $contentType)) {
                continue;
            }

            $url = $this->toAbsoluteUrl($origin, $href);

            if ($url === null) {
                continue;
            }

            $urls[] = $url;
        }

        $urls = array_values(array_unique($urls));

        usort(
            $urls,
            static fn (string $left, string $right): int => strnatcasecmp(
                basename(rawurldecode((string) parse_url($left, PHP_URL_PATH))),
                basename(rawurldecode((string) parse_url($right, PHP_URL_PATH))),
            ),
        );

        return $urls;
    }

    private function isImageResource(string $basename, string $contentType): bool
    {
        if (str_starts_with($contentType, 'image/')) {
            return true;
        }

        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true);
    }

    private function toAbsoluteUrl(string $origin, string $href): ?string
    {
        if (preg_match('#^https?://#i', $href) === 1) {
            return $this->sameOriginDavUrl($origin, $href) ? $href : null;
        }

        $path = $this->normalizeDavPath((string) parse_url($href, PHP_URL_PATH));

        if ($path === null) {
            return null;
        }

        $query = parse_url($href, PHP_URL_QUERY);

        return $origin.$path.($query !== null ? '?'.$query : '');
    }

    private function sameOriginDavUrl(string $origin, string $href): bool
    {
        $originParts = parse_url($origin);
        $hrefParts = parse_url($href);

        if (! is_array($originParts) || ! is_array($hrefParts)) {
            return false;
        }

        if (! isset($originParts['scheme'], $originParts['host'], $hrefParts['scheme'], $hrefParts['host'])) {
            return false;
        }

        if (strtolower($originParts['scheme']) !== strtolower($hrefParts['scheme'])) {
            return false;
        }

        if (strtolower($originParts['host']) !== strtolower($hrefParts['host'])) {
            return false;
        }

        if ($this->normalizedPort($originParts) !== $this->normalizedPort($hrefParts)) {
            return false;
        }

        return $this->normalizeDavPath((string) ($hrefParts['path'] ?? '')) !== null;
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function normalizedPort(array $parts): int
    {
        if (isset($parts['port'])) {
            return (int) $parts['port'];
        }

        return strtolower((string) ($parts['scheme'] ?? 'https')) === 'http' ? 80 : 443;
    }

    private function normalizeDavPath(string $path): ?string
    {
        $normalizedPath = '/'.ltrim($path, '/');

        if (! str_starts_with($normalizedPath, '/public.php/dav/files/')) {
            return null;
        }

        return $normalizedPath;
    }
}
