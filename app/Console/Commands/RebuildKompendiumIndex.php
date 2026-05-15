<?php

namespace App\Console\Commands;

use App\Models\KompendiumRoman;
use App\Models\RomanExcerpt;
use App\Services\KompendiumSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RebuildKompendiumIndex extends Command
{
    private const BATCH_SIZE = 25;

    protected $signature = 'kompendium:rebuild-index';

    protected $description = 'Baut den Kompendium-Suchindex aus vorhandenen Romanen neu auf, falls er fehlt';

    public function handle(KompendiumSearchService $searchService): int
    {
        if ($searchService->indexExists()) {
            $this->info('Index existiert bereits – kein Rebuild nötig.');

            return 0;
        }

        $romane = KompendiumRoman::query()->indexiert()->get();

        if ($romane->isEmpty()) {
            $this->info('Keine indexierten Romane in der Datenbank gefunden – nichts zu tun.');

            return 0;
        }

        $anzahl = $romane->count();
        $label = $anzahl === 1 ? 'Roman' : 'Romane';
        $this->info("Index fehlt – baue {$anzahl} {$label} neu auf …");

        $disk = Storage::disk('private');

        /** @var array<int, KompendiumRoman> $indexierteRomane */
        $indexierteRomane = $romane->all();

        $batch = collect();
        $fehler = 0;

        $this->withProgressBar($indexierteRomane, function (KompendiumRoman $roman) use (&$batch, &$fehler, $disk): void {
            if (! $disk->exists($roman->dateipfad)) {
                $this->newLine();
                $this->warn("Datei nicht gefunden: {$roman->dateipfad} – überspringe.");
                $roman->update(['status' => 'fehler', 'fehler_nachricht' => 'Datei nicht gefunden beim Index-Rebuild']);
                $fehler++;

                return;
            }

            $batch->push(new RomanExcerpt([
                'path' => $roman->dateipfad,
                'cycle' => $roman->zyklus,
                'roman_nr' => $roman->roman_nr,
                'title' => $roman->titel,
                'body' => $disk->get($roman->dateipfad),
            ]));

            if ($batch->count() >= self::BATCH_SIZE) {
                $batch->searchableSync();
                $batch = collect();
                gc_collect_cycles();
            }
        });

        if ($batch->isNotEmpty()) {
            $batch->searchableSync();
            gc_collect_cycles();
        }

        $this->newLine(2);

        if ($fehler > 0) {
            $fehlerLabel = $fehler === 1 ? 'Roman konnte' : 'Romane konnten';
            $this->warn("{$fehler} {$fehlerLabel} nicht indexiert werden (Datei fehlt).");
        }

        $this->info('Index-Rebuild abgeschlossen.');

        return 0;
    }
}
