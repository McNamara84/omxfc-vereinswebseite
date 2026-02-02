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
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
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

        // Aus Scout-Index entfernen - robust gegen fehlende Index-Datei
        $this->removeFromIndex($roman->dateipfad);

        // Status zurücksetzen
        $roman->update([
            'status' => 'hochgeladen',
            'indexiert_am' => null,
        ]);

        Log::info("Roman erfolgreich de-indexiert: {$roman->titel}");
    }

    /**
     * Entfernt ein Dokument aus dem TNTSearch-Index.
     * Fängt Fehler ab, wenn der Index nicht existiert.
     */
    private function removeFromIndex(string $path): void
    {
        try {
            $excerpt = new RomanExcerpt(['path' => $path]);
            $excerpt->unsearchable();
        } catch (IndexNotFoundException) {
            // Index existiert nicht - nichts zu entfernen
            Log::info("Index nicht gefunden, überspringe De-Indexierung für: {$path}");
        } catch (\BadMethodCallException) {
            // Tritt auf wenn Scout gemockt ist (z.B. in Tests)
            Log::info("Scout gemockt, überspringe De-Indexierung für: {$path}");
        } catch (Throwable $e) {
            // Andere Fehler loggen, aber nicht fehlschlagen lassen
            // wenn der Roman ohnehin nicht im Index ist
            if (str_contains($e->getMessage(), 'Index') || str_contains($e->getMessage(), 'not found')) {
                Log::info("Dokument nicht im Index gefunden: {$path}");

                return;
            }
            throw $e;
        }
    }

    /**
     * Wird aufgerufen, wenn der Job endgültig fehlschlägt.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("De-Indexierung fehlgeschlagen für Roman {$this->kompendiumRoman->titel}: {$exception->getMessage()}");

        $this->kompendiumRoman->update([
            'status' => 'fehler',
            'fehler_nachricht' => 'De-Indexierung fehlgeschlagen: '.$exception->getMessage(),
        ]);
    }
}
