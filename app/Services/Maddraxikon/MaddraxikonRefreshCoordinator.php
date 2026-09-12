<?php

namespace App\Services\Maddraxikon;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Services\MaddraxDataService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MaddraxikonRefreshCoordinator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly MaddraxikonCandidateStore $candidates,
        private readonly MaddraxikonBookImporter $importer,
        private readonly MaddraxDataService $dataService,
    ) {}

    /** @return array<string, int> */
    public function promote(string $candidateId): array
    {
        $candidate = $this->candidates->load($candidateId);
        $originals = [];
        $updatedPaths = [];

        foreach ($candidate['datasets'] as $seriesKey => $rows) {
            $type = BookType::fromKey($seriesKey);

            if ($type === null) {
                throw new MaddraxikonCrawlException("Unbekannte Buchreihe: {$seriesKey}");
            }

            $path = Storage::disk('private')->path(MaddraxikonSeries::filename($type));
            $originals[$path] = $this->files->isFile($path)
                ? $this->files->get($path)
                : null;
        }

        try {
            DB::beginTransaction();
            $this->importer->import($candidate['datasets'], false);

            foreach ($candidate['json'] as $seriesKey => $json) {
                $type = BookType::fromKey($seriesKey);

                if ($type === null) {
                    throw new MaddraxikonCrawlException("Unbekannte Buchreihe: {$seriesKey}");
                }

                $path = Storage::disk('private')->path(MaddraxikonSeries::filename($type));
                $previous = $path.'.previous';

                if ($originals[$path] !== null) {
                    $this->files->replace($previous, $originals[$path], 0600);
                    @chmod($previous, 0600);
                }

                $this->files->replace($path, $json, 0600);
                @chmod($path, 0600);
                $updatedPaths[] = $path;
            }

            DB::commit();
        } catch (\Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->restoreFiles($updatedPaths, $originals, $exception);

            throw $exception;
        }

        foreach (array_keys($candidate['datasets']) as $seriesKey) {
            $this->dataService->clearCache($seriesKey);
        }
        $this->dataService->clearCache();

        return collect($candidate['datasets'])
            ->map(static fn (array $rows): int => count($rows))
            ->all();
    }

    /**
     * @param  list<string>  $updatedPaths
     * @param  array<string, string|null>  $originals
     */
    private function restoreFiles(array $updatedPaths, array $originals, \Throwable $cause): void
    {
        $restoreErrors = [];

        foreach (array_reverse($updatedPaths) as $path) {
            try {
                if ($originals[$path] === null) {
                    $this->files->delete($path);
                } else {
                    $this->files->replace($path, $originals[$path], 0600);
                    @chmod($path, 0600);
                }
            } catch (\Throwable $exception) {
                $restoreErrors[] = "{$path}: {$exception->getMessage()}";
            }
        }

        if ($restoreErrors !== []) {
            Log::critical('Maddraxikon-Dateien konnten nach fehlgeschlagener Freigabe nicht wiederhergestellt werden.', [
                'cause' => $cause->getMessage(),
                'restore_errors' => $restoreErrors,
            ]);

            throw new MaddraxikonCrawlException(
                'Kritischer Fehler bei der Wiederherstellung der Maddraxikon-Dateien: '.
                implode('; ', $restoreErrors)
            );
        }
    }
}
