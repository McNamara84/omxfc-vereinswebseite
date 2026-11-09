<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait ProfileTourMariaDbConcurrencyTestFixtures
{
    use RefreshDatabase;

    private bool $committed = false;

    private function race(array $commands): array
    {
        DB::connection()->commit();
        $this->committed = true;
        $workers = [];
        try {
            foreach ($commands as $command) {
                $process = proc_open([PHP_BINARY, base_path('tests/Support/MariaDbBaxxWorker.php'), ...array_map(strval(...), $command)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                if (! is_resource($process)) {
                    throw new RuntimeException('Worker konnte nicht starten.');
                }
                stream_set_timeout($pipes[1], 30);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [$process, $pipes]) {
                expect(fgets($pipes[1]))->toBe("ready\n");
            }
            foreach ($workers as [$process, $pipes]) {
                fwrite($pipes[0], "RUN\n");
                fflush($pipes[0]);
            }
            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $line = fgets($pipes[1]);
                expect($line)->not->toBeFalse();
                $results[] = json_decode($line, true, flags: JSON_THROW_ON_ERROR)['result'];
            }

            return $results;
        } finally {
            foreach ($workers as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                }
                proc_close($process);
            }
        }
    }
}
