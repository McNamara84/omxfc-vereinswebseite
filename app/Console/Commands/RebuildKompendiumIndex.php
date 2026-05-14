<?php

namespace App\Console\Commands;

use App\Models\KompendiumRoman;
use App\Models\RomanExcerpt;
use App\Services\KompendiumSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RebuildKompendiumIndex extends Command
{
    protected $signature = 'kompendium:rebuild-index';

    protected $description = 'Baut den Kompendium-Suchindex aus vorhandenen Romanen neu auf, falls er fehlt';

    public function handle(KompendiumSearchService $searchService): int
    {
        if ($searchService->indexExists()) {
            $this->info('Index existiert bereits – kein Rebuild nötig.');

            return self::SUCCESS;
        }

        $romane = KompendiumRoman::query()->indexiert()->get();

        if ($romane->isEmpty()) {
            $this->info('Keine indexierten Romane in der Datenbank gefunden – nichts zu tun.');

            return self::SUCCESS;
        }

        $anzahl = $romane->count();
        $label = $anzahl === 1 ? 'Roman' : 'Romane';
        $this->info("Index fehlt – baue {$anzahl} {$label} neu auf …");

        $disk = Storage::disk('private');
        $bar = $this->output->createProgressBar($romane->count());
        $bar->start();

        $batch = collect();
        $fehler = 0;

        foreach ($romane as $roman) {
            if (! $disk->exists($roman->dateipfad)) {
                $this->newLine();
                $this->warn("Datei nicht gefunden: {$roman->dateipfad} – überspringe.");
                $roman->update(['status' => 'fehler', 'fehler_nachricht' => 'Datei nicht gefunden beim Index-Rebuild']);
                $fehler++;
                $bar->advance();

                continue;
            }

            $batch->push(new RomanExcerpt([
                'path' => $roman->dateipfad,
                'cycle' => $roman->zyklus,
                'roman_nr' => $roman->roman_nr,
                'title' => $roman->titel,
                'body' => $disk->get($roman->dateipfad),
            ]));

            if ($batch->count() === 250) {
                $batch->searchableSync();
                $batch = collect();
            }

            $bar->advance();
        }

        if ($batch->isNotEmpty()) {
            $batch->searchableSync();
        }

        $bar->finish();
        $this->newLine(2);

        if ($fehler > 0) {
            $fehlerLabel = $fehler === 1 ? 'Roman konnte' : 'Romane konnten';
            $this->warn("{$fehler} {$fehlerLabel} nicht indexiert werden (Datei fehlt).");
        }

        $this->info('Index-Rebuild abgeschlossen.');

        return self::SUCCESS;
    }
}
