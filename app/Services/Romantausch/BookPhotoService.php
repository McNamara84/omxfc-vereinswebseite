<?php

namespace App\Services\Romantausch;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service für die Verwaltung von Buch-Fotos in der Romantauschbörse.
 *
 * Verantwortlich für:
 * - Sicherer Upload von Fotos mit MIME-Type-Validierung
 * - Löschen von Fotos aus dem Storage
 * - Aktualisieren von Foto-Collections (Behalten/Entfernen/Hinzufügen)
 */
class BookPhotoService
{
    /**
     * Erlaubte Dateiendungen für Foto-Uploads.
     */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Maximale Anzahl Fotos pro Angebot/Bundle.
     */
    public const MAX_PHOTOS = 3;

    /**
     * Maximale Dateigröße in KB.
     */
    public const MAX_FILE_SIZE_KB = 2048;

    /**
     * Storage-Verzeichnis für Bundle-Fotos.
     */
    public const STORAGE_PATH = 'book-offers';

    /**
     * Sichere Mapping von MIME-Types zu Extensions.
     * Verhindert das Hochladen von ausführbaren Dateien.
     */
    private const MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Lädt Fotos aus einem Request hoch.
     *
     * @param  Request  $request  Der HTTP-Request mit 'photos' Feld
     * @param  int|null  $userId  User-ID für Logging (optional, verwendet Auth::id() als Fallback)
     * @return array<string> Array mit Storage-Pfaden der hochgeladenen Fotos
     *
     * @throws \RuntimeException Wenn der Upload fehlschlägt
     */
    public function uploadPhotosFromRequest(Request $request, ?int $userId = null): array
    {
        if (! $request->hasFile('photos')) {
            return [];
        }

        $userId = $userId ?? auth()->id();

        return $this->uploadPhotos($request->file('photos'), $userId);
    }

