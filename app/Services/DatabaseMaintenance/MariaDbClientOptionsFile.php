<?php

namespace App\Services\DatabaseMaintenance;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class MariaDbClientOptionsFile
{
    private function __construct(
        private readonly string $path,
    ) {}

    /**
     * @param  array<string, mixed>  $connection
     */
    public static function create(array $connection, string $directory): self
    {
        File::ensureDirectoryExists($directory, 0700);

        $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'client-'.Str::uuid().'.cnf';
        $contents = self::contents($connection);

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new DatabaseMaintenanceException('Die temporaere MariaDB-Optionsdatei konnte nicht geschrieben werden.');
        }

        @chmod($path, 0600);

        return new self($path);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function delete(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private static function contents(array $connection): string
    {
        $lines = ['[client]'];

        $socket = (string) ($connection['unix_socket'] ?? '');
        if ($socket !== '') {
            $lines[] = 'socket='.self::quote($socket);
        } else {
            $lines[] = 'host='.self::quote((string) ($connection['host'] ?? '127.0.0.1'));
            $lines[] = 'port='.(int) ($connection['port'] ?? 3306);
        }

        $lines[] = 'user='.self::quote((string) ($connection['username'] ?? ''));
        $lines[] = 'password='.self::quote((string) ($connection['password'] ?? ''));
        $lines[] = 'default-character-set='.self::quote((string) ($connection['charset'] ?? 'utf8mb4'));

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private static function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\"', '', ''], $value).'"';
    }
}
