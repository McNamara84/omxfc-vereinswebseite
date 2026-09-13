<?php

namespace App\Services\Maddraxikon;

use App\Exceptions\MaddraxikonCrawlException;
use Illuminate\Filesystem\Filesystem;
use Throwable;

class AtomicFileWriter
{
    private const MAX_ATTEMPTS = 10;

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    public function write(string $path, string $contents, int $mode = 0600): void
    {
        $directory = dirname($path);
        $this->files->ensureDirectoryExists($directory, 0700);
        $temporaryPath = null;
        $handle = null;

        try {
            [$temporaryPath, $handle] = $this->createTemporaryFile($directory, basename($path));
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

            if (! @chmod($temporaryPath, $mode)) {
                throw new MaddraxikonCrawlException(
                    "Dateirechte für den Kandidaten {$path} konnten nicht gesetzt werden."
                );
            }

            if (! @fclose($handle)) {
                throw new MaddraxikonCrawlException(
                    "Kandidat für {$path} konnte nicht geschlossen werden."
                );
            }

            $handle = null;

            if (! @rename($temporaryPath, $path)) {
                throw new MaddraxikonCrawlException(
                    "Kandidat konnte nicht atomar auf {$path} freigegeben werden."
                );
            }
        } finally {
            if (is_resource($handle)) {
                @fclose($handle);
            }

            if ($temporaryPath !== null && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /** @return array{0: string, 1: resource} */
    private function createTemporaryFile(string $directory, string $targetName): array
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $path = $this->candidatePath($directory, $targetName);
            $handle = @fopen($path, 'xb');

            if ($handle !== false) {
                return [$path, $handle];
            }

            clearstatcache(true, $path);

            if (file_exists($path)) {
                continue;
            }

            throw new MaddraxikonCrawlException(
                "Temporäre Datei für {$targetName} konnte nicht angelegt werden."
            );
        }

        throw new MaddraxikonCrawlException(
            "Temporäre Datei für {$targetName} konnte nach mehreren Versuchen nicht angelegt werden."
        );
    }

    protected function candidatePath(string $directory, string $targetName): string
    {
        try {
            $suffix = bin2hex(random_bytes(16));
        } catch (Throwable $exception) {
            throw new MaddraxikonCrawlException(
                "Temporäre Datei für {$targetName} konnte nicht sicher benannt werden.",
                previous: $exception,
            );
        }

        return rtrim($directory, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .$targetName.'.candidate.'.$suffix;
    }
}
