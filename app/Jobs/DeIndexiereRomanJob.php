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
use Throwable;

/**
 * Job zum De-Indexieren eines Romans aus der Kompendium-Suche.
 *
 * Entfernt den Roman aus dem TNTSearch-Index, behält aber die Datei.
 */
class DeIndexiereRomanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Anzahl der Versuche bei Fehlern.
     */
    public int $tries = 3;

    /**
     * Timeout in Sekunden.
     */
    public int $timeout = 60;

    public function __construct(
        public KompendiumRoman $kompendiumRoman
    ) {}

    public function handle(): void
    {
        $roman = $this->kompendiumRoman;

        Log::info("De-Indexiere Roman: {$roman->titel} (Nr. {$roman->roman_nr})");

        // Aus Scout-Index entfernen
        $excerpt = new RomanExcerpt(['path' => $roman->dateipfad]);
        $excerpt->unsearchable();

        // Status zurücksetzen
        $roman->update([
            'status' => 'hochgeladen',
            'indexiert_am' => null,
        ]);

        Log::info("Roman erfolgreich de-indexiert: {$roman->titel}");
    }

    /**
     * Wird aufgerufen, wenn der Job endgültig fehlschlägt.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("De-Indexierung fehlgeschlagen für Roman {$this->kompendiumRoman->titel}: {$exception->getMessage()}");

        $this->kompendiumRoman->update([
            'fehler_nachricht' => 'De-Indexierung fehlgeschlagen: '.$exception->getMessage(),
        ]);
    }
}
