<?php

namespace App\Services\DatabaseMaintenance;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DatabaseDumpService
{
    public function __construct(
        private readonly DatabaseConnectionResolver $connectionResolver,
        private readonly DatabaseMaintenanceAuditLogger $auditLogger,
    ) {}

    public function createDownloadDump(User $user): DatabaseDumpFile
    {
        $downloadName = 'omxfc-datenbank-'.now()->format('Y-m-d-His').'-'.Str::lower(Str::random(8)).'.sql.gz';
        $path = $this->path('downloads', $downloadName);

        $this->writeDump($path, $user);
        $this->auditLogger->log('dump_created', [
            'filename' => $downloadName,
            'bytes' => filesize($path) ?: null,
            'purpose' => 'download',
        ]);

        return new DatabaseDumpFile($path, $downloadName);
    }

    public function createPreRestoreDump(User $user): DatabaseDumpFile
    {
        $downloadName = 'pre-restore-'.now()->format('Y-m-d-His').'-user-'.$user->id.'-'.Str::lower(Str::random(8)).'.sql.gz';
        $path = $this->path('pre-restore', $downloadName);

        $this->writeDump($path, $user);
        $this->auditLogger->log('pre_restore_dump_created', [
            'filename' => $downloadName,
            'bytes' => filesize($path) ?: null,
        ]);

        return new DatabaseDumpFile($path, $downloadName);
    }

    public function storagePath(string $section): string
    {
        return rtrim((string) config('database-maintenance.storage_root'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$section;
    }

    private function writeDump(string $path, User $user): void
    {
        $connection = $this->connectionResolver->current();
        $databaseName = $this->connectionResolver->databaseName($connection);
        $optionsFile = MariaDbClientOptionsFile::create($connection, $this->storagePath('temp'));
        $gzip = @gzopen($path, 'wb9');

        if ($gzip === false) {
            $optionsFile->delete();

            throw new DatabaseMaintenanceException('Die Dump-Datei konnte nicht erstellt werden.');
        }

        $process = null;
        $stderr = '';

        try {
            $this->writeHeader($gzip, $user, $connection);

            $process = new Process($this->dumpCommand($optionsFile->path(), $databaseName));
            $process->setTimeout((int) config('database-maintenance.process_timeout_seconds', 300));
            $process->run(function (string $type, string $buffer) use ($gzip, &$stderr): void {
                if ($type === Process::OUT) {
                    gzwrite($gzip, $buffer);

                    return;
                }

                $stderr = $this->appendLimited($stderr, $buffer);
            });
        } catch (\Throwable $exception) {
            throw new DatabaseMaintenanceException('Der Datenbank-Dump konnte nicht erzeugt werden.', 0, $exception);
        } finally {
            gzclose($gzip);
            $optionsFile->delete();
        }

        if (! $process instanceof Process || ! $process->isSuccessful()) {
            File::delete($path);

            $message = trim($stderr) ?: 'Der Datenbank-Dump ist fehlgeschlagen.';

            throw new DatabaseMaintenanceException($message, previous: $process ? new ProcessFailedException($process) : null);
        }
    }

    /**
     * @return array<int, string>
     */
    private function dumpCommand(string $optionsPath, string $databaseName): array
    {
        return [
            (string) config('database-maintenance.dump_binary', 'mariadb-dump'),
            '--defaults-extra-file='.$optionsPath,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            '--hex-blob',
            '--add-drop-table',
            '--add-drop-trigger',
            $databaseName,
        ];
    }

    /**
     * @param  resource  $gzip
     * @param  array<string, mixed>  $connection
     */
    private function writeHeader($gzip, User $user, array $connection): void
    {
        $lines = [
            '-- OMXFC database dump',
            '-- format: omxfc-sql-gzip-v1',
            '-- generated_at: '.now()->toIso8601String(),
            '-- app: '.(string) config('app.name', 'OMXFC'),
            '-- environment: '.app()->environment(),
            '-- database: '.$this->connectionResolver->databaseName($connection),
            '-- user_id: '.$user->id,
            '',
        ];

        gzwrite($gzip, implode(PHP_EOL, $lines).PHP_EOL);
    }

    private function path(string $section, string $filename): string
    {
        $directory = $this->storagePath($section);
        File::ensureDirectoryExists($directory, 0700);

        return $directory.DIRECTORY_SEPARATOR.Str::of($filename)->replace(['/', '\\'], '-');
    }

    private function appendLimited(string $current, string $buffer): string
    {
        $combined = $current.$buffer;

        return strlen($combined) > 4000 ? substr($combined, -4000) : $combined;
    }
}
