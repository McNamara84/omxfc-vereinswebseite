<?php

namespace App\Services;

use Throwable;
use ZipArchive;

class KompendiumRomanArchiveWriter
{
    /**
     * @param  list<array{sourcePath: string, archivePath: string}>  $entries
     */
    public function write(string $archivePath, array $entries): void
    {
        $zip = new ZipArchive;
        $openResult = @$zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($openResult !== true) {
            throw new KompendiumRomanArchiveException('Das ZIP-Archiv konnte nicht erstellt werden.');
        }

        try {
            foreach ($entries as $entry) {
                if (! is_file($entry['sourcePath']) || ! is_readable($entry['sourcePath'])) {
                    throw new KompendiumRomanArchiveException('Eine Roman-Datei konnte nicht zum ZIP-Archiv hinzugefügt werden.');
                }

                if (! @$zip->addFile($entry['sourcePath'], $entry['archivePath'])) {
                    throw new KompendiumRomanArchiveException('Eine Roman-Datei konnte nicht zum ZIP-Archiv hinzugefügt werden.');
                }
            }

            if (! @$zip->close()) {
                throw new KompendiumRomanArchiveException('Das ZIP-Archiv konnte nicht abgeschlossen werden.');
            }
        } catch (Throwable $exception) {
            try {
                @$zip->close();
            } catch (Throwable) {
                // Das unvollständige Archiv wird vom aufrufenden Service entfernt.
            }

            if ($exception instanceof KompendiumRomanArchiveException) {
                throw $exception;
            }

            throw new KompendiumRomanArchiveException(
                'Das ZIP-Archiv konnte nicht erstellt werden.',
                previous: $exception,
            );
        }
    }
}
