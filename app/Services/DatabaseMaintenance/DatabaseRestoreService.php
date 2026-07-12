<?php

namespace App\Services\DatabaseMaintenance;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DatabaseRestoreService
{
    public function __construct(
        private readonly DatabaseConnectionResolver $connectionResolver,
        private readonly DatabaseDumpService $dumpService,
        private readonly DatabaseMaintenanceAuditLogger $auditLogger,
    ) {}

    public function restore(UploadedFile $uploadedFile, User $user): DatabaseRestoreResult
    {
        $this->connectionResolver->current();

        $originalName = $uploadedFile->getClientOriginalName();
        $this->auditLogger->log('restore_requested', [
            'original_name' => $originalName,
            'uploaded_bytes' => $uploadedFile->getSize(),
        ]);

        $stagedPath = null;
        $preparedSqlPath = null;
        $deletePreparedSql = false;
        $preRestoreDump = null;

        try {
            $stagedPath = $this->stageUpload($uploadedFile);
            $preRestoreDump = $this->dumpService->createPreRestoreDump($user);
            [$preparedSqlPath, $deletePreparedSql] = $this->prepareSqlFile($stagedPath);

            $this->runRestore($preparedSqlPath);

            $this->auditLogger->log('restore_completed', [
                'original_name' => $originalName,
                'pre_restore_dump' => basename($preRestoreDump->path),
            ]);

            return new DatabaseRestoreResult($preRestoreDump->path);
        } catch (\Throwable $exception) {
            $this->auditLogger->log('restore_failed', [
                'original_name' => $originalName,
                'pre_restore_dump' => $preRestoreDump ? basename($preRestoreDump->path) : null,
                'error' => Str::limit($exception->getMessage(), 500),
            ]);

            if ($exception instanceof DatabaseMaintenanceException) {
                throw $exception;
            }

            throw new DatabaseMaintenanceException('Der Datenbank-Restore konnte nicht ausgefuehrt werden.', 0, $exception);
        } finally {
            if ($stagedPath) {
                File::delete($stagedPath);
            }

            if ($deletePreparedSql && $preparedSqlPath) {
                File::delete($preparedSqlPath);
            }
        }
    }

    private function stageUpload(UploadedFile $uploadedFile): string
    {
        $directory = $this->path('uploads');
        File::ensureDirectoryExists($directory, 0700);

        $extension = $this->isGzipSql($uploadedFile->getClientOriginalName()) ? 'sql.gz' : 'sql';
        $filename = 'restore-'.now()->format('Y-m-d-His').'-'.Str::random(12).'.'.$extension;

        $uploadedFile->move($directory, $filename);

        return $directory.DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function prepareSqlFile(string $path): array
    {
        if ($this->isGzipSql($path)) {
            return [$this->unpackGzip($path), true];
        }

        $maxUncompressedBytes = $this->maxUncompressedBytes();

        if ($maxUncompressedBytes > 0) {
            $size = @filesize($path);

            if ($size === false) {
                throw new DatabaseMaintenanceException('Die SQL-Datei konnte nicht groessenvalidiert werden.');
            }

            if ($size > $maxUncompressedBytes) {
                throw new DatabaseMaintenanceException('Die SQL-Datei ist groesser als die erlaubte entpackte Maximalgroesse.');
            }
        }

        $this->assertOmxfcMarkerIfRequired($path);

        return [$path, false];
    }

    private function unpackGzip(string $path): string
    {
        $read = @gzopen($path, 'rb');
        if ($read === false) {
            throw new DatabaseMaintenanceException('Die gzip-Datei konnte nicht gelesen werden.');
        }

        $target = $this->path('temp').DIRECTORY_SEPARATOR.'restore-'.Str::uuid().'.sql';
        $write = @fopen($target, 'wb');
        if ($write === false) {
            gzclose($read);

            throw new DatabaseMaintenanceException('Die temporaere SQL-Datei konnte nicht erstellt werden.');
        }

        $maxUncompressedBytes = $this->maxUncompressedBytes();
        $writtenBytes = 0;

        try {
            try {
                while (! gzeof($read)) {
                    $buffer = gzread($read, 1024 * 1024);

                    if ($buffer === false) {
                        throw new DatabaseMaintenanceException('Die gzip-Datei konnte nicht entpackt werden.');
                    }

                    $writtenBytes += strlen($buffer);
                    if ($maxUncompressedBytes > 0 && $writtenBytes > $maxUncompressedBytes) {
                        throw new DatabaseMaintenanceException('Die entpackte SQL-Datei ist groesser als erlaubt.');
                    }

                    if (fwrite($write, $buffer) === false) {
                        throw new DatabaseMaintenanceException('Die temporaere SQL-Datei konnte nicht geschrieben werden.');
                    }
                }
            } finally {
                gzclose($read);
                fclose($write);
            }

            $this->assertOmxfcMarkerIfRequired($target);

            return $target;
        } catch (\Throwable $exception) {
            File::delete($target);

            throw $exception;
        }
    }

    private function runRestore(string $sqlPath): void
    {
        $connection = $this->connectionResolver->current();
        $databaseName = $this->connectionResolver->databaseName($connection);
        $optionsFile = MariaDbClientOptionsFile::create($connection, $this->path('temp'));
        $input = @fopen($sqlPath, 'rb');

        if ($input === false) {
            $optionsFile->delete();

            throw new DatabaseMaintenanceException('Die SQL-Datei konnte fuer den Restore nicht geoeffnet werden.');
        }

        $process = new Process([
            (string) config('database-maintenance.client_binary', 'mariadb'),
            '--defaults-extra-file='.$optionsFile->path(),
            '--default-character-set=utf8mb4',
            $databaseName,
        ]);
        $process->setInput($input);
        $process->setTimeout((int) config('database-maintenance.process_timeout_seconds', 300));

        $stderr = '';

        try {
            $process->run(function (string $type, string $buffer) use (&$stderr): void {
                if ($type === Process::ERR) {
                    $stderr = strlen($stderr.$buffer) > 4000 ? substr($stderr.$buffer, -4000) : $stderr.$buffer;
                }
            });
        } finally {
            fclose($input);
            $optionsFile->delete();
        }

        if (! $process->isSuccessful()) {
            $message = trim($stderr) ?: 'Der MariaDB-Import ist fehlgeschlagen.';

            throw new DatabaseMaintenanceException($message, previous: new ProcessFailedException($process));
        }
    }

    private function assertOmxfcMarkerIfRequired(string $sqlPath): void
    {
        if (! (bool) config('database-maintenance.require_omxfc_dump_marker', false)) {
            return;
        }

        $handle = @fopen($sqlPath, 'rb');
        if ($handle === false) {
            throw new DatabaseMaintenanceException('Die SQL-Datei konnte nicht validiert werden.');
        }

        $prefix = fread($handle, 4096);
        fclose($handle);

        if (! is_string($prefix) || ! str_contains($prefix, 'OMXFC database dump')) {
            throw new DatabaseMaintenanceException('Die SQL-Datei enthaelt keinen gueltigen OMXFC-Dump-Marker.');
        }
    }

    private function maxUncompressedBytes(): int
    {
        return DatabaseMaintenanceLimitService::megabytesToBytes(
            config('database-maintenance.max_uncompressed_mb'),
        ) ?? 0;
    }

    private function isGzipSql(string $path): bool
    {
        return str_ends_with(strtolower($path), '.sql.gz');
    }

    private function path(string $section): string
    {
        $path = rtrim((string) config('database-maintenance.storage_root'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$section;

        File::ensureDirectoryExists($path, 0700);

        return $path;
    }
}
