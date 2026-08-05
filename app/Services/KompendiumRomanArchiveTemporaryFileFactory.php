<?php

namespace App\Services;

use Throwable;

class KompendiumRomanArchiveTemporaryFileFactory
{
    private const MAX_ATTEMPTS = 10;

    public function create(string $directory): string
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new KompendiumRomanArchiveException(
                'Das temporäre ZIP-Verzeichnis konnte nicht angelegt werden.',
            );
        }

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $path = $this->candidatePath($directory);
            $handle = @fopen($path, 'x+b');

            if ($handle === false) {
                clearstatcache(true, $path);

                if (file_exists($path)) {
                    continue;
                }

                throw new KompendiumRomanArchiveException(
                    'Das temporäre ZIP-Archiv konnte nicht angelegt werden.',
                );
            }

            $permissionsSet = @chmod($path, 0600);
            $closed = @fclose($handle);

            if (! $permissionsSet || ! $closed) {
                @unlink($path);

                throw new KompendiumRomanArchiveException(
                    'Das temporäre ZIP-Archiv konnte nicht angelegt werden.',
                );
            }

            return $path;
        }

        throw new KompendiumRomanArchiveException(
            'Das temporäre ZIP-Archiv konnte nach mehreren Versuchen nicht angelegt werden.',
        );
    }

    protected function candidatePath(string $directory): string
    {
        try {
            $suffix = bin2hex(random_bytes(16));
        } catch (Throwable $exception) {
            throw new KompendiumRomanArchiveException(
                'Das temporäre ZIP-Archiv konnte nicht sicher benannt werden.',
                previous: $exception,
            );
        }

        return rtrim($directory, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .'romane-'.$suffix.'.zip';
    }
}
