<?php

namespace App\Console\Commands;

use App\Models\KompendiumRoman;
use App\Models\RomanExcerpt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RebuildKompendiumIndex extends Command
{
    protected $signature = 'kompendium:rebuild-index';

    protected $description = 'Baut den Kompendium-Suchindex aus vorhandenen Romanen neu auf, falls er fehlt';

    public function handle(): int
    {
        if (config('scout.driver') !== 'tntsearch') {
            $this->warn('Scout-Driver ist nicht tntsearch – Rebuild nicht möglich.');

            return self::FAILURE;
        }

        $indexName = (new RomanExcerpt)->searchableAs();
        $indexPath = config('scout.tntsearch.storage').DIRECTORY_SEPARATOR.$indexName.'.index';

        if (file_exists($indexPath)) {
            $this->info('Index existiert bereits – kein Rebuild nötig.');

            return self::SUCCESS;
        }

        $romane = KompendiumRoman::where('status', 'indexiert')->get();

        if ($romane->isEmpty()) {
            $this->info('Keine indexierten Romane in der Datenbank gefunden – nichts zu tun.');

            return self::SUCCESS;
        }

        $this->info("Index fehlt – baue {$romane->count()} Romane neu auf …");

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
                $batch->searchable();
                $batch = collect();
            }

            $bar->advance();
        }

        if ($batch->isNotEmpty()) {
            $batch->searchable();
        }

        $bar->finish();
        $this->newLine(2);

        if ($fehler > 0) {
            $this->warn("{$fehler} Roman(e) konnten nicht indexiert werden (Datei fehlt).");
        }

        $this->info('Index-Rebuild abgeschlossen.');

        return self::SUCCESS;
    }
}
