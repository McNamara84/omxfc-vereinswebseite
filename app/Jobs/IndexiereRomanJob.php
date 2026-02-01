<?php

namespace App\Jobs;

use App\Models\KompendiumRoman;
use App\Models\RomanExcerpt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Job zum Indexieren eines Romans für die Kompendium-Suche.
 *
 * Liest die TXT-Datei ein und fügt sie dem TNTSearch-Index hinzu.
 */
class IndexiereRomanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Anzahl der Versuche bei Fehlern.
     */
    public int $tries = 3;

    /**
     * Timeout in Sekunden.
     */
    public int $timeout = 120;

    public function __construct(
        public KompendiumRoman $kompendiumRoman
    ) {}

    public function handle(): void
    {
        $roman = $this->kompendiumRoman;

        Log::info("Indexiere Roman: {$roman->titel} (Nr. {$roman->roman_nr})");

        // Status auf "läuft" setzen
        $roman->update(['status' => 'indexierung_laeuft']);

        // Datei lesen
        $inhalt = Storage::disk('private')->get($roman->dateipfad);

        if (! $inhalt) {
            throw new \RuntimeException("Datei nicht gefunden: {$roman->dateipfad}");
        }

        // RomanExcerpt für Scout erstellen und indexieren
        $excerpt = new RomanExcerpt([
            'path' => $roman->dateipfad,
            'cycle' => $roman->zyklus,
            'roman_nr' => $roman->roman_nr,
            'title' => $roman->titel,
            'body' => $inhalt,
        ]);

        $excerpt->searchable();

        // Status aktualisieren
        $roman->update([
            'status' => 'indexiert',
            'indexiert_am' => now(),
            'fehler_nachricht' => null,
        ]);

        Log::info("Roman erfolgreich indexiert: {$roman->titel}");
    }

    /**
     * Wird aufgerufen, wenn der Job endgültig fehlschlägt.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("Indexierung fehlgeschlagen für Roman {$this->kompendiumRoman->titel}: {$exception->getMessage()}");

        $this->kompendiumRoman->update([
            'status' => 'fehler',
            'fehler_nachricht' => $exception->getMessage(),
        ]);
    }
}
