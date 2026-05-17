<?php

namespace App\Http\Controllers;

use App\Services\NextcloudGalleryService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhotoGalleryController extends Controller
{
    public function __construct(
        private readonly NextcloudGalleryService $galleryService,
    ) {}

    public function index()
    {
        $photoBaseUrls = $this->photoBaseUrls();
        $years = array_keys($photoBaseUrls);
        $activeYear = $years[0] ?? '';
        $photos = collect($photoBaseUrls)
            ->mapWithKeys(fn (string $baseUrl, string $year): array => [$year => $this->getPhotosForYear($year, $baseUrl)])
            ->all();

        return view('pages.fotogalerie', compact('years', 'activeYear', 'photos'));
    }

    /**
     * Lädt Fotos für ein bestimmtes Jahr
     */
    private function getPhotosForYear(string $year, ?string $baseUrl = null): array
    {
        $baseUrl ??= $this->photoBaseUrls()[$year] ?? '';

        if (empty($baseUrl)) {
            return $this->getFallbackPhotos($year);
        }

        $photoUrls = $this->galleryService->photoUrls($baseUrl, 'nextcloud_gallery:'.$year.':'.sha1($baseUrl));

        if (empty($photoUrls)) {
            return $this->getFallbackPhotos($year);
        }

        return $photoUrls;
    }

    /**
     * Gibt Fallback-Fotos zurück, falls keine Nextcloud-Fotos gefunden wurden
     */
    private function getFallbackPhotos($year)
    {
        return [
            $this->placeholderDataUrl($year, 1),
            $this->placeholderDataUrl($year, 2),
        ];
    }

    private function placeholderDataUrl(string $year, int $index): string
    {
        return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($this->placeholderSvg($year, $index));
    }

    private function placeholderSvg(string $year, int $index = 1): string
    {
        $safeYear = htmlspecialchars($year, ENT_QUOTES, 'UTF-8');
        $safeIndex = htmlspecialchars((string) $index, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" role="img" aria-labelledby="title desc">
    <title id="title">Fotogalerie {$safeYear}</title>
    <desc id="desc">Platzhalterbild {$safeIndex} fuer die Fotogalerie {$safeYear}</desc>
    <defs>
        <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#f8fafc"/>
            <stop offset="100%" stop-color="#e2e8f0"/>
        </linearGradient>
    </defs>
    <rect width="1200" height="800" rx="36" fill="url(#bg)"/>
    <circle cx="196" cy="188" r="108" fill="#8b0116" opacity="0.12"/>
    <circle cx="1012" cy="612" r="154" fill="#8b0116" opacity="0.08"/>
    <rect x="112" y="124" width="976" height="552" rx="30" fill="#ffffff" opacity="0.92"/>
    <path d="M224 548l148-160c22-24 60-25 84-2l102 96 154-178c25-29 70-30 97-2l167 170" fill="none" stroke="#8b0116" stroke-width="24" stroke-linecap="round" stroke-linejoin="round" opacity="0.74"/>
    <circle cx="420" cy="294" r="44" fill="#8b0116" opacity="0.24"/>
    <text x="600" y="326" text-anchor="middle" font-family="Arial, sans-serif" font-size="108" font-weight="700" fill="#8b0116">{$safeYear}</text>
    <text x="600" y="420" text-anchor="middle" font-family="Arial, sans-serif" font-size="38" fill="#334155">Fotogalerie im Aufbau</text>
    <text x="600" y="478" text-anchor="middle" font-family="Arial, sans-serif" font-size="28" fill="#64748b">Platzhalter {$safeIndex}</text>
</svg>
SVG;
    }

    /**
     * Gibt konfigurierte Galerie-Basis-URLs nach Jahr absteigend sortiert zurück.
     *
     * @return array<string, string>
     */
    private function photoBaseUrls(): array
    {
        $links = config('services.nextcloud.links', []);

        if (! is_array($links)) {
            return [];
        }

        $links = array_filter(
            $links,
            static fn (mixed $url): bool => is_string($url) && filled($url),
        );

        uksort(
            $links,
            static fn (string $left, string $right): int => (int) $right <=> (int) $left,
        );

        return $links;
    }

    /**
     * Proxy für Bilder, um mögliche CORS-Probleme zu umgehen
     */
    public function proxyImage(string $year, int $index)
    {
        $baseUrl = $this->photoBaseUrls()[$year] ?? '';
        if (empty($baseUrl)) {
            return $this->placeholderResponse($year, $index);
        }

        $photoUrl = $this->galleryService->photoUrlForIndex($baseUrl, $index, 'nextcloud_gallery:'.$year.':'.sha1($baseUrl));

        if ($photoUrl === null) {
            return $this->placeholderResponse($year, $index);
        }

        try {
            $response = Http::timeout(10)->get($photoUrl);

            if ($response->successful()) {
                return response($response->body(), 200)
                    ->header('Content-Type', 'image/jpeg')
                    ->header('Cache-Control', 'public, max-age=86400'); // 1 Tag cachen
            }
        } catch (\Exception $e) {
            Log::error('Fehler beim Proxy-Aufruf: '.$e->getMessage());
        }

        return $this->placeholderResponse($year, $index);
    }

    private function placeholderResponse(string $year, int $index)
    {
        return response($this->placeholderSvg($year, $index), 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
