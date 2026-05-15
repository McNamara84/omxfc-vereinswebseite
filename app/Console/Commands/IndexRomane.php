<?php

namespace App\Console\Commands;

use App\Models\RomanExcerpt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IndexRomane extends Command
{
    private const BATCH_SIZE = 25;

    /** artisan romane:index  */
    protected $signature = 'romane:index {--fresh : löscht vorhandenen Index zuerst}';

    protected $description = 'Scant alle TXT-Dateien und (re-)indexiert sie via Laravel Scout';

    public function handle(): int
    {
        $disk = Storage::disk('private');
        $this->info('Suche nach Romanen …');

        $txt = collect($disk->allFiles('romane'))
            ->filter(fn ($p) => Str::endsWith($p, '.txt'));

        if ($txt->isEmpty()) {
            $this->error('Keine TXT-Dateien gefunden.');

            return 1;
        }

        if ($this->option('fresh')) {
            $this->callSilent('scout:flush', ['model' => RomanExcerpt::class]);
            $this->info('Alter Index geleert.');
        }

        /** @var array<int, string> $dateipfade */
        $dateipfade = $txt->values()->all();

        /** @var Collection<RomanExcerpt> $batch */
        $batch = collect();

        $this->withProgressBar($dateipfade, function (string $path) use (&$batch, $disk): void {
            [$cycle, $romanNr, $title] = $this->metaFromPath($path);

            $batch->push(new RomanExcerpt([
                'path' => $path,          // <- Primärschlüssel sitzt
                'cycle' => $cycle,
                'roman_nr' => $romanNr,
                'title' => $title,
                'body' => $disk->get($path),
            ]));

            // RomanExcerpt ist kein persistiertes Eloquent-Modell und muss daher synchron indiziert werden.
            if ($batch->count() >= self::BATCH_SIZE) {
                $batch->searchableSync();
                $batch = collect();
                gc_collect_cycles();
            }
        });

        // Rest flushen
        if ($batch->isNotEmpty()) {
            $batch->searchableSync();
            gc_collect_cycles();
        }

        $this->newLine(2);
        $this->info('Indexierung abgeschlossen.');

        return 0;
    }

    private function metaFromPath(string $path): array
    {
        $parts = preg_split('/[\\\\\/]+/', $path);   // beide Slash-Typen
        $cycle = $parts[1] ?? 'unknown';
        $filename = pathinfo($path, PATHINFO_FILENAME);

        if (! preg_match('/^(\d+)\s*-\s*(.+)$/', $filename, $matches)) {
            Log::warning("IndexRomane: Dateiname '{$filename}' entspricht nicht dem erwarteten Format '001 - Titel'.", [
                'path' => $path,
            ]);

            return [$cycle, null, $filename];
        }

        return [$cycle, $matches[1], trim($matches[2])];
    }
}