    /**
     * Lädt ein Array von Fotos hoch.
     *
     * @param  array<UploadedFile|null>  $photos  Array von UploadedFile-Objekten
     * @param  int|null  $userId  User-ID für Logging
     * @return array<string> Array mit Storage-Pfaden
     *
     * @throws \RuntimeException Wenn der Upload fehlschlägt
     */
    public function uploadPhotos(array $photos, ?int $userId = null): array
    {
        $photoPaths = [];
        $photoIndex = 0;

        foreach ($photos as $photo) {
            if (! $photo instanceof UploadedFile) {
                continue;
            }
            $photoIndex++;

            try {
                $path = $this->uploadSinglePhoto($photo, $photoIndex, $userId);
                if ($path !== null) {
                    $photoPaths[] = $path;
                }
            } catch (\Throwable $e) {
                // Bereits hochgeladene Fotos aufräumen
                $this->deletePhotos($photoPaths);

                Log::error('Foto-Upload fehlgeschlagen', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                throw new \RuntimeException('Foto-Upload fehlgeschlagen.', 0, $e);
            }
        }

        return $photoPaths;
    }

    /**
     * Lädt ein einzelnes Foto sicher hoch.
     *
     * @param  UploadedFile  $photo  Das hochzuladende Foto
     * @param  int  $index  Index für Fallback-Dateiname
     * @param  int|null  $userId  User-ID für Logging
     * @return string|null Storage-Pfad oder null wenn Foto ungültig
     */
    private function uploadSinglePhoto(UploadedFile $photo, int $index, ?int $userId): ?string
    {
        // SICHERHEIT: Extension wird vom MIME-Type abgeleitet, nicht vom Client!
        $mimeType = $photo->getMimeType();
        $extension = self::MIME_TO_EXTENSION[$mimeType] ?? null;

        if ($extension === null) {
            Log::warning('Foto-Upload: Nicht erlaubter MIME-Type', [
                'user_id' => $userId,
                'mime_type' => $mimeType,
                'original_name' => $photo->getClientOriginalName(),
            ]);

            return null;
        }

        // Zusätzliche Sicherheitsebene: Prüfe ob Datei echtes Bild ist
        if (! $this->isValidImage($photo, $userId)) {
            return null;
        }

        $filename = $this->generateFilename($photo, $index, $extension);

        return $photo->storeAs(self::STORAGE_PATH, $filename, 'public');
    }

    /**
     * Prüft ob eine Datei ein echtes Bild ist.
     *
     * getimagesize() liest die Bild-Header und erkennt Polyglot-Dateien
     * (z.B. PHP-Code mit gültigem JPEG-Header).
     */
    private function isValidImage(UploadedFile $photo, ?int $userId): bool
    {
        try {
            $imageInfo = getimagesize($photo->getRealPath());
        } catch (\Throwable $e) {
            Log::warning('Foto-Upload: getimagesize fehlgeschlagen', [
                'user_id' => $userId,
                'original_name' => $photo->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($imageInfo === false) {
            Log::warning('Foto-Upload: Keine gültige Bilddatei', [
                'user_id' => $userId,
                'mime_type' => $photo->getMimeType(),
                'original_name' => $photo->getClientOriginalName(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Generiert einen sicheren Dateinamen mit UUID.
     */
    private function generateFilename(UploadedFile $photo, int $index, string $extension): string
    {
        $name = Str::slug(pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME));
        if ($name === '') {
            $name = 'photo-'.$index;
        }

        return $name.'-'.Str::uuid().'.'.$extension;
    }

    /**
     * Löscht eine Liste von Fotos aus dem Storage.
     *
     * @param  array<string>  $paths  Array mit Storage-Pfaden
     */
    public function deletePhotos(array $paths): void
    {
        foreach ($paths as $path) {
            $this->deletePhoto($path);
        }
    }

    /**
     * Löscht ein einzelnes Foto aus dem Storage.
     *
     * @param  string  $path  Storage-Pfad des Fotos
     */
    public function deletePhoto(string $path): void
    {
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            Log::warning('Foto-Cleanup fehlgeschlagen', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Aktualisiert eine Foto-Collection: Behält existierende, entfernt markierte, fügt neue hinzu.
     *
     * @param  array<string>  $existingPhotos  Aktuell gespeicherte Foto-Pfade
     * @param  array<string>  $photosToRemove  Pfade der zu löschenden Fotos
     * @param  Request  $request  Request mit neuen Fotos
     * @param  int|null  $userId  User-ID für Logging
     * @return array{photos: array<string>, deleted: array<string>} Neue Foto-Liste und gelöschte Pfade
     *
     * @throws \RuntimeException Wenn der Upload neuer Fotos fehlschlägt
     */
    public function updatePhotos(
        array $existingPhotos,
        array $photosToRemove,
        Request $request,
        ?int $userId = null
    ): array {
        $existingCollection = collect($existingPhotos);
        $removeCollection = collect($photosToRemove);

        // Fotos die behalten werden
        $photosToKeep = $existingCollection
            ->reject(fn ($path) => $removeCollection->contains($path))
            ->values()
            ->toArray();

        // Tatsächlich zu löschende Fotos (nur die, die auch existieren)
        $photosToDelete = $removeCollection
            ->filter(fn ($path) => $existingCollection->contains($path))
            ->values()
            ->toArray();

        // Neue Fotos hochladen
        $newPhotoPaths = $this->uploadPhotosFromRequest($request, $userId);

        return [
            'photos' => array_merge($photosToKeep, $newPhotoPaths),
            'deleted' => $photosToDelete,
        ];
    }

    /**
     * Validiert ob die Gesamtanzahl der Fotos das Maximum nicht überschreitet.
     *
     * @param  array<string>  $existingPhotos  Vorhandene Fotos
     * @param  array<string>  $photosToRemove  Zu entfernende Fotos
     * @param  int  $newPhotoCount  Anzahl neuer Fotos
     * @return bool True wenn gültig
     */
    public function validatePhotoCount(
        array $existingPhotos,
        array $photosToRemove,
        int $newPhotoCount
    ): bool {
        $remainingCount = collect($existingPhotos)
            ->reject(fn ($path) => in_array($path, $photosToRemove, true))
            ->count();

        return ($remainingCount + $newPhotoCount) <= self::MAX_PHOTOS;
    }

    /**
     * Gibt die Validierungsregeln für Foto-Uploads zurück.
     *
     * @return array<string, string>
     */
    public static function getValidationRules(): array
    {
        $extensions = implode(',', self::ALLOWED_EXTENSIONS);

        return [
            'photos' => 'nullable|array|max:'.self::MAX_PHOTOS,
            'photos.*' => 'file|max:'.self::MAX_FILE_SIZE_KB.'|mimes:'.$extensions,
            'remove_photos' => 'nullable|array',
            'remove_photos.*' => 'string',
        ];
    }
}
