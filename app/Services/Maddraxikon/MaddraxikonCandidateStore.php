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
    private const BASELINE_FILE = 'legacy_file';

    private const BASELINE_NONE = 'none';

    private const BASELINE_SNAPSHOT = 'database_snapshot';

    public function __construct(
        private readonly Filesystem $files,
        private readonly MaddraxikonDatasetValidator $validator,
        private readonly AtomicFileWriter $writer,
        private readonly MaddraxikonSnapshotRepository $snapshots,
    ) {}

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $datasets
     * @return array{id: string, hash: string, manifest: array<string, mixed>}
     */
    public function stage(array $datasets): array
    {
        $this->pruneExpired();

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

                $baseline = $this->activeBaseline($type);
                $this->validator->validate(
                    $rows,
                    $type,
                    $baseline['rows'],
                    baselineSource: $baseline['source'],
                );
                $json = $this->encode($rows);
                $filename = "{$seriesKey}.json";
                $path = $directory.DIRECTORY_SEPARATOR.$filename;
                $this->writer->write($path, $json);
                $this->assertCandidateFile(
                    $path,
                    $rows,
                    $type,
                    $baseline['rows'],
                    $baseline['source'],
                );

                $manifestSeries[$seriesKey] = [
                    'type' => $type->value,
                    'filename' => $filename,
                    'sha256' => hash_file('sha256', $path),
                    'count' => count($rows),
                    'highest_number' => max(array_column($rows, 'nummer')),
                    'baseline' => [
                        'source' => $baseline['source'],
                        'fingerprint' => $baseline['fingerprint'],
                    ],
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
     *     json: array<string, string>,
     *     already_active: bool
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
        try {
            $expiresAt = isset($manifest['expires_at'])
                ? Carbon::parse((string) $manifest['expires_at'])
                : null;
        } catch (\Throwable $exception) {
            $this->files->deleteDirectory($directory);

            throw new MaddraxikonCrawlException(
                "Kandidat {$id} enthält kein gültiges Ablaufdatum.",
                previous: $exception,
            );
        }

        if ($expiresAt === null || $expiresAt->isPast()) {
            $this->files->deleteDirectory($directory);

            throw new MaddraxikonCrawlException("Kandidat {$id} ist abgelaufen.");
        }

        $series = $manifest['series'] ?? null;

        if (! is_array($series) || $series === []) {
            throw new MaddraxikonCrawlException("Kandidat {$id} enthält keine Reihen.");
        }

        $datasets = [];
        $jsonBySeries = [];
        $activationStates = [];

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
            $baseline = $this->activeBaseline($type);
            $alreadyActive = $this->assertBaselineUnchanged($id, $type, $metadata, $baseline);

            if ($alreadyActive && $rows !== $baseline['rows']) {
                throw new MaddraxikonCrawlException(
                    "Der aktive Datenbank-Snapshot für {$type->label()} stimmt nicht mit Kandidat {$id} überein."
                );
            }

            $this->validator->validate(
                $rows,
                $type,
                $baseline['rows'],
                baselineSource: $baseline['source'],
            );
            $datasets[$seriesKey] = $rows;
            $jsonBySeries[$seriesKey] = $json;
            $activationStates[] = $alreadyActive;
        }

        if (in_array(true, $activationStates, true) && in_array(false, $activationStates, true)) {
            throw new MaddraxikonCrawlException(
                "Kandidat {$id} ist nur für einen Teil seiner Reihen aktiv und kann nicht sicher wiederholt werden."
            );
        }

        return [
            'manifest' => $manifest,
            'datasets' => $datasets,
            'json' => $jsonBySeries,
            'already_active' => ! in_array(false, $activationStates, true),
        ];
    }

    public function delete(string $id): bool
    {
        $this->assertCandidateId($id);
        $directory = $this->candidateDirectory($id);

        return ! $this->files->isDirectory($directory)
            || $this->files->deleteDirectory($directory);
    }

    public function pruneExpired(): int
    {
        $root = Storage::disk('private')->path('maddrax-candidates');

        if (! $this->files->isDirectory($root)) {
            return 0;
        }

        $deleted = 0;

        foreach ($this->files->directories($root) as $directory) {
            if (! $this->isCandidateId(basename($directory)) || ! $this->isExpired($directory)) {
                continue;
            }

            if ($this->files->deleteDirectory($directory)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function assertCandidateId(string $id): void
    {
        if (! $this->isCandidateId($id)) {
            throw new MaddraxikonCrawlException('Ungültige Kandidaten-ID.');
        }
    }

    private function isCandidateId(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id) === 1;
    }

    private function candidateDirectory(string $id): string
    {
        return Storage::disk('private')->path('maddrax-candidates/'.$id);
    }

    /**
     * @return array{
     *     rows: array<int, mixed>|null,
     *     source: 'database_snapshot'|'legacy_file'|'none',
     *     fingerprint: string|null
     * }
     */
    private function activeBaseline(BookType $type): array
    {
        $snapshot = $this->snapshots->activeDataset($type);

        if ($snapshot !== null) {
            return [
                'rows' => $snapshot['rows'],
                'source' => self::BASELINE_SNAPSHOT,
                'fingerprint' => $snapshot['id'],
            ];
        }

        $path = Storage::disk('private')->path(MaddraxikonSeries::filename($type));

        if (! $this->files->isFile($path)) {
            return [
                'rows' => null,
                'source' => self::BASELINE_NONE,
                'fingerprint' => null,
            ];
        }

        $json = $this->files->get($path);

        try {
            $rows = $this->decode($json, 'aktiver Snapshot');
        } catch (MaddraxikonCrawlException) {
            $rows = null;
        }

        return [
            'rows' => $rows,
            'source' => self::BASELINE_FILE,
            'fingerprint' => hash('sha256', $json),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{
     *     rows: array<int, mixed>|null,
     *     source: 'database_snapshot'|'legacy_file'|'none',
     *     fingerprint: string|null
     * }  $current
     */
    private function assertBaselineUnchanged(
        string $candidateId,
        BookType $type,
        array $metadata,
        array $current,
    ): bool {
        $expected = $metadata['baseline'] ?? null;
        $source = is_array($expected) ? ($expected['source'] ?? null) : null;
        $fingerprint = is_array($expected) ? ($expected['fingerprint'] ?? null) : null;
        $validSource = is_string($source)
            && in_array($source, [self::BASELINE_SNAPSHOT, self::BASELINE_FILE, self::BASELINE_NONE], true);
        $validFingerprint = $source === self::BASELINE_NONE
            ? $fingerprint === null
            : is_string($fingerprint) && $fingerprint !== '';

        if (! $validSource || ! $validFingerprint) {
            throw new MaddraxikonCrawlException(
                "Kandidat {$candidateId} enthält keine gültige Baseline für {$type->label()}."
            );
        }

        if ($source === $current['source'] && $fingerprint === $current['fingerprint']) {
            return false;
        }

        if ($current['source'] === self::BASELINE_SNAPSHOT && $current['fingerprint'] === $candidateId) {
            return true;
        }

        throw new MaddraxikonCrawlException(
            "Die aktive Datenbasis für {$type->label()} hat sich seit der Kandidatenerstellung geändert. ".
            'Der Kandidat muss neu erzeugt werden.'
        );
    }

    private function isExpired(string $directory): bool
    {
        $manifestPath = $directory.DIRECTORY_SEPARATOR.'manifest.json';

        if ($this->files->isFile($manifestPath)) {
            try {
                $manifest = $this->decode($this->files->get($manifestPath), 'Kandidatenmanifest');

                if (isset($manifest['expires_at'])) {
                    return Carbon::parse((string) $manifest['expires_at'])->isPast();
                }
            } catch (\Throwable) {
                // Beschädigte Kandidaten werden nach ihrem Dateialter entfernt.
            }
        }

        $ttlMinutes = max(1, (int) config('maddraxikon.crawler.candidate_ttl_minutes', 360));

        return Carbon::createFromTimestamp($this->files->lastModified($directory))
            ->addMinutes($ttlMinutes)
            ->isPast();
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
        string $baselineSource,
    ): void {
        $rows = $this->decode($this->files->get($path), basename($path));
        $this->validator->validate($rows, $type, $baseline, baselineSource: $baselineSource);

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
