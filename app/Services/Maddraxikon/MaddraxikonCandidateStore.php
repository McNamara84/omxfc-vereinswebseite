<?php

namespace App\Services\Maddraxikon;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

class MaddraxikonCandidateStore
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly MaddraxikonDatasetValidator $validator,
        private readonly AtomicFileWriter $writer,
    ) {}

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $datasets
     * @return array{id: string, hash: string, manifest: array<string, mixed>}
     */
    public function stage(array $datasets): array
    {
        if ($datasets === []) {
            throw new MaddraxikonCrawlException('Es wurden keine Kandidatendaten erzeugt.');
        }

        $id = (string) Str::uuid();
        $directory = $this->candidateDirectory($id);
        $this->files->ensureDirectoryExists($directory, 0700);
        $manifestSeries = [];

        try {
            foreach ($datasets as $seriesKey => $rows) {
                $type = BookType::fromKey($seriesKey);

                if ($type === null) {
                    throw new MaddraxikonCrawlException("Unbekannte Buchreihe: {$seriesKey}");
                }

                $baseline = $this->activeDataset($type);
                $this->validator->validate($rows, $type, $baseline);
                $json = $this->encode($rows);
                $filename = "{$seriesKey}.json";
                $path = $directory.DIRECTORY_SEPARATOR.$filename;
                $this->writer->write($path, $json);
                $this->assertCandidateFile($path, $rows, $type, $baseline);

                $manifestSeries[$seriesKey] = [
                    'type' => $type->value,
                    'filename' => $filename,
                    'sha256' => hash_file('sha256', $path),
                    'count' => count($rows),
                    'highest_number' => max(array_column($rows, 'nummer')),
                ];
            }

            $manifest = [
                'id' => $id,
                'created_at' => now()->toIso8601String(),
                'expires_at' => now()->addMinutes(max(
                    1,
                    (int) config('maddraxikon.crawler.candidate_ttl_minutes', 360)
                ))->toIso8601String(),
                'series' => $manifestSeries,
            ];
            $manifestPath = $directory.DIRECTORY_SEPARATOR.'manifest.json';
            $this->writer->write($manifestPath, $this->encode($manifest));

            return [
                'id' => $id,
                'hash' => (string) hash_file('sha256', $manifestPath),
                'manifest' => $manifest,
            ];
        } catch (\Throwable $exception) {
            $this->files->deleteDirectory($directory);

            throw $exception;
        }
    }

    /**
     * @return array{
     *     manifest: array<string, mixed>,
     *     datasets: array<string, array<int, array<string, mixed>>>,
     *     json: array<string, string>
     * }
     */
    public function load(string $id): array
    {
        $this->assertCandidateId($id);
        $directory = $this->candidateDirectory($id);
        $manifestPath = $directory.DIRECTORY_SEPARATOR.'manifest.json';

        if (! $this->files->isFile($manifestPath)) {
            throw new MaddraxikonCrawlException("Kandidat {$id} wurde nicht gefunden.");
        }

        $manifest = $this->decode($this->files->get($manifestPath), 'Kandidatenmanifest');
        $expiresAt = isset($manifest['expires_at'])
            ? Carbon::parse((string) $manifest['expires_at'])
            : null;

        if ($expiresAt === null || $expiresAt->isPast()) {
            throw new MaddraxikonCrawlException("Kandidat {$id} ist abgelaufen.");
        }

        $series = $manifest['series'] ?? null;

        if (! is_array($series) || $series === []) {
            throw new MaddraxikonCrawlException("Kandidat {$id} enthält keine Reihen.");
        }

        $datasets = [];
        $jsonBySeries = [];

        foreach ($series as $seriesKey => $metadata) {
            $type = is_string($seriesKey) ? BookType::fromKey($seriesKey) : null;

            if ($type === null || ! is_array($metadata)) {
                throw new MaddraxikonCrawlException("Kandidat {$id} enthält eine unbekannte Reihe.");
            }

            $filename = $metadata['filename'] ?? null;

            if (! is_string($filename) || $filename !== "{$seriesKey}.json") {
                throw new MaddraxikonCrawlException("Kandidat {$id} enthält einen ungültigen Dateinamen.");
            }

            $path = $directory.DIRECTORY_SEPARATOR.$filename;

            if (! $this->files->isFile($path)) {
                throw new MaddraxikonCrawlException("Kandidatendatei {$filename} fehlt.");
            }

            $actualHash = hash_file('sha256', $path);

            if (! is_string($metadata['sha256'] ?? null) || ! hash_equals($metadata['sha256'], $actualHash)) {
                throw new MaddraxikonCrawlException("Hashprüfung für {$filename} ist fehlgeschlagen.");
            }

            $json = $this->files->get($path);
            $rows = $this->decode($json, $filename);
            $baseline = $this->activeDataset($type);
            $this->validator->validate($rows, $type, $baseline);
            $datasets[$seriesKey] = $rows;
            $jsonBySeries[$seriesKey] = $json;
        }

        return [
            'manifest' => $manifest,
            'datasets' => $datasets,
            'json' => $jsonBySeries,
        ];
    }

    private function assertCandidateId(string $id): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id) !== 1) {
            throw new MaddraxikonCrawlException('Ungültige Kandidaten-ID.');
        }
    }

    private function candidateDirectory(string $id): string
    {
        return Storage::disk('private')->path('maddrax-candidates/'.$id);
    }

    /** @return array<int, mixed>|null */
    private function activeDataset(BookType $type): ?array
    {
        $path = Storage::disk('private')->path(MaddraxikonSeries::filename($type));

        if (! $this->files->isFile($path)) {
            return null;
        }

        try {
            return $this->decode($this->files->get($path), 'aktiver Snapshot');
        } catch (MaddraxikonCrawlException) {
            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $expectedRows
     * @param  array<int, mixed>|null  $baseline
     */
    private function assertCandidateFile(
        string $path,
        array $expectedRows,
        BookType $type,
        ?array $baseline,
    ): void {
        $rows = $this->decode($this->files->get($path), basename($path));
        $this->validator->validate($rows, $type, $baseline);

        if ($rows !== $expectedRows) {
            throw new MaddraxikonCrawlException(
                'Kandidat stimmt nach dem Schreiben nicht mit den validierten Daten überein.'
            );
        }
    }

    private function encode(mixed $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).PHP_EOL;
        } catch (JsonException $exception) {
            throw new MaddraxikonCrawlException(
                'Kandidat konnte nicht als JSON kodiert werden: '.$exception->getMessage()
            );
        }
    }

    /** @return array<int|string, mixed> */
    private function decode(string $json, string $source): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MaddraxikonCrawlException(
                "{$source} enthält ungültiges JSON: {$exception->getMessage()}"
            );
        }

        if (! is_array($data)) {
            throw new MaddraxikonCrawlException("{$source} enthält keine JSON-Liste.");
        }

        return $data;
    }
}
