<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

use function array_filter;
use function base_path;
use function config;
use function count;
use function fclose;
use function fgets;
use function function_exists;
use function fwrite;
use function getenv;
use function implode;
use function in_array;
use function intdiv;
use function is_array;
use function is_resource;
use function is_string;
use function json_decode;
use function max;
use function microtime;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function sprintf;
use function str_contains;
use function stream_get_contents;
use function stream_select;
use function trim;

trait MaddraxikonOAuthAttemptStoreMariaDbConcurrencyTestFixtures
{
    use RefreshDatabase;

    private bool $committedTestDatabase = false;

    private function requireSafeMariaDbTestDatabase(): void
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Der echte OAuth-Nebenlaeufigkeitstest benoetigt MariaDB.');
        }
        if ($database !== 'omxfc_maddraxikon_test') {
            throw new RuntimeException(sprintf('Unsichere Testdatenbank verweigert: %s.', $database));
        }
        $version = (string) (DB::selectOne('SELECT VERSION() AS database_version')->database_version ?? '');
        if (! str_contains($version, 'MariaDB')) {
            $this->markTestSkipped('Der echte OAuth-Nebenlaeufigkeitstest wird nur auf MariaDB ausgefuehrt.');
        }
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open wird fuer die getrennten Worker-Prozesse benoetigt.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function workerEnvironment(string $appKey, string $cachePrefix): array
    {
        $environment = getenv();
        if (! is_array($environment)) {
            $environment = [];
        }
        $environment = array_filter($environment, is_string(...));
        $environment['APP_ENV'] = 'testing';
        $environment['APP_KEY'] = $appKey;
        $environment['CACHE_STORE'] = 'database';
        $environment['CACHE_PREFIX'] = $cachePrefix;
        $environment['DB_CONNECTION'] = (string) config('database.default');
        $environment['DB_DATABASE'] = 'omxfc_maddraxikon_test';

        return $environment;
    }

    /**
     * @param  array<string, string>  $environment
     * @return array{
     *     process: resource,
     *     stdin: resource,
     *     stdout: resource,
     *     stderr: resource
     * }
     */
    private function startWorker(int $userId, string $state, string $sessionId, array $environment): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $pipes = [];
        $process = proc_open([PHP_BINARY, base_path('tests/Support/MariaDbOAuthAttemptConcurrencyWorker.php'), (string) $userId, $state, $sessionId], $descriptors, $pipes, base_path(), $environment, ['bypass_shell' => true]);
        if (! is_resource($process) || count($pipes) !== 3) {
            throw new RuntimeException('Der OAuth-Nebenlaeufigkeits-Worker konnte nicht starten.');
        }

        return ['process' => $process, 'stdin' => $pipes[0], 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    }

    /**
     * @param  array{
     *     process: resource,
     *     stdin: resource,
     *     stdout: resource,
     *     stderr: resource
     * }  $worker
     * @return array<string, mixed>
     */
    private function readWorkerResult(array $worker, int $timeoutSeconds): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $line = null;
        do {
            $remainingMicroseconds = (int) max(0, ($deadline - microtime(true)) * 1000000);
            $read = [$worker['stdout']];
            $write = null;
            $except = null;
            $ready = stream_select($read, $write, $except, intdiv($remainingMicroseconds, 1000000), $remainingMicroseconds % 1000000);
            if ($ready !== 1) {
                throw new RuntimeException('Zeitueberschreitung beim Warten auf den OAuth-Worker.');
            }
            $line = fgets($worker['stdout']);
            if ($line === false) {
                $stderr = trim((string) stream_get_contents($worker['stderr']));
                throw new RuntimeException('Der OAuth-Worker endete ohne Ergebnis. '.$stderr);
            }
        } while (trim($line) === '' && microtime(true) < $deadline);
        if (trim($line) === '') {
            throw new RuntimeException('Der OAuth-Worker lieferte nur leere Ausgabe.');
        }
        try {
            $result = json_decode(trim($line), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $stderr = trim((string) stream_get_contents($worker['stderr']));
            throw new RuntimeException(sprintf('Ungueltige OAuth-Worker-Ausgabe: stdout=%s stderr=%s', trim($line), $stderr), previous: $exception);
        }
        if (! is_array($result)) {
            throw new RuntimeException('Der OAuth-Worker lieferte kein JSON-Objekt.');
        }

        return $result;
    }

    /**
     * @param  array{stdin: resource}  $worker
     */
    private function sendWorkerCommand(array $worker, string $command): void
    {
        if (! isset($worker['stdin']) || ! is_resource($worker['stdin'])) {
            return;
        }
        $written = @fwrite($worker['stdin'], $command.PHP_EOL);
        if ($written !== false) {
            @fflush($worker['stdin']);
        }
    }

    /**
     * @param  array{
     *     process: resource,
     *     stdin: resource,
     *     stdout: resource,
     *     stderr: resource
     * }  $worker
     */
    private function finishWorker(array &$worker): int
    {
        foreach (['stdin', 'stdout', 'stderr'] as $pipe) {
            if (is_resource($worker[$pipe])) {
                fclose($worker[$pipe]);
            }
        }
        if (! is_resource($worker['process'])) {
            return -1;
        }
        $exitCode = proc_close($worker['process']);
        $worker = [];

        return $exitCode;
    }

    /**
     * @param  array{
     *     process?: resource,
     *     stdin?: resource,
     *     stdout?: resource,
     *     stderr?: resource
     * }  $worker
     */
    private function terminateWorker(array &$worker): void
    {
        if ($worker === []) {
            return;
        }
        foreach (['stdin', 'stdout', 'stderr'] as $pipe) {
            if (isset($worker[$pipe]) && is_resource($worker[$pipe])) {
                fclose($worker[$pipe]);
            }
        }
        if (isset($worker['process']) && is_resource($worker['process'])) {
            $status = proc_get_status($worker['process']);
            if ($status['running'] ?? false) {
                proc_terminate($worker['process']);
            }
            proc_close($worker['process']);
        }
        $worker = [];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function workerFailure(array $result): string
    {
        return implode(' ', array_filter([(string) ($result['exception'] ?? ''), (string) ($result['message'] ?? '')])) ?: 'Der OAuth-Worker meldete keinen Fehlertext.';
    }
}
