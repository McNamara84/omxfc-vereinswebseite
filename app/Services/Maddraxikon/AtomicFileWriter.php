<?php

namespace App\Services\Maddraxikon;

use App\Exceptions\MaddraxikonCrawlException;
use Illuminate\Filesystem\Filesystem;

class AtomicFileWriter
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    public function write(string $path, string $contents, int $mode = 0600): void
    {
        $directory = dirname($path);
        $this->files->ensureDirectoryExists($directory, 0700);
        $temporaryPath = tempnam($directory, basename($path).'.candidate.');

        if ($temporaryPath === false) {
            throw new MaddraxikonCrawlException(
                "Temporäre Datei für {$path} konnte nicht angelegt werden."
            );
        }

        try {
            $handle = fopen($temporaryPath, 'wb');

            if ($handle === false) {
                throw new MaddraxikonCrawlException(
                    "Temporäre Datei für {$path} konnte nicht geöffnet werden."
                );
            }

            try {
                $remaining = $contents;

                while ($remaining !== '') {
                    $written = fwrite($handle, $remaining);

                    if ($written === false || $written === 0) {
                        throw new MaddraxikonCrawlException(
                            "Kandidat für {$path} konnte nicht vollständig geschrieben werden."
                        );
                    }

                    $remaining = substr($remaining, $written);
                }

                if (! fflush($handle)) {
                    throw new MaddraxikonCrawlException(
                        "Kandidat für {$path} konnte nicht auf Datenträger geschrieben werden."
                    );
                }

                if (function_exists('fsync') && ! fsync($handle)) {
                    throw new MaddraxikonCrawlException(
                        "Kandidat für {$path} konnte nicht synchronisiert werden."
                    );
                }
            } finally {
                fclose($handle);
            }

            if (! @chmod($temporaryPath, $mode)) {
                throw new MaddraxikonCrawlException(
                    "Dateirechte für den Kandidaten {$path} konnten nicht gesetzt werden."
                );
            }

            if (! @rename($temporaryPath, $path)) {
                throw new MaddraxikonCrawlException(
                    "Kandidat konnte nicht atomar auf {$path} freigegeben werden."
                );
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }
}
