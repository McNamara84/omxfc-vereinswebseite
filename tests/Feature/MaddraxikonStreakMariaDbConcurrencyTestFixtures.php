<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonStreakMariaDbConcurrencyTestFixtures
{
    use RefreshDatabase;

    private bool $committed = false;

    private function workers(int $invalidRevision, ?callable $afterReady = null, bool $activate = false): void
    {
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, base_path('tests/Support/MariaDbMaddraxikonStreakWorker.php'), (string) $invalidRevision, $activate ? 'activate' : 'evaluate'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                expect($process)->toBeResource();
                stream_set_timeout($pipes[1], 30);
                stream_set_blocking($pipes[2], false);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [, $pipes]) {
                $ready = fgets($pipes[1]);
                expect($ready)->toBe("ready\n");
            }
            if ($afterReady) {
                $afterReady();
            }
            foreach ($workers as [, $pipes]) {
                fwrite($pipes[0], "go\n");
                fflush($pipes[0]);
            }
            foreach ($workers as [, $pipes]) {
                $result = fgets($pipes[1]);
                expect($result)->toBeString()
                    ->and(json_decode($result, true, flags: JSON_THROW_ON_ERROR))->toHaveKey('bookings');
            }
        } finally {
            foreach ($workers as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                $status = proc_get_status($process);
                if ($status['running']) {
                    proc_terminate($process);
                }
                proc_close($process);
            }
        }
    }
}
