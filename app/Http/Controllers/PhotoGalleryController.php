<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class PhotoGalleryController extends Controller
{
    public function index()
    {
        // Jahre für die Tabs definieren
        $years = ['2025', '2024', '2023'];
        // Standardmäßig das aktuellste Jahr anzeigen
        $activeYear = $years[0];

        // Fotos für jedes Jahr laden
        $photos = [
            '2025' => $this->getPhotosForYear('2025'),
            '2024' => $this->getPhotosForYear('2024'),
            '2023' => $this->getPhotosForYear('2023'),
        ];

        return view('pages.fotogalerie', compact('years', 'activeYear', 'photos'));
    }

    /**
     * Lädt Fotos für ein bestimmtes Jahr
     */
    private function getPhotosForYear($year)
    {
        // Basis-URLs für die öffentlichen Freigaben
        $baseUrls = [
            '2025' => 'https://cloud.maddrax-fanclub.de/public.php/dav/files/jnGa6sEecKa3fiX/Foto',
            '2024' => 'https://cloud.maddrax-fanclub.de/public.php/dav/files/tztWY5ML5XMRWPw/Foto', // Anpassen
            '2023' => 'https://cloud.maddrax-fanclub.de/public.php/dav/files/jjpfnJbgStE8LcQ/Foto', // Anpassen
        ];

        $photoUrls = [];

        // Basis-URL für das Jahr
        $baseUrl = $baseUrls[$year] ?? '';

        if (empty($baseUrl)) {
            return $this->getFallbackPhotos($year);
        }

        // Fotos mit einer Schleife laden und abbrechen, wenn ein Foto nicht existiert
        $maxTries = 5; // Sicherheitslimit
        $index = 1;

        while ($index <= $maxTries) {
            $photoUrl = $baseUrl.$index.'.jpg';

            if ($this->photoExists($photoUrl)) {
                $photoUrls[] = $photoUrl;
                $index++;
            } else {
                // Wenn ein Foto nicht existiert, brechen wir die Schleife ab
                break;
            }
        }

        // Cache die Anzahl der Fotos für zukünftige Anfragen
        // (optional - könnte in einer Datenbank oder Cache gespeichert werden)
        // Cache::put('photo_count_' . $year, count($photoUrls), now()->addDay());

        // Wenn keine Fotos gefunden wurden, Fallback verwenden
        if (empty($photoUrls)) {
            return $this->getFallbackPhotos($year);
        }

        return $photoUrls;
    }

    /**
     * Prüft, ob ein Foto unter der angegebenen URL existiert
     */
    private function photoExists($url)
    {
        try {
            $response = Http::timeout(5)->head($url);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Fehler beim Prüfen des Fotos: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Gibt Fallback-Fotos zurück, falls keine Nextcloud-Fotos gefunden wurden
     */
    private function getFallbackPhotos($year)
    {
        // Hier könntest du lokale Bilder als Fallback verwenden
        return [
            asset('images/galerie/'.$year.'/placeholder1.jpg'),
            asset('images/galerie/'.$year.'/placeholder2.jpg'),
        ];
    }

    /**
     * Proxy für Bilder, um mögliche CORS-Probleme zu umgehen
     */
    public function proxyImage($year, $index)
    {
        // Basis-URLs für die öffentlichen Freigaben
        $baseUrls = [
            '2025' => 'https://cloud.maddrax-fanclub.de/public.php/dav/files/jnGa6sEecKa3fiX/Foto',
            '2024' => 'https://cloud.maddrax-fanclub.de/public.php/dav/files/tztWY5ML5XMRWPw/Foto',
            '2023' => 'https://cloud.maddrax-fanclub.de/public.php/dav/files/jjpfnJbgStE8LcQ/Foto',
        ];

        $baseUrl = $baseUrls[$year] ?? '';
        if (empty($baseUrl)) {
            return response()->file(public_path("images/galerie/{$year}/placeholder1.jpg"));
        }

        $photoUrl = $baseUrl.$index.'.jpg';

        try {
            $response = Http::timeout(10)->get($photoUrl);

            if ($response->successful()) {
                return response($response->body(), 200)
                    ->header('Content-Type', 'image/jpeg')
                    ->header('Cache-Control', 'public, max-age=86400'); // 1 Tag cachen
            }
        } catch (\Exception $e) {
            \Log::error('Fehler beim Proxy-Aufruf: '.$e->getMessage());
        }

        // Fallback, wenn das Bild nicht geladen werden konnte
        return response()->file(public_path("images/galerie/{$year}/placeholder1.jpg"));
    }
}
