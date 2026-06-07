<?php

namespace App\Console\Commands;

use App\Models\KompendiumRoman;
use App\Services\KompendiumService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillKompendiumPublicationDates extends Command
{
    protected $signature = 'kompendium:backfill-publication-dates {--dry-run : Zeigt nur, wie viele Datensaetze aktualisiert wuerden}';

    protected $description = 'Befuellt Erstveroeffentlichungsdaten im Kompendium aus den Serien-Metadaten';

    public function handle(KompendiumService $kompendiumService): int
    {
        $updated = 0;
        $missing = 0;
        $unchanged = 0;
        $dryRun = (bool) $this->option('dry-run');

        KompendiumRoman::query()
            ->select(['id', 'serie', 'roman_nr', 'titel', 'erstveroeffentlicht_am'])
            ->orderBy('id')
            ->chunkById(100, function ($romane) use ($kompendiumService, $dryRun, &$updated, &$missing, &$unchanged): void {
                foreach ($romane as $roman) {
                    $date = $kompendiumService->findeErstveroeffentlichtAm(
                        $roman->serie,
                        $roman->roman_nr,
                        $roman->titel,
                    );

                    if ($date === null) {
                        $missing++;

                        continue;
                    }

                    $dateString = $date->toDateString();

                    if ($roman->erstveroeffentlicht_am?->toDateString() === $dateString) {
                        $unchanged++;

                        continue;
                    }

                    $updated++;

                    if (! $dryRun) {
                        DB::table('kompendium_romane')
                            ->where('id', $roman->id)
                            ->update(['erstveroeffentlicht_am' => $dateString]);
                    }
                }
            });

        $prefix = $dryRun ? 'Dry run: ' : '';
        $this->info($prefix."{$updated} aktualisiert, {$unchanged} unveraendert, {$missing} ohne Datum.");

        return self::SUCCESS;
    }
}
